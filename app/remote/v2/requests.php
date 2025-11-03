<?php

use App\Services\ApiService;
use App\Services\TestsService;
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
use App\Services\STS\RequestsService;

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var RequestsService $stsRequestsService */
$stsRequestsService = ContainerRegistry::get(RequestsService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


$payload = [];

try {
    $db->beginTransaction();

    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');

    $authToken = ApiService::extractBearerToken($request);
    $data      = $apiService->getJsonFromRequest($request, true);

    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $labId = $data['labId'] ?? null;
    if (!$labId) {
        // Let the generator choose body/headers for 400
        throw new SystemException('Lab ID is missing in the request', 400);
    }

    $token = $stsTokensService->validateToken($authToken, $labId);
    if ($token === false || empty($token)) {
        $labDetails = $facilitiesService->getFacilityById($labId);
        http_response_code(401);
        throw new SystemException("Unauthorized Access. Token missing or invalid for lab {$labDetails['facility_name']}.", 401);
    }

    if (is_string($token)) {
        $payload['token'] = $token;
    }

    $syncSinceDate = $data['syncSinceDate'] ?? null;
    $manifestCode  = $data['manifestCode'] ?? null;
    $testType      = $data['testType'] ?? null;

    if (!$testType) {
        throw new SystemException('Test Type is missing in the request', 400);
    }

    $tableName      = TestsService::getTestTableName($testType);
    $primaryKeyName = TestsService::getPrimaryColumn($testType);

    $facilityMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

    $requestsData = $stsRequestsService->getRequests(
        $testType,
        $labId,
        $facilityMapResult ?? [],
        $manifestCode,
        $syncSinceDate
    );

    $sampleIds   = $requestsData['sampleIds'] ?? [];
    $facilityIds = $requestsData['facilityIds'] ?? [];
    $requests    = $requestsData['requests'] ?? [];

    $payload = [
        'status'        => 'success',
        'requests'      => $requests,
        'testType'      => $testType,
        'labId'         => $labId,
        'syncSinceDate' => $syncSinceDate,
    ];

    $general->addApiTracking(
        $transactionId,
        'system',
        count($requests),
        'requests',
        $testType,
        $_SERVER['REQUEST_URI'],
        JsonUtility::encodeUtf8Json($data),
        $payload,
        'json',
        $labId
    );

    if ($facilityIds) {
        $general->updateTestRequestsSyncDateTime($testType, $facilityIds, $labId);
    }

    if ($sampleIds) {
        $sampleIds = array_values(array_unique(array_filter(
            $sampleIds,
            static fn($id) => $id !== null && $id !== ''
        )));

        $maxRetries = 5;

        foreach (array_chunk($sampleIds, 100) as $batch) {
            $attempt = 0;

            while (true) {
                $db->where($primaryKeyName, $batch, 'IN');
                $updateResult = $db->update($tableName, ['data_sync' => 1]);

                if ($updateResult !== false) {
                    break;
                }

                $errorCode = (int) $db->getLastErrno();

                if (!in_array($errorCode, [1205, 1213], true)) {
                    throw new SystemException(
                        $db->getLastError() ?: 'Failed to update data_sync flag',
                        $errorCode ?: 500
                    );
                }

                if ($attempt >= $maxRetries) {
                    throw new SystemException('Unable to mark samples as synced due to persistent database locks', 1205);
                }

                $attempt++;
                usleep((int) (100000 * $attempt)); // Back off progressively
                $db->reset(); // Clear state before retrying
            }
        }
    }

    $db->commitTransaction();

    // Success path: produce JSON; Apache adds br/gzip
    echo ApiService::generateJsonResponse($payload, $request);
} catch (Throwable $e) {
    $db->rollbackTransaction();

    // Optional: set a safe message for prod (error generator reads this)
    $_SESSION['errorDisplayMessage'] = _translate('Unable to process the request');

    // Log extra context if you want
    LoggerUtility::log('error', $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception'     => $e,
    ]);

    // Re-throw so ErrorResponseGenerator builds the error JSON + status code
    throw new SystemException($e->getMessage(), ($e->getCode() ?: 500), $e);
}
