<?php

use App\Services\CommonService;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new CommonService();
$table = "form_vl";
$primaryKey = "vl_sample_id";

$testType = 'vl';
$sampleReceivedfield = "sample_received_at_vl_lab_datetime";
if (isset($_POST['testType']) && !empty($_POST['testType'])) {
    $testType = $_POST['testType'];
}

if (isset($testType) && $testType == 'vl') {
    $url = "/vl/requests/vlRequest.php";
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
     
    $table = "form_tb";
    $testName = 'TB';
    $sampleReceivedfield = "sample_received_at_lab_datetime";
}

$sQuery = "SELECT f.facility_id, f.facility_name, tar.request_type, tar.requested_on, tar.test_type, 
                GREATEST(
                    COALESCE(facility_attributes->>'$.lastHeartBeat', 0), 
                    COALESCE(facility_attributes->>'$.lastResultsSync', 0), 
                    COALESCE(facility_attributes->>'$.lastRequestSync', 0),
                    COALESCE(tar.requested_on, 0)
                    ) as latest, 
                (facility_attributes->>'$.version') as version, 
                (facility_attributes->>'$.lastHeartBeat') as lastHeartBeat, 
                (facility_attributes->>'$.lastResultsSync') as lastResultsSync, 
                (facility_attributes->>'$.lastRequestSync') as lastRequestsSync 
            FROM `facility_details`as f 
            LEFT JOIN track_api_requests as tar ON tar.facility_id = f.facility_id 
            LEFT JOIN testing_labs as lab ON lab.facility_id = f.facility_id";

//if (isset($_POST['testType']) && trim($_POST['testType']) != '') {
//$sQuery .= " JOIN $table as vl ON f.facility_id = vl.lab_id";
//}
$sWhere[] = ' f.facility_type = 2 AND f.status = "active" ';
if (isset($_POST['testType']) && trim($_POST['testType']) != '') {
    $sWhere[] = ' (tar.test_type like "' . $_POST['testType'] . '"  OR tar.test_type is null) ';
}

if (isset($_POST['labName']) && trim($_POST['labName']) != '') {
    $sWhere[] = ' f.facility_id IN (' . $_POST['labName'] . ')';
}
if (isset($_POST['province']) && trim($_POST['province']) != '') {
    $sWhere[] = ' f.facility_state_id = "' . $_POST['province'] . '"';
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
    $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}

/* Implode all the where fields for filtering the data */
if (!empty($sWhere)) {
    $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere) . ' 
    GROUP BY f.facility_id 
    ORDER BY latest DESC';
}
$_SESSION['labSyncStatus'] = $sQuery;
$rResult = $db->rawQuery($sQuery);
$today = new DateTimeImmutable();
$twoWeekExpiry = $today->sub(DateInterval::createFromDateString('2 weeks'));
$threeWeekExpiry = $today->sub(DateInterval::createFromDateString('4 weeks'));
foreach ($rResult as $key => $aRow) {
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
    <tr class="<?php echo $color; ?>" data-facilityId="<?php echo base64_encode($aRow['facility_id']);?>">
        <td><?= ($aRow['facility_name']); ?></td>
        <td><?= DateUtils::humanReadableDateFormat($aRow['latest'], true); ?></td>
        <td><?= DateUtils::humanReadableDateFormat($aRow['lastResultsSync'], true); ?></td>
        <td><?= DateUtils::humanReadableDateFormat($aRow['lastRequestsSync'], true); ?></td>
        <td><?= (isset($aRow['version']) && !empty($aRow['version']) && $aRow['version'] != "" && $aRow['version'] != null)?$aRow['version']:" - "; ?></td>
    </tr>
<?php } ?>