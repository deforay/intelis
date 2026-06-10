<?php

// app/remote/v2/verify-manifest.php
use Slim\Psr7\Request;
use App\Services\ApiService;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Services\STS\TokensService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var Request $request */
$request = AppRegistry::get('request');
$origJson = $apiService->getJsonFromRequest($request);
if (JsonUtility::isJSON($origJson) === false) {
    throw new SystemException("Invalid JSON Payload", 400);
}
$input = JsonUtility::decodeJson($origJson, true);
if (
    empty($input) ||
    empty($input['testType']) ||
    empty($input['labId']) ||
    empty($input['manifestCode']) ||
    empty($input['manifestHash'])
) {
    http_response_code(400);
    throw new SystemException(_translate('Invalid request'), 400);
}

$transactionId = MiscUtility::generateULID();

/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$authToken = ApiService::extractBearerToken($request);
$user = $usersService->findUserByApiToken($authToken);

$payload = [
    'status' => 'not-found',
];

$labId = (int) ($input['labId'] ?? 0);
$testType = trim((string) ($input['testType'] ?? ''));

try {
    $manifestCode = trim((string) ($input['manifestCode'] ?? ''));
    $providedHash = trim((string) ($input['manifestHash'] ?? ''));

    if ($manifestCode === '' || $providedHash === '') {
        http_response_code(400);
        throw new SystemException('Manifest code and hash are required', 400);
    }

    if ($labId <= 0) {
        http_response_code(400);
        throw new SystemException('Lab ID is required', 400);
    }

    $token = $stsTokensService->validateToken($authToken, $labId);
    if ($token === false || empty($token)) {
        $labDetails = $facilitiesService->getFacilityById($labId);
        http_response_code(401);
        throw new SystemException("Unauthorized Access. Token missing or invalid for lab {$labDetails['facility_name']}.", 401);
    }

    $db->where('manifest_code', $manifestCode);
    $db->where('module', $testType);
    $db->where('lab_id', $labId);
    $manifestRecord = $db->getOne('specimen_manifests');

    if (empty($manifestRecord)) {
        // The requesting lab does not own this manifest. Whether wrong-lab or
        // not-found is a question of OWNERSHIP, not of whether samples exist:
        // this endpoint runs on STS, which holds every lab's samples, so a sample
        // lookup would happily match an other-lab manifest and mask the real
        // answer. Referral-ness (tb/generic) is irrelevant here — it only changes
        // how samples are linked for the hash check below, not who owns the code.
        $db->reset();
        $db->where('manifest_code', $manifestCode);
        $db->where('module', $testType);
        $otherLabManifest = $db->getOne('specimen_manifests', ['lab_id']);

        http_response_code(404);
        if (!empty($otherLabManifest['lab_id'])) {
            $otherLab = $facilitiesService->getFacilityById((int) $otherLabManifest['lab_id']);
            $payload['status'] = 'wrong-lab';
            $payload['message'] = 'Manifest is registered to a different testing lab.';
            $payload['labName'] = $otherLab['facility_name'] ?? null;
        } else {
            $payload['status'] = 'not-found';
            $payload['message'] = 'Manifest not found.';
        }
    } else {
        // The requesting lab owns the manifest — compare hashes to decide match
        // vs. mismatch (sync). TB/generic referral samples link via
        // referral_manifest_code, so widen the lookup for those.
        $tableName = TestsService::getTestTableName($testType);
        $primaryKey = TestsService::getPrimaryColumn($testType);
        $db->reset();
        $db->where('sample_package_code', $manifestCode);
        if ($testType === 'tb' || $testType === 'generic-tests') {
            $db->orWhere('referral_manifest_code', $manifestCode);
        }
        $selectedSamples = $db->getValue($tableName, $primaryKey, null);
        $currentHash = $testRequestsService->getManifestHash($selectedSamples, $testType, $manifestCode);

        if ($currentHash !== '') {
            if (hash_equals($currentHash, $providedHash)) {
                $payload['status'] = 'match';
            } else {
                $payload['status'] = 'mismatch';
                $payload['message'] = 'Manifest hash mismatch.';
            }
        }
    }
} catch (Throwable $exc) {
    $statusCode = (int) ($exc->getCode() ?: 500);
    if ($statusCode < 400 || $statusCode > 599) {
        $statusCode = 500;
    }
    http_response_code($statusCode);
    $payload = [
        'status' => 'error',
        'message' => $exc->getMessage(),
    ];
    LoggerUtility::logError($exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'requestUrl' => $requestUrl,
        'stacktrace' => $exc->getTraceAsString()
    ]);
} finally {
    $encodedPayload = JsonUtility::encodeUtf8Json($payload ?? []);
    $userId = $user['user_id'] ?? null;
    $recordsCount = $payload['status'] === 'match' ? 1 : 0;
    $general->addApiTracking($transactionId, $userId, $recordsCount, 'manifest-verify', $testType, $requestUrl, $origJson, $encodedPayload, 'json', $labId, null, $authToken);
    echo ApiService::generateJsonResponse($encodedPayload, $request);
}
