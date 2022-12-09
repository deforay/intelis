<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new \Vlsm\Models\General();
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
    $url = "/tb/requests/tb-requests.php";
    $table = "form_tb";
    $testName = 'TB';
    $sampleReceivedfield = "sample_received_at_lab_datetime";
}

$sQuery = "SELECT f.facility_id, f.facility_name, tar.request_type, tar.requested_on, tar.test_type 
            FROM facility_details as f 
            LEFT JOIN track_api_requests as tar ON tar.facility_id = f.facility_id
            LEFT JOIN testing_labs as lab ON lab.facility_id = f.facility_id";

//if (isset($_POST['testType']) && trim($_POST['testType']) != '') {
//$sQuery .= " JOIN $table as vl ON f.facility_id = vl.lab_id";
//}
$sWhere[] = ' f.facility_type = 2 and f.status = "active" ';
if (isset($_POST['testType']) && trim($_POST['testType']) != '') {
    $sWhere[] = ' (tar.test_type like "' . $_POST['testType'] . '"  OR tar.test_type is null) ';
    $sWhere[] = ' (lab.test_type like "' . $_POST['testType'] . '"  OR lab.test_type is null) ';
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
    $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere) . ' GROUP BY f.facility_id ORDER BY tar.requested_on DESC';
}
$_SESSION['labSyncStatus'] = $sQuery;
$rResult = $db->rawQuery($sQuery);
$twoWeekExpiry = date("Y-m-d", strtotime(date("Y-m-d") . '-2 weeks'));
$threeWeekExpiry = date("Y-m-d", strtotime(date("Y-m-d") . '-4 weeks'));
foreach ($rResult as $key => $aRow) {
    $color = "red";

    if ($aRow['requested_on'] >= $twoWeekExpiry) {
        $color = "green";
    } elseif ($aRow['requested_on'] > $threeWeekExpiry && $aRow['requested_on'] < $twoWeekExpiry) {
        $color = "yellow";
    } elseif ($aRow['requested_on'] >= $threeWeekExpiry) {
        $color = "red";
    }
    /* Assign data table variables */ ?>
    <tr class="<?php echo $color; ?>">
        <td><a href="lab-sync-details.php?labId=<?php echo base64_encode($aRow['facility_id']);?>" target="_blank"><?php echo ucwords($aRow['facility_name']); ?></a></td>
        <!-- <td><?php echo ucwords($aRow['test_type']); ?></td> -->
        <td><?php echo $general->humanReadableDateFormat($aRow['requested_on'], true); ?></td>
    </tr>
<?php } ?>