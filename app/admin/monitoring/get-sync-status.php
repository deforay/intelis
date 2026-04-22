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
$colspan = $isSTS ? 6 : 5;

// Build parameterized query for better performance and security
$query = "SELECT
    f.facility_id,
    f.facility_name,
    f.facility_attributes->>'$.version' as version,
    f.facility_attributes->>'$.lastHeartBeat' as lastHeartBeat,
    f.facility_attributes->>'$.lastResultsSync' as lastResultsSync,
    f.facility_attributes->>'$.lastRequestsSync' as lastRequestsSync,
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
$pendingCommandsByLab = [];
if ($isSTS) {
    $pendingCommands = $db->rawQuery(
        "SELECT lab_id, command, status, requested_at
         FROM s_lis_remote_commands
         WHERE status IN ('pending','picked','running','preparing','prepared','applying')
         ORDER BY requested_at DESC"
    );
    foreach ($pendingCommands ?: [] as $row) {
        $pendingCommandsByLab[$row['lab_id']][] = $row;
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
                <?= htmlspecialchars($aRow['version'] ?? '-'); ?>
            </td>
            <?php if ($isSTS) {
                $labPending = $pendingCommandsByLab[$aRow['facility_id']] ?? []; ?>
                <td class="text-center no-row-click">
                    <button type="button" class="btn btn-sm btn-primary row-action queue-command-btn"
                        data-lab-id="<?= (int) $aRow['facility_id']; ?>"
                        data-lab-name="<?= htmlspecialchars((string) $aRow['facility_name'], ENT_QUOTES); ?>"
                        title="<?= _translate('Queue a command for this lab'); ?>">
                        <i class="fa fa-paper-plane"></i>
                        <?= _translate('Queue'); ?>
                    </button>
                    <?php if (!empty($labPending)) { ?>
                        <div style="margin-top: 4px;">
                            <?php foreach ($labPending as $pc) { ?>
                                <span class="label label-warning" style="display:inline-block; margin-top:2px;"
                                    title="<?= htmlspecialchars((string) $pc['requested_at']); ?>">
                                    <?= htmlspecialchars($pc['command']); ?>: <?= htmlspecialchars($pc['status']); ?>
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
