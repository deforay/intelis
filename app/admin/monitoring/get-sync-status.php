<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
$_COOKIE = _sanitizeInput($request->getCookieParams());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sampleReceivedfield = "sample_received_at_lab_datetime";

$sQuery = "SELECT f.facility_id, f.facility_name, tar.request_type, tar.requested_on, tar.test_type,
                GREATEST(
                    COALESCE(facility_attributes->>'$.lastHeartBeat', 0),
                    COALESCE(facility_attributes->>'$.lastResultsSync', 0),
                    COALESCE(facility_attributes->>'$.lastRequestsSync', 0),
                    COALESCE(tar.requested_on, 0)
                    ) as latest,
                (facility_attributes->>'$.version') as version,
                (facility_attributes->>'$.lastHeartBeat') as lastHeartBeat,
                (facility_attributes->>'$.lastResultsSync') as lastResultsSync,
                (facility_attributes->>'$.lastRequestsSync') as lastRequestsSync
            FROM `facility_details`as f
            LEFT JOIN track_api_requests as tar ON tar.facility_id = f.facility_id
            LEFT JOIN testing_labs as lab ON lab.facility_id = f.facility_id";


$sWhere[] = ' f.facility_type = 2 AND f.status = "active" ';
if (isset($_POST['testType']) && trim((string) $_POST['testType']) != '') {
    $sWhere[] = ' (tar.test_type like "' . $_POST['testType'] . '"  OR tar.test_type is null) ';
}

if (isset($_POST['labName']) && trim((string) $_POST['labName']) != '') {
    $sWhere[] = ' f.facility_id IN (' . $_POST['labName'] . ')';
}
if (isset($_POST['province']) && trim((string) $_POST['province']) != '') {
    $sWhere[] = ' f.facility_state_id = "' . $_POST['province'] . '"';
}
if (isset($_POST['district']) && trim((string) $_POST['district']) != '') {
    $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}

/* Implode all the where fields for filtering the data */
if (!empty($sWhere)) {
    $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere) . '
    GROUP BY f.facility_id
    ORDER BY latest DESC';
}
$_SESSION['labSyncStatus'] = $sQuery;

$today = new DateTimeImmutable();
$twoWeekExpiry = $today->sub(DateInterval::createFromDateString('2 weeks'));
$threeWeekExpiry = $today->sub(DateInterval::createFromDateString('4 weeks'));
$resultSet = $db->rawQuery($sQuery);
foreach ($resultSet as $key => $aRow) {
    $color = "red";
    $aRow['latest'] = $aRow['latest'] ?: $aRow['requested_on'];
    $latest = (!empty($aRow['latest'])) ? new DateTimeImmutable($aRow['latest']) : null;

    if (empty($latest)) {
        $color = "red";
    } elseif ($latest >= $twoWeekExpiry) {
        $color = "green";
    } elseif ($latest > $threeWeekExpiry && $latest < $twoWeekExpiry) {
        $color = "yellow";
    } elseif ($latest >= $threeWeekExpiry) {
        $color = "red";
    }

    /* Assign data table variables */ ?>
    <tr class="<?php echo $color; ?>" data-facilityId="<?= base64_encode((string) $aRow['facility_id']); ?>">
        <td>
            <?= $aRow['facility_name']; ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($aRow['latest'], true); ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($aRow['lastResultsSync'], true); ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($aRow['lastRequestsSync'], true); ?>
        </td>
        <td>
            <?= $aRow['version'] ?? '-'; ?>
        </td>
    </tr>
<?php } ?>
