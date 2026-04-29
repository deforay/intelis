<?php

// Queue a remote command for a LIS instance to pick up on its next sync tick.
// Only STS-side; only admin users (access gated via the privileges table).

use App\Registries\AppRegistry;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use App\Services\LabCapabilityService;

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

// AJAX requests bypass the AclMiddleware — gate here explicitly.
if (!_isAllowed('/admin/monitoring/queue-lis-command.php')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Not authorized to queue commands']);
    exit;
}

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$post = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

$labId = isset($post['labId']) ? (int) $post['labId'] : 0;
$command = $post['command'] ?? '';

// Whitelist. Keep in sync with:
//   - the LIS courier's $inProcessHandlers map (non-root)
//   - scripts/intelis-runner.sh dispatch case (root commands)
$commandWhitelist = [
    // Self-test: no-op, just echoes a timestamp. Useful for verifying the
    // whole queue-courier-handler-status pipeline without side effects.
    'ping',
    // Non-root, run by the LIS courier in-process:
    'resend-results',
    'resend-requests',
    'metadata-resync',
    'refresh-cache',
    'rotate-token',
    // Root, run by the privileged runner:
    'refresh-perms',
    'restart-apache',
    'upgrade',
    'upgrade-prepare',
    'upgrade-apply',
];
$rootCommands = ['refresh-perms', 'restart-apache', 'upgrade', 'upgrade-prepare', 'upgrade-apply'];

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

// Capability gate: refuse to queue if the lab's courier hasn't reported it
// can handle this verb. Defense in depth — the UI already hides the action,
// but a stale page or a hand-crafted POST should not bypass it.
$labCaps = ContainerRegistry::get(LabCapabilityService::class);
if (!$labCaps->supportsCommandPlane($labId)) {
    http_response_code(409);
    echo json_encode([
        'status' => 'error',
        'error' => 'Lab does not support the remote command plane (no recent capability report).',
    ]);
    exit;
}
if (!$labCaps->supportsCommand($labId, $command)) {
    http_response_code(409);
    echo json_encode([
        'status' => 'error',
        'error' => "Lab's courier does not support the '{$command}' command.",
    ]);
    exit;
}

// Build the params blob based on the command. Validate per-command so the
// LIS-side handler can trust its input.
$params = [];
$dependsOn = null;
$notBefore = null;

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

// upgrade-apply requires a dependsOn that points at a currently-prepared
// row for THIS lab. Enforce that server-side so the operator can't apply
// a stale or foreign staging dir by tampering with the client payload.
if ($command === 'upgrade-apply') {
    $dependsOn = $post['dependsOn'] ?? null;
    if (empty($dependsOn) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $dependsOn)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'upgrade-apply requires a valid dependsOn commandId']);
        exit;
    }
    $db->reset();
    $db->where('command_id', $dependsOn);
    $db->where('lab_id', $labId);
    $db->where('command', 'upgrade-prepare');
    $db->where('status', 'prepared');
    $prepRow = $db->getOne('s_lis_remote_commands', 'command_id');
    if (empty($prepRow)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'No matching prepared upgrade for this lab']);
        exit;
    }
}

// Optional maintenance flag — only meaningful for commands that invoke the
// upgrade script. Drives the -M / --maintenance flag on intelis-update so the
// lab shows a 503 "upgrade in progress" page during the apply window.
// Default is silent (omit from params) matching the upgrade.sh default.
if (in_array($command, ['upgrade', 'upgrade-apply'], true) && !empty($post['maintenance'])) {
    $params['maintenance'] = true;
}

// Optional notBefore — earliest time the runner may pick up this command.
// Accepts ISO 8601 / datetime-local format. Never accept past values.
if (!empty($post['notBefore'])) {
    $raw = str_replace('T', ' ', (string) $post['notBefore']);
    $ts = strtotime($raw);
    if ($ts === false) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'Invalid notBefore datetime']);
        exit;
    }
    if ($ts < time()) {
        // Ignore past notBefore rather than erroring — treat as "now".
        $notBefore = null;
    } else {
        $notBefore = date('Y-m-d H:i:s', $ts);
    }
}

// Refuse duplicate in-flight commands for the same lab + command combo.
// 'prepared' is intentionally excluded — it's a plateau state waiting for
// an apply, and operators may legitimately prepare multiple times (e.g.
// to stage a newer version on top of an existing stage).
$db->reset();
$db->where('lab_id', $labId);
$db->where('command', $command);
$db->where('status', ['pending', 'picked', 'running', 'preparing', 'applying'], 'IN');
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
    'depends_on' => $dependsOn,
    'not_before' => $notBefore,
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
