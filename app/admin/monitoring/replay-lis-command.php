<?php

// Re-queue a previously-run command with the same params/lab/dependencies.
// Creates a NEW row (new command_id, new nonce, status=pending) so we
// preserve the audit trail of the failed attempt. Only terminal rows are
// replayable — replaying an in-flight command would race with its runner.

use App\Registries\AppRegistry;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
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

// Replay needs both the history-view (to know the row exists) and the
// queue privilege (since it's effectively queueing a new command).
if (!_isAllowed('/admin/monitoring/queue-lis-command.php')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Not authorized to replay commands']);
    exit;
}

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$post = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

$sourceId = $post['commandId'] ?? '';
if (empty($sourceId) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $sourceId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid commandId']);
    exit;
}

$terminalStatuses = ['completed', 'failed', 'expired', 'cancelled'];

try {
    $db->reset();
    $db->where('command_id', $sourceId);
    $source = $db->getOne(
        's_lis_remote_commands',
        'command_id, lab_id, command, params, status, depends_on'
    );

    if (empty($source)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'error' => 'Source command not found']);
        exit;
    }

    if (!in_array($source['status'], $terminalStatuses, true)) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'error' => 'Can only replay terminal commands; current status is ' . $source['status'],
        ]);
        exit;
    }

    // Capability gate — replay is queueing under the hood, so use the same
    // contract as queue-lis-command. A lab whose courier has gone silent
    // shouldn't accumulate fresh pending rows it will never pick up.
    $labCaps = ContainerRegistry::get(LabCapabilityService::class);
    if (!$labCaps->supportsCommandPlane((int) $source['lab_id'])) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'error' => 'Lab does not support the remote command plane (no recent capability report).',
        ]);
        exit;
    }
    if (!$labCaps->supportsCommand((int) $source['lab_id'], (string) $source['command'])) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'error' => "Lab's courier does not support the '{$source['command']}' command.",
        ]);
        exit;
    }

    // upgrade-apply needs its dependsOn to still be a valid 'prepared' row.
    // If the prior prepared row has been cleaned up or the new replay
    // should reference a fresh prepare, admin should queue a new apply
    // via the normal modal instead. Refuse to guess.
    if ($source['command'] === 'upgrade-apply') {
        if (empty($source['depends_on'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Source upgrade-apply has no dependsOn — cannot replay']);
            exit;
        }
        $db->reset();
        $db->where('command_id', $source['depends_on']);
        $db->where('lab_id', $source['lab_id']);
        $db->where('status', 'prepared');
        $prep = $db->getValue('s_lis_remote_commands', 'command_id');
        if (empty($prep)) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'error' => 'Original upgrade-prepare is no longer in the prepared state. Queue a fresh upgrade-apply manually.',
            ]);
            exit;
        }
    }

    // Refuse if a matching command for this lab is already in-flight.
    $db->reset();
    $db->where('lab_id', (int) $source['lab_id']);
    $db->where('command', $source['command']);
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

    $newId = MiscUtility::generateULID(false);
    $newNonce = MiscUtility::generateULID(false);

    $db->insert('s_lis_remote_commands', [
        'command_id' => $newId,
        'lab_id' => (int) $source['lab_id'],
        'command' => $source['command'],
        'params' => $source['params'],
        'status' => 'pending',
        'requested_by' => (int) $_SESSION['userId'],
        'requested_at' => DateUtility::getCurrentDateTime(),
        'nonce' => $newNonce,
        'depends_on' => $source['depends_on'] ?: null,
    ]);

    echo json_encode([
        'status' => 'success',
        'commandId' => $newId,
        'replayedFrom' => $sourceId,
        'command' => $source['command'],
        'labId' => (int) $source['lab_id'],
    ]);
} catch (Throwable $e) {
    LoggerUtility::logError('Failed to replay LIS command', [
        'sourceId' => $sourceId,
        'message' => $e->getMessage(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
    ]);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to replay command']);
}
