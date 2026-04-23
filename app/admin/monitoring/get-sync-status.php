<?php

// get-sync-status.php

use Carbon\Carbon;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$isSTS = $general->isSTSInstance();
$canQueue = $isSTS && _isAllowed('/admin/monitoring/queue-lis-command.php');
$canCancel = $isSTS && _isAllowed('/admin/monitoring/cancel-lis-command.php');
$showActions = $isSTS && ($canQueue || $canCancel);
$colspan = $showActions ? 6 : 5;

// Build parameterized query for better performance and security
$query = "SELECT
    f.facility_id,
    f.facility_name,
    f.facility_attributes->>'$.version' as version,
    f.facility_attributes->>'$.commitSha' as commitSha,
    f.facility_attributes->>'$.lastHeartBeat' as lastHeartBeat,
    f.facility_attributes->>'$.lastResultsSync' as lastResultsSync,
    f.facility_attributes->>'$.lastRequestsSync' as lastRequestsSync,
    f.facility_attributes->>'$.courierHeartbeat' as courierHeartbeat,
    f.facility_attributes->>'$.runnerHeartbeat' as runnerHeartbeat,
    tar.last_requested_on,
    GREATEST(
        COALESCE(UNIX_TIMESTAMP(STR_TO_DATE(f.facility_attributes->>'$.lastHeartBeat', '%Y-%m-%d %H:%i:%s')), 0),
        COALESCE(UNIX_TIMESTAMP(STR_TO_DATE(f.facility_attributes->>'$.lastResultsSync', '%Y-%m-%d %H:%i:%s')), 0),
        COALESCE(UNIX_TIMESTAMP(STR_TO_DATE(f.facility_attributes->>'$.lastRequestsSync', '%Y-%m-%d %H:%i:%s')), 0),
        COALESCE(UNIX_TIMESTAMP(tar.last_requested_on), 0)
    ) as latest_timestamp
FROM facility_details f
LEFT JOIN (
    SELECT facility_id, MAX(requested_on) as last_requested_on
    FROM track_api_requests
    GROUP BY facility_id
) tar ON tar.facility_id = f.facility_id
WHERE f.facility_type = 2
    AND f.status = 'active'";

$params = [];

// Add filters with parameterized queries
if (!empty($_POST['labName'])) {
    $query .= " AND f.facility_id = ?";
    $params[] = $_POST['labName'];
}
if (!empty($_POST['province'])) {
    $query .= " AND f.facility_state_id = ?";
    $params[] = $_POST['province'];
}
if (!empty($_POST['district'])) {
    $query .= " AND f.facility_district_id = ?";
    $params[] = $_POST['district'];
}

$query .= " ORDER BY latest_timestamp DESC";

// Store query for export functionality
$_SESSION['labSyncStatus'] = $query;
$_SESSION['labSyncStatusParams'] = $params;

$resultSet = $db->rawQueryGenerator($query, $params);

// Calculate thresholds once
$twoWeeksAgo = strtotime('-2 weeks');
$fourWeeksAgo = strtotime('-4 weeks');

// Pre-fetch pending/in-flight commands for the labs in this result set so we
// can badge the row and disable duplicate-queueing client-side.
// Prepared rows are tracked separately so the "Apply prepared upgrade"
// dropdown can show the operator what's ready to apply on each lab.
$pendingCommandsByLab = [];
$preparedByLab = [];
if ($showActions) {
    $inFlight = $db->rawQuery(
        "SELECT command_id, lab_id, command, status, result, requested_at
         FROM s_lis_remote_commands
         WHERE status IN ('pending','picked','running','preparing','prepared','applying')
         ORDER BY requested_at DESC"
    );
    // Tag each row with its command_id in the indexed array so we can show
    // a cancel 'x' on pending badges without adding another query path.
    foreach ($inFlight ?: [] as $row) {
        $pendingCommandsByLab[$row['lab_id']][] = $row;

        if ($row['status'] === 'prepared' && $row['command'] === 'upgrade-prepare') {
            $resultDecoded = [];
            if (!empty($row['result'])) {
                $resultDecoded = json_decode((string) $row['result'], true) ?: [];
            }
            $preparedByLab[$row['lab_id']][] = [
                'commandId' => $row['command_id'],
                'stagedVersion' => $resultDecoded['stagedVersion'] ?? 'unknown',
                'stagingDir' => $resultDecoded['stagingDir'] ?? '',
                'requestedAt' => $row['requested_at'],
            ];
        }
    }
}

