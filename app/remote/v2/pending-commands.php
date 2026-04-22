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

    // 0a) Sweep expired rows for this lab so we never hand out commands that
    //     are past their deadline. Any non-terminal row whose expires_at has
    //     passed moves to 'expired'.
    $db->reset();
    $db->where('lab_id', (int) $labId);
    $db->where('expires_at IS NOT NULL');
    $db->where("expires_at <= '$now'");
    $db->where('status', $terminalStatuses, 'NOT IN');
    $db->update('s_lis_remote_commands', [
        'status' => 'expired',
        'completed_at' => $now,
    ]);

    // 0b) Stale-command sweep. If a row was picked up more than 2 hours ago
    //     but never reached a terminal or 'prepared' state, its handler has
    //     almost certainly crashed (OOM, segfault, runner restart mid-op).
    //     Flip to 'failed' so the next command in the queue can proceed and
    //     the UI stops showing a ghost in-flight badge forever. 'prepared'
    //     is excluded — it's the legitimate plateau state between prepare
    //     and apply, can sit for days.
    $db->reset();
    $db->where('lab_id', (int) $labId);
    $db->where('picked_at IS NOT NULL');
    $db->where("picked_at < DATE_SUB('$now', INTERVAL 2 HOUR)");
    $db->where('status', ['picked', 'running', 'preparing', 'applying'], 'IN');
    $db->update('s_lis_remote_commands', [
        'status' => 'failed',
        'completed_at' => $now,
        'last_error' => 'Stale: no status report received within 2 hours of pick-up',
    ]);

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

    // 4) Stash heartbeats in facility_attributes so the sync-status UI can
    //    surface "courier last seen X ago" and "runner last seen Y ago"
    //    per lab. Any value is allowed (null, ISO 8601) — display is
    //    best-effort.
    if (isset($data['heartbeats']) && is_array($data['heartbeats'])) {
        $courierHb = $data['heartbeats']['courier'] ?? null;
        $runnerHb = $data['heartbeats']['runner'] ?? null;
        $sql = "UPDATE facility_details
                SET facility_attributes = JSON_SET(
                    COALESCE(facility_attributes, '{}'),
                    '$.courierHeartbeat', ?,
                    '$.runnerHeartbeat', ?,
                    '$.lastPendingCommandsSync', ?
                )
                WHERE facility_id = ?";
        $db->rawQuery($sql, [$courierHb, $runnerHb, $now, (int) $labId]);
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
