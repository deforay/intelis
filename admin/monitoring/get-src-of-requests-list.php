<?php
$general = new \Vlsm\Models\General();
$table = "vl_request_form";
$testType = 'vl';
if (isset($_POST['testType']) && !empty($_POST['testType'])) {
    $testType = $_POST['testType'];
}

if (isset($testType) && $testType == 'vl') {
    $table = "vl_request_form";
}
if (isset($testType) && $testType == 'eid') {
    $table = "eid_form";
}
if (isset($testType) && $testType == 'covid19') {
    $table = "form_covid19";
}
if (isset($testType) && $testType == 'hepatitis') {
    $table = "form_hepatitis";
}
if (isset($testType) && $testType == 'tb') {
    $table = "form_tb";
}
$sQuery = "SELECT DISTINCT source_of_request from $table;";
$result = $db->rawQuery($sQuery);
$option = "<option value=''>--All--</option>";
foreach ($result as $row) {
    if (isset($row['source_of_request']) && $row['source_of_request'] != "") {
        $option .= "<option value='" . $row['source_of_request'] . "'>" . strtoupper($row['source_of_request']) . "</option>";
    }
}
echo $option;
