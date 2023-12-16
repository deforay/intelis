<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$mappingType     = $_POST['mappingType'];
$testType   = $_POST['testType'];

if ($mappingType == "testing-labs") {
    $facilityQuery = "SELECT facility_id, facility_name FROM facility_details WHERE status = 'active' and facility_type=2";
    $facilityResult = $db->rawQuery($facilityQuery);

    $db->where("test_type", $testType);
    $mapResult = $db->get("testing_labs", null, "facility_id");
} elseif ($mappingType == "health-facilities") {
    $facilityQuery = "SELECT facility_id, facility_name FROM facility_details WHERE status = 'active'";
    $facilityResult = $db->rawQuery($facilityQuery);

    $db->where("test_type", $testType);
    $mapResult = $db->get("health_facilities", null, "facility_id");
}

$mapResult = array_column($mapResult, 'facility_id'); // Convert the result into a simple array
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
