<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$params     = $_POST['facilityType'];
$testType   = $_POST['testType'];



if ($params == "testing-labs") {
    $facilityQuery = "SELECT facility_id, facility_name FROM facility_details WHERE status = 'active' and facility_type=2";
    $facilityResult = $db->rawQuery($facilityQuery);

    $db->where("test_type", $testType);
    $mapResult = $db->getValue("testing_labs", "GROUP_CONCAT(DISTINCT facility_id SEPARATOR ',')");
} else if ($params == "health-facilities") {
    $facilityQuery = "SELECT facility_id, facility_name FROM facility_details WHERE status = 'active'";
    $facilityResult = $db->rawQuery($facilityQuery);

    $db->where("test_type", $testType);
    $mapResult = $db->getValue("health_facilities", "GROUP_CONCAT(DISTINCT facility_id SEPARATOR ',')");
}

$mapResult = explode(",", $mapResult);
$response = '';
foreach ($facilityResult as $row) {
    $selectedText = '';
    if (!empty($mapResult)) {
        if (in_array($row['facility_id'], $mapResult)) {
            $selectedText = "selected='selected'";
        }
    }
    $response .= "<option value='" . $row['facility_id'] . "' $selectedText>" . $row['facility_name'] . "</option>";
}
echo $response;
