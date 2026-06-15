<?php

// app/remote/v2/backup-key-release.php
//
// STS endpoint that releases an escrowed backup key to a replacement machine in
// exchange for a one-time recovery token (minted by an STS admin via
// `bin/backup-key-admin.php approve`). The token IS the capability: it is
// admin-issued, single-use, expiring, and stored only as a sha256 hash, so no
// Bearer/STS token is required (the dead machine's token isn't available anyway).
//
// On success it returns the key (base64) and marks the row released so the token
// cannot be replayed. This endpoint exists only to hand back a key the STS already
// holds; nothing calls it unless a migration restore does, so it is inert for the
// existing fleet.

use Slim\Psr7\Request;
use App\Services\ApiService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Utilities\CryptoUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var Request $request */
$request = AppRegistry::get('request');
$origJson = $apiService->getJsonFromRequest($request);
if (JsonUtility::isJSON($origJson) === false) {
    throw new SystemException("Invalid JSON Payload", 400);
}
$input = JsonUtility::decodeJson($origJson, true);

$transactionId = MiscUtility::generateULID();
$requestUrl = ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

$payload = ['status' => 'error'];
$labId = null;

try {
    if (!$general->isSTSInstance()) {
        http_response_code(404);
        throw new SystemException('Not available on this instance', 404);
    }

    $rawToken = trim((string) ($input['recoveryToken'] ?? ''));
    // Normalize identically to bin/backup-key-admin.php: strip separators,
    // uppercase, fold look-alike characters (Crockford base32).
    $normalized = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $rawToken) ?? '');
    $normalized = strtr($normalized, ['I' => '1', 'L' => '1', 'O' => '0', 'U' => 'V']);

    if (strlen($normalized) < 12) {
        http_response_code(400);
        throw new SystemException('A recovery token is required', 400);
    }

    $tokenHash = hash('sha256', $normalized);

    $db->where('release_token_hash', $tokenHash);
    $db->where('release_status', 'release_approved');
    $row = $db->getOne('s_lis_backup_key_recovery');

    if (empty($row)) {
        http_response_code(403);
        throw new SystemException('Invalid recovery token', 403);
    }

    $labId = (int) $row['facility_id'];

    $expires = $row['release_token_expires'] ?? null;
    if ($expires === null || strtotime((string) $expires) < time()) {
        http_response_code(403);
        throw new SystemException('Recovery token has expired; ask the STS admin to approve again', 403);
    }

    $key = CryptoUtility::decrypt((string) $row['encrypted_key']);
    if (!hash_equals((string) $row['fingerprint'], hash('sha256', $key))) {
        http_response_code(500);
        throw new SystemException('Escrowed key failed its integrity check', 500);
    }

    // Single use: consume the token and stamp the release before returning the key.
    $db->where('id', (int) $row['id']);
    $db->update('s_lis_backup_key_recovery', [
        'release_status'        => 'released',
        'released_at'           => DateUtility::getCurrentDateTime(),
        'release_token_hash'    => null,
        'release_token_expires' => null,
    ]);

    $payload = [
        'status'      => 'success',
        'key'         => $key,
        'fingerprint' => (string) $row['fingerprint'],
        'keyVersion'  => (int) $row['key_version'],
    ];
} catch (Throwable $exc) {
    $statusCode = (int) ($exc->getCode() ?: 500);
    if ($statusCode < 400 || $statusCode > 599) {
        $statusCode = 500;
    }
    http_response_code($statusCode);
    $payload = ['status' => 'error', 'message' => $exc->getMessage()];
    LoggerUtility::logError($exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'requestUrl' => $requestUrl,
    ]);
} finally {
    $encodedPayload = JsonUtility::encodeUtf8Json($payload ?? []);
    // Never persist the released key in API tracking.
    $tracked = $payload;
    if (isset($tracked['key'])) {
        $tracked['key'] = '[redacted]';
    }
    $recordsCount = ($payload['status'] ?? '') === 'success' ? 1 : 0;
    $general->addApiTracking($transactionId, null, $recordsCount, 'backup-key-release', null, $requestUrl, $origJson, JsonUtility::encodeUtf8Json($tracked), 'json', $labId, null, null);
    echo ApiService::generateJsonResponse($encodedPayload, $request);
}
