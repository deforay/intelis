<?php

// app/remote/v2/backup-key-recovery.php
//
// STS receiver that saves a LIS's backup encryption key for recovery. A LIS posts
// its base64 backup key (over TLS, Bearer-authenticated); the STS re-encrypts it
// at rest with its own instance key and upserts the key-store row. The returned
// fingerprint is the verification the LIS checks -- one round-trip, no second
// endpoint.
//
// Modeled on verify-manifest.php (Bearer extract -> validateToken -> JSON in ->
// addApiTracking -> generateJsonResponse).

use Slim\Psr7\Request;
use App\Services\ApiService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Utilities\CryptoUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use App\Services\UsersService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Services\STS\TokensService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

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

$transactionId = MiscUtility::generateULID();

/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$authToken = ApiService::extractBearerToken($request);
$user = $usersService->findUserByApiToken($authToken);

$payload = [
    'status' => 'error',
];

$labId = (int) ($input['labId'] ?? 0);

try {
    if (empty($input) || $labId <= 0) {
        http_response_code(400);
        throw new SystemException('Lab ID is required', 400);
    }

    $token = $stsTokensService->validateToken($authToken, $labId);
    if ($token === false || empty($token)) {
        $labDetails = $facilitiesService->getFacilityById($labId);
        http_response_code(401);
        throw new SystemException("Unauthorized Access. Token missing or invalid for lab {$labDetails['facility_name']}.", 401);
    }

    $backupKey = trim((string) ($input['backupKey'] ?? ''));
    $providedFingerprint = trim((string) ($input['fingerprint'] ?? ''));
    $keyVersion = (int) ($input['keyVersion'] ?? 1);
    $instanceId = isset($input['instanceId']) ? substr((string) $input['instanceId'], 0, 64) : null;

    if ($backupKey === '' || $providedFingerprint === '') {
        http_response_code(400);
        throw new SystemException('Key and fingerprint are required', 400);
    }

    // Integrity check: the fingerprint must match the key we received, so a
    // corrupted/truncated payload is rejected rather than silently stored.
    if (!hash_equals(hash('sha256', $backupKey), $providedFingerprint)) {
        http_response_code(400);
        throw new SystemException('Fingerprint does not match key', 400);
    }

    // Re-encrypt at rest with the STS's own instance key (var/key.storage).
    $encryptedKey = CryptoUtility::encrypt($backupKey);
    $now = DateUtility::getCurrentDateTime();

    $db->where('facility_id', $labId);
    $db->where('key_version', $keyVersion);
    $existing = $db->getOne('s_lis_backup_key_recovery', ['id']);

    $row = [
        'facility_id'      => $labId,
        'vlsm_instance_id' => $instanceId,
        'key_version'      => $keyVersion,
        'encrypted_key'    => $encryptedKey,
        'fingerprint'      => $providedFingerprint,
        'updated_datetime' => $now,
    ];

    if (!empty($existing['id'])) {
        $db->where('id', (int) $existing['id']);
        $db->update('s_lis_backup_key_recovery', $row);
    } else {
        $row['saved_at'] = $now;
        $db->insert('s_lis_backup_key_recovery', $row);
    }

    $payload = [
        'status'      => 'success',
        'fingerprint' => $providedFingerprint,
        'version'     => $keyVersion,
    ];
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
    $recordsCount = ($payload['status'] ?? '') === 'success' ? 1 : 0;
    // Never persist the raw key in API tracking — redact it from the stored request body.
    $trackedRequest = $input ?? [];
    if (isset($trackedRequest['backupKey'])) {
        $trackedRequest['backupKey'] = '[redacted]';
    }
    $trackedJson = JsonUtility::encodeUtf8Json($trackedRequest);
    $general->addApiTracking($transactionId, $userId, $recordsCount, 'backup-key-recovery', null, $requestUrl, $trackedJson, $encodedPayload, 'json', $labId, null, $authToken);
    echo ApiService::generateJsonResponse($encodedPayload, $request);
}
