<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
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

$subQuery = "SELECT f.facility_id,
                f.facility_name,
                tar.requested_on,
                (facility_attributes->>'$.version') as `version`,
                (facility_attributes->>'$.lastHeartBeat') as `lastHeartBeat`,
                (facility_attributes->>'$.lastResultsSync') as `lastResultsSync`,
                (facility_attributes->>'$.lastRequestsSync') as `lastRequestsSync`,
                GREATEST(
                    COALESCE(facility_attributes->>'$.lastHeartBeat', 0),
                    COALESCE(facility_attributes->>'$.lastResultsSync', 0),
                    COALESCE(facility_attributes->>'$.lastRequestsSync', 0),
                    COALESCE(tar.requested_on, 0)
                ) as `latest`
            FROM `facility_details`as f
            LEFT JOIN track_api_requests as tar ON tar.facility_id = f.facility_id
            LEFT JOIN testing_labs as lab ON lab.facility_id = f.facility_id";


$sWhere[] = ' f.facility_type = 2 AND f.status = "active" ';

if (!empty($_POST['labName'])) {
    $sWhere[] = ' f.facility_id IN (' . $_POST['labName'] . ')';
}
if (!empty($_POST['province'])) {
    $sWhere[] = ' f.facility_state_id = "' . $_POST['province'] . '"';
}
if (!empty($_POST['district'])) {
    $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}

/* Implode all the where fields for filtering the data */
if (!empty($sWhere)) {
    $subQuery = $subQuery . ' WHERE ' . implode(" AND ", $sWhere) . '
    GROUP BY f.facility_id
    ORDER BY latest DESC';
}

$mainQuery = "SELECT main_query.*,
                CASE
                    WHEN latest > DATE_SUB(NOW(), INTERVAL 2 WEEK) THEN 'green'
                    WHEN latest <= DATE_SUB(NOW(), INTERVAL 4 WEEK) THEN 'red'
                    WHEN latest <= DATE_SUB(NOW(), INTERVAL 2 WEEK) THEN 'yellow'
                    ELSE 'red'
                END AS color
                FROM ($subQuery) as main_query";

$_SESSION['labSyncStatus'] = $mainQuery;

$resultSet = $db->rawQueryGenerator($mainQuery);

foreach ($resultSet as $aRow) {
    $color = $aRow['color'];
?>
    <tr class="<?php echo $color; ?>" data-facilityId="<?= base64_encode((string) $aRow['facility_id']); ?>">
        <td>
            <?= $aRow['facility_name']; ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($aRow['latest'] ?? '', true); ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($aRow['lastResultsSync'] ?? '', true); ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($aRow['lastRequestsSync'] ?? '', true); ?>
        </td>
        <td>
            <?= $aRow['version'] ?? '-'; ?>
        </td>
    </tr>
<?php } ?>
