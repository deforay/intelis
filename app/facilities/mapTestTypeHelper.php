<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;



// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

$mappingType = $_POST['mappingType'];
$testType   = $_POST['testType'];

if ($mappingType == "testing-labs") {
    $tableName = "testing_labs";
} else {
    $tableName = "health_facilities";
}
try {
    $mappedFacilities = json_decode($_POST['selectedFacilities'], true);
    if (!empty($mappedFacilities)) {

        $db->where('test_type', $testType);
        $db->delete($tableName);
        $currentDateTime = DateUtility::getCurrentDateTime();
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
        $data = array(
            'updated_datetime'  => $currentDateTime
        );

        $db->where('facility_id', $mappedFacilities, 'IN');
        $db->update($tableName, $data);


        $_SESSION['alertMsg'] = _translate("Facility Mapped to Selected Test Type successfully");
    } else {
        $_SESSION['alertMsg'] = _translate("No Facility Mapped to Selected Test Type");
    }
    header("Location:/facilities/mapTestType.php?type=$mappingType&test=$testType");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
