<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$currentDateTime = DateUtility::getCurrentDateTime();

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$mappingType = $_POST['mappingType'];
$testType   = $_POST['testType'];

if ($mappingType == "testing-labs") {
    $tableName = "testing_labs";
} else {
    $tableName = "health_facilities";
}
try {
    if (!empty($_POST)) {
        $db->where('test_type', $testType);
        $db->delete($tableName);
        $mappedFacilities = json_decode((string) $_POST['selectedFacilities'], true);
        if (!empty($mappedFacilities)) {
            $data = [];
            foreach ($mappedFacilities as $facility) {
                $data[] = [
                    'test_type'     => $testType,
                    'facility_id'   => $facility,
                    'updated_datetime'  => $currentDateTime
                ];
            }
            $db->insertMulti($tableName, $data);

            // Issue : When one test type was updated, the other test types for those same facilities don't get synced
            // To overcome this, we update the datetime of all test types for those facilities

            $db->where('facility_id', $mappedFacilities, 'IN');
            $db->update($tableName, ['updated_datetime'  => $currentDateTime]);
            $alertMessage = _translate("Facility Mapped to Selected Test Type successfully");
        } else {
            $alertMessage = _translate("No Facility Mapped to Selected Test Type");
        }
        $_SESSION['alertMsg'] = $alertMessage;
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

header("Location:/facilities/mapTestType.php?type=$mappingType&test=$testType");
