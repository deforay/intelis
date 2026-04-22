<?php

// Cancel a queued LIS command. Only rows in status='pending' can be
// cancelled — once the courier has picked up a row, the LIS runner owns
// it and cancellation would be racy. Callers get a clear 409 in that case.

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
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

// AJAX requests bypass the AclMiddleware — gate here explicitly.
if (!_isAllowed('/admin/monitoring/cancel-lis-command.php')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Not authorized to cancel commands']);
    exit;
}

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$post = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

$commandId = $post['commandId'] ?? '';
if (empty($commandId) || !preg_match('/^[A-Z0-9]{26}$/', (string) $commandId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid commandId']);
    exit;
}

try {
    $db->reset();
    $db->where('command_id', $commandId);
    $row = $db->getOne('s_lis_remote_commands', 'command_id, lab_id, command, status');

    if (empty($row)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'error' => 'Command not found']);
        exit;
    }

    if ($row['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'error' => 'Only pending commands can be cancelled; current status is ' . $row['status'],
            'currentStatus' => $row['status'],
        ]);
        exit;
    }

    $db->reset();
    $db->where('command_id', $commandId);
    $db->where('status', 'pending');
    $db->update('s_lis_remote_commands', [
        'status' => 'cancelled',
        'completed_at' => DateUtility::getCurrentDateTime(),
        'last_error' => 'Cancelled by user ' . (int) $_SESSION['userId'],
    ]);

    echo json_encode([
        'status' => 'success',
        'commandId' => $commandId,
        'previousStatus' => 'pending',
    ]);
} catch (Throwable $e) {
    LoggerUtility::logError('Failed to cancel LIS command', [
        'commandId' => $commandId,
        'message' => $e->getMessage(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
    ]);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to cancel command']);
}
