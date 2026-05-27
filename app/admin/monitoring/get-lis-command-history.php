<?php

// AJAX data source for /admin/monitoring/lis-command-history.php
//
// Two modes:
//   - default: renders the latest matching rows as <tr> HTML
//   - detailFor=<commandId>: renders a detail pane (full result JSON) for
//     a single command

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (!$general->isSTSInstance()) {
    http_response_code(403);
    echo '<p class="text-danger">Not an STS instance.</p>';
    exit;
}

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo '<p class="text-danger">Not authenticated.</p>';
    exit;
}

// AJAX isn't ACL-checked by middleware — gate explicitly.
if (!_isAllowed('/admin/monitoring/lis-command-history.php')) {
    http_response_code(403);
    echo '<p class="text-danger">Not authorized.</p>';
    exit;
}

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$method = $request->getMethod();
if ($method === 'POST') {
    $post = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);
} else {
    $post = _sanitizeInput($request->getQueryParams(), nullifyEmptyStrings: true);
}

$canCancel = _isAllowed('/admin/monitoring/cancel-lis-command.php');
$canQueue = _isAllowed('/admin/monitoring/queue-lis-command.php');
$terminalStatuses = ['completed', 'failed', 'expired', 'cancelled'];

$isDetailMode = !empty($post['detailFor'])
    && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $post['detailFor']);

