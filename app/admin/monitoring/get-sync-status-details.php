<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new \Vlsm\Models\General();
$table = "form_vl";
$primaryKey = "vl_sample_id";

$testType = 'vl';
if (isset($_POST['testType']) && !empty($_POST['testType'])) {
    $testType = $_POST['testType'];
}

if (isset($testType) && $testType == 'vl') {
    $table = "form_vl";
    $testName = 'Viral Load';
}
if (isset($testType) && $testType == 'eid') {
    $table = "form_eid";
    $testName = 'EID';
}
if (isset($testType) && $testType == 'covid19') {
    $table = "form_covid19";
    $testName = 'Covid-19';
}
if (isset($testType) && $testType == 'hepatitis') {
    $table = "form_hepatitis";
    $testName = 'Hepatitis';
}
if (isset($testType) && $testType == 'tb') {
    $table = "form_tb";
    $testName = 'TB';
}

$sQuery = "SELECT f.facility_id, 
              f.facility_name, tar.test_type, tar.requested_on,
               (vl.form_attributes->>'$.remoteResultsSync') as lastResultsSync, 
               (vl.form_attributes->>'$.remoteRequestsSync') as lastRequestsSync   
               FROM facility_details AS f 
               JOIN track_api_requests AS tar ON tar.facility_id = f.facility_id ";
if (isset($_POST['testType']) && trim($_POST['testType']) != '') {
    $sQuery .= " JOIN $table as vl ON f.facility_id = vl.lab_id";
}
if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
    $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['province']) && trim($_POST['province']) != '') {
    $sWhere[] = ' f.facility_state_id = "' . $_POST['province'] . '"';
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
    $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}

if (isset($_POST['labId']) && trim($_POST['labId']) != '') {
    $sWhere[] = ' vl.lab_id = "' . base64_decode($_POST['labId']) . '"';
}
if (!empty($sWhere)) {
    $sQuery = $sQuery . " WHERE " . implode(" AND ", $sWhere);
}
$sQuery = $sQuery . " GROUP BY f.facility_id ORDER BY tar.requested_on DESC";
// die($sQuery);
$rResult = $db->rawQuery($sQuery);
foreach ($rResult as $key => $aRow) { ?>
    <tr class="<?php echo $color; ?>">
        <td><?php echo ucwords($aRow['facility_name']); ?></td>
        <td><?php echo ucwords($aRow['test_type']); ?></td>
        <td><?php echo $general->humanReadableDateFormat($aRow['lastRequestsSync'], true); ?></td>
        <td><?php echo $general->humanReadableDateFormat($aRow['lastResultsSync'], true); ?></td>
    </tr>
<?php } ?>