<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$mappingType = $_POST['mappingType'];
$testType = $_POST['testType'];

$isTestingLab = $mappingType == "testing-labs";

$facilityTypeCondition = $isTestingLab ? " AND facility_type=2 " : "";
$facilityQuery = "SELECT facility_id, facility_name FROM facility_details WHERE `status` = 'active' " . $facilityTypeCondition . " order by facility_name";
$facilityResult = $db->rawQuery($facilityQuery);

if (empty($facilityResult)) {
    echo "";
    exit;
}

$tableName = $isTestingLab ? "testing_labs" : "health_facilities";
$db->where("test_type", $testType);
$mapResult = $db->get($tableName, null, "facility_id");



$mapResult = array_column($mapResult ?? [], 'facility_id'); // Convert the result into a simple array

$response = '';
foreach ($facilityResult as $row) {
    $selectedText = in_array($row['facility_id'], $mapResult) ? "selected='selected'" : '';
    $response .= "<option value='" . $row['facility_id'] . "' $selectedText>" . $row['facility_name'] . "</option>";
}
echo $response;
