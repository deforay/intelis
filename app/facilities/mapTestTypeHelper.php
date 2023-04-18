<?php

use App\Models\General;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new General();
$params     = $_POST['facilityType'];
$testType   = $_POST['testType'];
//print_r($_POST); die;
if ($params == "testing-labs") {
    $tableName = "testing_labs";
} else {
    $tableName = "health_facilities";
}
try {
    $mappedFacility = explode(',',$_POST['selectedSample']);
   // $_POST['mappedFacilities'] = json_decode($_POST['mappedFacilities'], true);
    if (isset($mappedFacility) && count($mappedFacility) > 0) {

        $db->where('test_type', $testType);
       // $db->where('facility_id', $mappedFacility, 'NOT IN');
        $db->delete($tableName);
        $currentDateTime = DateUtils::getCurrentDateTime();
        $data = array();
        foreach ($mappedFacility as $facility) {
            $data[] = array(
                'test_type'     => $testType,
                'facility_id'   => $facility,
                'updated_datetime'  => $currentDateTime
            );
        }
        $db->insertMulti($tableName, $data);

        // Issue : When one test type was updated, the other test types for those same facilities don't get synced
        // To overcome this, we update the datetime of all test types for those facilities
        $data = array(
            'updated_datetime'  => $currentDateTime
        );

        $db->where('facility_id', $mappedFacility, 'IN');
        $db->update($tableName, $data);


        $_SESSION['alertMsg'] = _("Facility Mapped to Selected Test Type successfully");
    }
    header("location:facilities.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
