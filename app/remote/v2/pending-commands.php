<?php

// /remote/v2/pending-commands.php
// Remote command queue endpoint. LIS posts labId + statusUpdates for any
// commands it has just processed, and gets back the next batch of pending
// commands for that lab. One round-trip per sync tick.

use App\Services\ApiService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\STS\TokensService;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);

$payload = ['status' => 'error', 'error' => 'Unknown'];

try {
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');

    $data = $apiService->getJsonFromRequest($request, true);
    $apiRequestId = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $authToken = ApiService::extractBearerToken($request);

    $labId = $data['labId'] ?? null;
    if (empty($labId)) {
        throw new SystemException('Lab ID is missing in the request', 400);
    }

    $token = $stsTokensService->validateToken($authToken, $labId);
    if (!$token) {
        throw new SystemException('Unauthorized Access', 401);
    }

    $now = DateUtility::getCurrentDateTime();
    $terminalStatuses = ['completed', 'failed', 'expired', 'cancelled'];

    // 1) Apply incoming status updates (only for rows belonging to this lab).
    $statusUpdates = is_array($data['statusUpdates'] ?? null) ? $data['statusUpdates'] : [];
    $ackIds = [];
    foreach ($statusUpdates as $update) {
        $commandId = $update['commandId'] ?? null;
        $status = $update['status'] ?? null;
        $nonce = $update['nonce'] ?? null;

        if (empty($commandId) || empty($status)) {
            continue;
        }

        $validStatuses = [
            'picked','running','preparing','prepared','applying',
            'completed','failed','expired','cancelled'
        ];
        if (!in_array($status, $validStatuses, true)) {
            continue;
        }

        $updateData = ['status' => $status];
        if (!empty($update['result'])) {
            $updateData['result'] = is_string($update['result'])
                ? $update['result']
                : json_encode($update['result']);
        }
        if (!empty($update['lastError'])) {
            $updateData['last_error'] = mb_substr((string) $update['lastError'], 0, 4000);
        }
        if (in_array($status, $terminalStatuses, true)) {
            $updateData['completed_at'] = $now;
        }
        if ($status === 'picked' && empty($updateData['picked_at'])) {
            $updateData['picked_at'] = $now;
        }

        $db->reset();
        $db->where('command_id', $commandId);
        $db->where('lab_id', (int) $labId);
        if (!empty($nonce)) {
            $db->where('nonce', $nonce);
        }
        // Don't clobber already-terminal rows.
        $db->where('status', $terminalStatuses, 'NOT IN');
        $db->update('s_lis_remote_commands', $updateData);
        $ackIds[] = $commandId;
    }

    // 2) Fetch next batch of pending commands for this lab.
    $db->reset();
    $db->where('lab_id', (int) $labId);
    $db->where('status', 'pending');
    $db->where("(not_before IS NULL OR not_before <= '$now')");
    $db->where("(expires_at IS NULL OR expires_at > '$now')");
    // Dependency gate: if depends_on is set, the referenced command must exist,
    // belong to the same lab, and be in a prepared/completed state.
    $db->where(
        "(depends_on IS NULL OR EXISTS (
            SELECT 1 FROM s_lis_remote_commands d
            WHERE d.command_id = s_lis_remote_commands.depends_on
              AND d.lab_id = s_lis_remote_commands.lab_id
              AND d.status IN ('prepared','completed')
        ))"
    );
    $db->orderBy('requested_at', 'ASC');
    $db->pageLimit = 10;
    $rows = $db->get('s_lis_remote_commands', [1, 10],
        'command_id, command, params, nonce, not_before, expires_at, depends_on');

    // 3) Mark them picked.
    $commands = [];
    if (!empty($rows)) {
        $pickedIds = [];
        foreach ($rows as $row) {
            $pickedIds[] = $row['command_id'];
            $commands[] = [
                'commandId' => $row['command_id'],
                'command' => $row['command'],
                'params' => !empty($row['params']) ? json_decode((string) $row['params'], true) : new stdClass(),
                'nonce' => $row['nonce'],
                'notBefore' => $row['not_before'],
                'expiresAt' => $row['expires_at'],
                'dependsOn' => $row['depends_on'],
            ];
        }
        $db->reset();
        $db->where('command_id', $pickedIds, 'IN');
        $db->update('s_lis_remote_commands', [
            'status' => 'picked',
            'picked_at' => $now,
        ]);
    }

    $payload = [
        'status' => 'success',
        'commands' => $commands,
        'acknowledged' => $ackIds,
        'serverTime' => $now,
    ];

    $general->addApiTracking(
        $transactionId,
        'intelis-system',
        count($commands),
        'pending-commands',
        'system',
        $_SERVER['REQUEST_URI'] ?? '',
        JsonUtility::encodeUtf8Json($data),
        JsonUtility::encodeUtf8Json($payload),
        'json',
        $labId,
        null,
        $authToken
    );
} catch (SystemException $e) {
    http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
    $payload = ['status' => 'error', 'error' => $e->getMessage()];
    LoggerUtility::logError('pending-commands endpoint: ' . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    $payload = ['status' => 'error', 'error' => 'Server error'];
    LoggerUtility::logError('pending-commands endpoint: unexpected error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
    ]);
}

echo ApiService::generateJsonResponse($payload, $request ?? null);