if (empty($resultSet)) {
    echo '<tr><td colspan="' . $colspan . '" class="dataTables_empty">' . _translate("No data available") . '</td></tr>';
} else {
    foreach ($resultSet as $aRow) {
        // Determine sync status color
        $latestSync = (int) $aRow['latest_timestamp'];
        if ($latestSync > $twoWeeksAgo) {
            $color = 'green';
        } elseif ($latestSync > $fourWeeksAgo) {
            $color = 'yellow';
        } else {
            $color = 'red';
        }

        // Calculate days since last sync for better user understanding
        $daysSinceSync = null;
        if ($latestSync !== 0) {
            $latest = Carbon::createFromTimestamp($latestSync);
            $now = Carbon::now();
            $daysSinceSync = $latest->diffInDays($now, false); // false = return negative if future
            $daysSinceSync = max(0, (int) $daysSinceSync); // Clamp to 0 to avoid negative display
        }

        if ($daysSinceSync !== null) {
            $daysSinceText = $daysSinceSync === 0 ? 'Today' : "$daysSinceSync days ago";
        } else {
            $daysSinceText = 'Never';
        }


        ?>
        <tr class="<?php echo $color; ?>" data-facilityId="<?= base64_encode((string) $aRow['facility_id']); ?>">
            <td>
                <?= htmlspecialchars((string) $aRow['facility_name']); ?>
                <br><small class="text-muted">
                    <span class="sync-indicator <?= $color ?>-indicator"></span>
                    <?= $daysSinceText ?>
                </small>
            </td>
            <td class="text-center">
                <?= $latestSync !== 0 ? DateUtility::humanReadableDateFormat(date('Y-m-d H:i:s', $latestSync), true) : '-'; ?>
            </td>
            <td class="text-center">
                <?= DateUtility::humanReadableDateFormat($aRow['lastResultsSync'] ?? '', true) ?: '-'; ?>
            </td>
            <td class="text-center">
                <?= DateUtility::humanReadableDateFormat($aRow['lastRequestsSync'] ?? '', true) ?: '-'; ?>
            </td>
            <td class="text-center">
                <?php
                $_rowSha = $aRow['commitSha'] ?? null;
                if (is_string($_rowSha) && preg_match('/^[0-9a-f]{40}$/', $_rowSha)) {
                    $_rowShaShort = substr($_rowSha, 0, 7);
                } else {
                    $_rowSha = null;
                    $_rowShaShort = null;
                }
                ?>
                <?= htmlspecialchars($aRow['version'] ?? '-'); ?><?php if ($_rowShaShort): ?>
                    <small class="text-muted" style="cursor:help;"
                        title="Commit <?= htmlspecialchars($_rowSha, ENT_QUOTES, 'UTF-8'); ?>">(<?= htmlspecialchars($_rowShaShort, ENT_QUOTES, 'UTF-8'); ?>)</small>
                <?php endif; ?>
                <?php if ($showActions) {
                    // Heartbeat freshness — report on the two background loops
                    // that actually drive remote commands. Both are "eventually
                    // consistent" so > 10 min stale is genuinely suspicious.
                    $courierHb = $aRow['courierHeartbeat'] ?? null;
                    $runnerHb = $aRow['runnerHeartbeat'] ?? null;
                    $staleThresholdSec = 15 * 60;

                    $renderHb = static function (?string $iso, string $label) use ($staleThresholdSec): string {
                        if (empty($iso)) {
                            // Never reported — feature is either off or the lab is on an older version.
                            return '<span class="label label-default" style="font-weight:normal;" title="'
                                 . _translate('Not reporting') . ' (' . htmlspecialchars($label) . ')">' . htmlspecialchars($label) . ': —</span>';
                        }
                        $ts = strtotime($iso);
                        $age = time() - $ts;
                        $ageText = $age < 60 ? 'just now'
                                 : ($age < 3600 ? floor($age / 60) . 'm ago'
                                 : floor($age / 3600) . 'h ago');
                        $cls = $age > $staleThresholdSec ? 'label-danger' : 'label-success';
                        return '<span class="label ' . $cls . '" style="font-weight:normal;" title="' . htmlspecialchars($iso) . '">'
                             . htmlspecialchars($label) . ': ' . htmlspecialchars($ageText) . '</span>';
                    };

                    // Show heartbeats only when at least one has ever reported,
                    // so legacy (not-yet-upgraded) labs don't show noise.
                    if (!empty($courierHb) || !empty($runnerHb)) { ?>
                        <br>
                        <small style="display:inline-block; margin-top:3px;">
                            <?= $renderHb($courierHb, _translate('courier')); ?>
                            <?= $renderHb($runnerHb, _translate('runner')); ?>
                        </small>
                    <?php }
                } ?>
            </td>
            <?php if ($showActions) {
                $labPending = $pendingCommandsByLab[$aRow['facility_id']] ?? [];
                $labPrepared = $preparedByLab[$aRow['facility_id']] ?? []; ?>
                <td class="text-center no-row-click">
                    <?php if ($canQueue) { ?>
                        <button type="button" class="btn btn-sm btn-primary row-action queue-command-btn"
                            data-lab-id="<?= (int) $aRow['facility_id']; ?>"
                            data-lab-name="<?= htmlspecialchars((string) $aRow['facility_name'], ENT_QUOTES); ?>"
                            data-prepared='<?= htmlspecialchars(json_encode($labPrepared), ENT_QUOTES); ?>'
                            title="<?= _translate('Queue a command for this lab'); ?>">
                            <i class="fa fa-paper-plane"></i>
                            <?= _translate('Queue'); ?>
                        </button>
                    <?php } ?>
                    <?php if (!empty($labPrepared)) { ?>
                        <div style="margin-top: 4px;">
                            <?php foreach ($labPrepared as $pr) { ?>
                                <span class="label label-info" style="display:inline-block; margin-top:2px;"
                                    title="<?= htmlspecialchars($pr['requestedAt']); ?>">
                                    <i class="fa fa-cube"></i>
                                    <?= _translate('Staged'); ?>: <?= htmlspecialchars($pr['stagedVersion']); ?>
                                </span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if (!empty($labPending)) { ?>
                        <div style="margin-top: 4px;">
                            <?php foreach ($labPending as $pc) {
                                if ($pc['status'] === 'prepared' && $pc['command'] === 'upgrade-prepare') {
                                    continue; // Already shown as "Staged:" above
                                }
                                $cancellable = ($pc['status'] === 'pending' && $canCancel); ?>
                                <span class="label label-warning row-action" style="display:inline-block; margin-top:2px;"
                                    title="<?= htmlspecialchars((string) $pc['requested_at']); ?>">
                                    <?= htmlspecialchars($pc['command']); ?>: <?= htmlspecialchars($pc['status']); ?>
                                    <?php if ($cancellable) { ?>
                                        <a href="#" class="cancel-command-link no-row-click"
                                           style="color: white; margin-left: 6px; font-weight: bold; text-decoration: none;"
                                           data-command-id="<?= htmlspecialchars((string) $pc['command_id']); ?>"
                                           data-command="<?= htmlspecialchars((string) $pc['command']); ?>"
                                           title="<?= _translate('Cancel this pending command'); ?>">&times;</a>
                                    <?php } ?>
                                </span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </td>
            <?php } ?>
        </tr>
        <?php
    }
}