// Buffer + try/catch so a failure (a class skew after a partial/failed upgrade,
// a malformed row, a DB hiccup) degrades to a logged error message instead of a
// raw 500. Any partial output already echoed is discarded before the error.
ob_start();
try {

    // Single-command detail mode.
    if ($isDetailMode) {
    $db->reset();
    $db->where('command_id', $post['detailFor']);
    $row = $db->getOne(
        's_lis_remote_commands',
        'command_id, lab_id, command, params, status, requested_by, requested_at, picked_at, completed_at, not_before, expires_at, depends_on, result, last_error, nonce'
    );
    if (empty($row)) {
        echo '<p class="text-danger">Command not found.</p>';
        exit;
    }

    $labName = $db->where('facility_id', $row['lab_id'])->getValue('facility_details', 'facility_name');
    $userName = !empty($row['requested_by'])
        ? $db->where('user_id', $row['requested_by'])->getValue('user_details', 'user_name')
        : null;

    $resultPretty = '';
    if (!empty($row['result'])) {
        $decoded = json_decode((string) $row['result'], true);
        if (is_array($decoded)) {
            $resultPretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $resultPretty = (string) $row['result'];
        }
    }
    $paramsPretty = '';
    if (!empty($row['params'])) {
        $decoded = json_decode((string) $row['params'], true);
        if (is_array($decoded)) {
            $paramsPretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $paramsPretty = (string) $row['params'];
        }
    }
    ?>
    <dl class="dl-horizontal">
        <dt><?= _translate('Command ID'); ?></dt><dd><code><?= htmlspecialchars((string) $row['command_id']); ?></code></dd>
        <dt><?= _translate('Command'); ?></dt><dd><?= htmlspecialchars((string) $row['command']); ?></dd>
        <dt><?= _translate('Lab'); ?></dt><dd><?= htmlspecialchars((string) ($labName ?? 'Unknown')); ?> (<?= (int) $row['lab_id']; ?>)</dd>
        <dt><?= _translate('Status'); ?></dt><dd><span class="cmd-status-<?= htmlspecialchars((string) $row['status']); ?>"><?= htmlspecialchars((string) $row['status']); ?></span></dd>
        <dt><?= _translate('Requested at'); ?></dt><dd><?= htmlspecialchars((string) $row['requested_at']); ?></dd>
        <dt><?= _translate('Requested by'); ?></dt><dd><?= htmlspecialchars((string) ($userName ?? '—')); ?></dd>
        <?php if (!empty($row['not_before'])) { ?>
            <dt><?= _translate('Not before'); ?></dt><dd><?= htmlspecialchars((string) $row['not_before']); ?></dd>
        <?php } ?>
        <?php if (!empty($row['picked_at'])) { ?>
            <dt><?= _translate('Picked at'); ?></dt><dd><?= htmlspecialchars((string) $row['picked_at']); ?></dd>
        <?php } ?>
        <?php if (!empty($row['completed_at'])) { ?>
            <dt><?= _translate('Completed at'); ?></dt><dd><?= htmlspecialchars((string) $row['completed_at']); ?></dd>
        <?php } ?>
        <?php if (!empty($row['depends_on'])) { ?>
            <dt><?= _translate('Depends on'); ?></dt><dd><code><?= htmlspecialchars((string) $row['depends_on']); ?></code></dd>
        <?php } ?>
    </dl>
    <?php if ($paramsPretty !== '') { ?>
        <h5><?= _translate('Params'); ?></h5>
        <pre class="result-tail-box"><?= htmlspecialchars($paramsPretty); ?></pre>
    <?php } ?>
    <?php if ($resultPretty !== '') { ?>
        <h5><?= _translate('Result'); ?></h5>
        <pre class="result-tail-box"><?= htmlspecialchars($resultPretty); ?></pre>
    <?php } ?>
    <?php if (!empty($row['last_error'])) { ?>
        <h5><?= _translate('Last error'); ?></h5>
        <pre class="result-tail-box" style="background:#fdecea;"><?= htmlspecialchars((string) $row['last_error']); ?></pre>
    <?php }
    exit;
}

// Default: list mode. Build a dynamic WHERE.
$conditions = [];
$params = [];

if (!empty($post['labId'])) {
    $conditions[] = 'c.lab_id = ?';
    $params[] = (int) $post['labId'];
}
if (!empty($post['command'])) {
    $conditions[] = 'c.command = ?';
    $params[] = (string) $post['command'];
}
if (!empty($post['status'])) {
    $conditions[] = 'c.status = ?';
    $params[] = (string) $post['status'];
}
if (!empty($post['dateFrom'])) {
    $conditions[] = 'DATE(c.requested_at) >= ?';
    $params[] = (string) $post['dateFrom'];
}
if (!empty($post['dateTo'])) {
    $conditions[] = 'DATE(c.requested_at) <= ?';
    $params[] = (string) $post['dateTo'];
}

$whereClause = $conditions ? (' WHERE ' . implode(' AND ', $conditions)) : '';

$query = "SELECT c.command_id, c.lab_id, c.command, c.params, c.status,
                 c.requested_by, c.requested_at, c.picked_at, c.completed_at,
                 c.not_before, c.expires_at, c.depends_on,
                 f.facility_name, u.user_name
          FROM s_lis_remote_commands c
          LEFT JOIN facility_details f ON f.facility_id = c.lab_id
          LEFT JOIN user_details u ON u.user_id = c.requested_by
          $whereClause
          ORDER BY c.requested_at DESC
          LIMIT 200";

$rows = $db->rawQuery($query, $params);

if (empty($rows)) {
    echo '<tr><td colspan="8" class="text-center text-muted">' . _translate('No commands match these filters.') . '</td></tr>';
    exit;
}

foreach ($rows as $r) {
    $paramsShort = '';
    if (!empty($r['params'])) {
        $decoded = json_decode((string) $r['params'], true);
        if (is_array($decoded)) {
            $bits = [];
            foreach ($decoded as $k => $v) {
                $bits[] = htmlspecialchars((string) $k) . '=' . htmlspecialchars(is_scalar($v) ? (string) $v : json_encode($v));
            }
            $paramsShort = implode(' ', $bits);
        }
    }

    $statusClass = 'cmd-status-' . preg_replace('/[^a-z\-]/', '', $r['status']);
    $isCancellable = ($r['status'] === 'pending' && $canCancel);
    ?>
    <tr>
        <td><small><?= htmlspecialchars((string) $r['requested_at']); ?></small></td>
        <td>
            <?= htmlspecialchars((string) ($r['facility_name'] ?? 'Unknown')); ?>
            <br><small class="text-muted">id: <?= (int) $r['lab_id']; ?></small>
        </td>
        <td><code><?= htmlspecialchars((string) $r['command']); ?></code></td>
        <td><small><?= $paramsShort ?: '—'; ?></small></td>
        <td><span class="<?= $statusClass; ?>"><?= htmlspecialchars((string) $r['status']); ?></span></td>
        <td><?= htmlspecialchars((string) ($r['user_name'] ?? '—')); ?></td>
        <td>
            <?php if (!empty($r['completed_at'])) { ?>
                <small><?= htmlspecialchars((string) $r['completed_at']); ?></small>
            <?php } else { ?>
                <small class="text-muted">—</small>
            <?php } ?>
        </td>
        <td>
            <a href="#" class="details-link" data-command-id="<?= htmlspecialchars((string) $r['command_id']); ?>">
                <i class="fa fa-search-plus"></i> <?= _translate('Details'); ?>
            </a>
            <?php if ($isCancellable) { ?>
                &nbsp;
                <a href="#" class="cancel-link text-danger" data-command-id="<?= htmlspecialchars((string) $r['command_id']); ?>">
                    <i class="fa fa-times-circle"></i> <?= _translate('Cancel'); ?>
                </a>
            <?php } ?>
            <?php if ($canQueue && in_array($r['status'], $terminalStatuses, true)) { ?>
                &nbsp;
                <a href="#" class="replay-link" data-command-id="<?= htmlspecialchars((string) $r['command_id']); ?>"
                   title="<?= _translate('Re-queue this command with the same params'); ?>">
                    <i class="fa fa-repeat"></i> <?= _translate('Replay'); ?>
                </a>
            <?php } ?>
        </td>
    </tr>
    <?php
}

    echo ob_get_clean();
} catch (Throwable $e) {
    ob_end_clean();
    LoggerUtility::logError('Failed to render LIS command history', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    if ($isDetailMode) {
        echo '<p class="text-danger">' . _translate('Failed to load command details. Please try again.') . '</p>';
    } else {
        echo '<tr><td colspan="8" class="text-center text-danger">'
            . '<i class="fa fa-exclamation-triangle"></i> '
            . _translate('Failed to load command history. Please try again.')
            . '</td></tr>';
    }
}
