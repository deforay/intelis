<?php

// Queue a remote command for a LIS instance to pick up on its next sync tick.
// Only STS-side; only admin users (access gated via the privileges table).

use App\Registries\AppRegistry;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (!$general->isSTSInstance()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Not an STS instance']);
    exit;
}

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Not authenticated']);
    exit;
}

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$post = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

$labId = isset($post['labId']) ? (int) $post['labId'] : 0;
$command = $post['command'] ?? '';

// Whitelist. This grows as handlers are added on the LIS side.
// Keep in sync with the runner/courier dispatch tables.
$commandWhitelist = [
    'resend-results',
    'resend-requests',
    'metadata-resync',
    'refresh-cache',
    'rotate-token',
];

if ($labId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Missing or invalid labId']);
    exit;
}

if (!in_array($command, $commandWhitelist, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Unknown command']);
    exit;
}

// Build the params blob based on the command. Validate per-command so the
// LIS-side handler can trust its input.
$params = [];

if ($command === 'resend-results' || $command === 'resend-requests') {
    $module = $post['module'] ?? null;
    $days = isset($post['days']) ? (int) $post['days'] : null;

    $validModules = ['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'cd4', 'generic-tests'];
    if (!empty($module) && !in_array($module, $validModules, true)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'Invalid module']);
        exit;
    }
    if ($days !== null && ($days < 1 || $days > 3650)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'Days must be between 1 and 3650']);
        exit;
    }

    if (!empty($module)) {
        $params['module'] = $module;
    }
    if ($days !== null && $days > 0) {
        $params['days'] = $days;
    }
}

// Refuse duplicate in-flight commands for the same lab + command combo.
$db->reset();
$db->where('lab_id', $labId);
$db->where('command', $command);
$db->where('status', ['pending', 'picked', 'running', 'preparing', 'prepared', 'applying'], 'IN');
$existing = $db->getValue('s_lis_remote_commands', 'command_id');

if (!empty($existing)) {
    http_response_code(409);
    echo json_encode([
        'status' => 'error',
        'error' => 'A matching command is already in flight for this lab',
        'commandId' => $existing,
    ]);
    exit;
}

$commandId = MiscUtility::generateULID(false);
$nonce = MiscUtility::generateULID(false);

$insertData = [
    'command_id' => $commandId,
    'lab_id' => $labId,
    'command' => $command,
    'params' => !empty($params) ? json_encode($params) : null,
    'status' => 'pending',
    'requested_by' => (int) $_SESSION['userId'],
    'requested_at' => date('Y-m-d H:i:s'),
    'nonce' => $nonce,
];

try {
    $db->insert('s_lis_remote_commands', $insertData);

    echo json_encode([
        'status' => 'success',
        'commandId' => $commandId,
        'command' => $command,
        'labId' => $labId,
    ]);
} catch (Throwable $e) {
    LoggerUtility::logError('Failed to queue LIS command', [
        'command' => $command,
        'labId' => $labId,
        'message' => $e->getMessage(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
    ]);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to queue command']);
}
