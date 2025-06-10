<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

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

if (empty($resultSet)) {
    echo '<tr><td colspan="5" class="dataTables_empty">' . _translate("No data available") . '</td></tr>';
} else {
    foreach ($resultSet as $aRow) {
        // Determine sync status color
        $latestSync = (int)$aRow['latest_timestamp'];
        if ($latestSync > $twoWeeksAgo) {
            $color = 'green';
            $statusText = 'Active';
        } elseif ($latestSync > $fourWeeksAgo) {
            $color = 'yellow';
            $statusText = 'Warning';
        } else {
            $color = 'red';
            $statusText = 'Critical';
        }

        // Calculate days since last sync for better user understanding
        $daysSinceSync = $latestSync ? floor((time() - $latestSync) / 86400) : null;
        ?>
        <tr class="<?php echo $color; ?>" data-facilityId="<?= base64_encode((string) $aRow['facility_id']); ?>">
            <td>
                <?= htmlspecialchars($aRow['facility_name']); ?>
                <br><small class="text-muted">
                    <span class="sync-indicator <?= $color ?>-indicator"></span>
                    <?= $statusText ?>
                    <?php if ($daysSinceSync !== null): ?>
                        (<?= $daysSinceSync ?> days ago)
                    <?php endif; ?>
                </small>
            </td>
            <td class="text-center">
                <?= $latestSync ? DateUtility::humanReadableDateFormat(date('Y-m-d H:i:s', $latestSync), true) : '-'; ?>
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
        </tr>
        <?php
    }
}
