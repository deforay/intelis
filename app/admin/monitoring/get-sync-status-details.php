<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$table = "form_vl";
$primaryKey = "vl_sample_id";

$testType = 'vl';
if (!empty($_POST['testType'])) {
    $testType = $_POST['testType'];
}

if (isset($testType) && $testType == 'vl') {
    $url = "/vl/requests/vl-requests.php";
    $table = "form_vl";
    $testName = 'Viral Load';
}
if (isset($testType) && $testType == 'eid') {
    $url = "/eid/requests/eid-requests.php";
    $table = "form_eid";
    $testName = 'EID';
}
if (isset($testType) && $testType == 'covid19') {
    $url = "/covid-19/requests/covid-19-requests.php";
    $table = "form_covid19";
    $testName = 'Covid-19';
}
if (isset($testType) && $testType == 'hepatitis') {
    $url = "/hepatitis/requests/hepatitis-requests.php";
    $table = "form_hepatitis";
    $testName = 'Hepatitis';
}
if (isset($testType) && $testType == 'tb') {
    $url = "/tb/requests/tb-requests.php";
    $table = "form_tb";
    $testName = 'TB';
}

if ($testType == 'generic-tests') {
    $testType = 'genericTests';
}

$sQuery = "SELECT f.facility_id,
            f.facility_name, GREATEST(
                    COALESCE(facility_attributes->>'$." . $testType . "RemoteResultsSync', 0),
                    COALESCE(facility_attributes->>'$." . $testType . "RemoteRequestsSync', 0)
                ) as latestSync,
                (f.facility_attributes->>'$." . $testType . "RemoteResultsSync') as lastResultsSync,
                (f.facility_attributes->>'$." . $testType . "RemoteRequestsSync') as lastRequestsSync, g_d_s.geo_name as province, g_d_d.geo_name as district
            FROM facility_details AS f
                LEFT JOIN geographical_divisions as g_d_s ON g_d_s.geo_id = f.facility_state_id
                LEFT JOIN geographical_divisions as g_d_d ON g_d_d.geo_id = f.facility_district_id ";
if (isset($_POST['testType']) && trim((string) $_POST['testType']) !== '' && isset($_POST['labId']) && trim((string) $_POST['labId']) !== '') {
    $sWhere[] = ' f.facility_id IN (SELECT DISTINCT facility_id from ' . $table . ' WHERE lab_id = ' . base64_decode((string) $_POST['labId']) . ') ';
}
if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
    $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['province']) && trim((string) $_POST['province']) !== '') {
    $sWhere[] = ' f.facility_state_id = "' . $_POST['province'] . '"';
}
if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
    $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}
if (!empty($sWhere)) {
    $sQuery = $sQuery . " WHERE " . implode(" AND ", $sWhere);
}
$sQuery .= " ORDER BY latestSync DESC, f.facility_name ASC";

$_SESSION['labSyncStatusDetails'] = $sQuery;

$rResult = $db->rawQuery($sQuery);
foreach ($rResult as $key => $aRow) { ?>
    <tr data-facilityId="<?= base64_encode((string) $aRow['facility_id']); ?>" data-labId="<?= htmlspecialchars((string) $_POST['labId']); ?>" data-url="<?php echo urlencode((string) $url); ?>">
        <td>
            <?= htmlspecialchars((string) $aRow['facility_name']); ?>
        </td>
        <td>
            <?= htmlspecialchars((string) $_POST['testType']); ?>
        </td>
        <td>
            <?= htmlspecialchars((string) $aRow['province']); ?>
        </td>
        <td>
            <?= htmlspecialchars((string) $aRow['district']); ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($aRow['lastRequestsSync'], true); ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($aRow['lastResultsSync'], true); ?>
        </td>
    </tr>
<?php }
