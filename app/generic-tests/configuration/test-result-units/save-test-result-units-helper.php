<?php

use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

$general = new CommonService();
$tableName = "r_generic_test_result_units";
$unitId = (int) base64_decode($_POST['unitId']);
try {
    if (!empty($_POST['unitName'])) {
       
        $data = array(
            'unit_name' => trim($_POST['unitName']),
            'unit_status' => $_POST['status'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        if(!empty($unitId)){
            $db = $db->where('unit_id', $unitId);
            $lastId = $db->update($tableName, $data);
            if($lastId > 0){
                $_SESSION['alertMsg'] = _("Test Result Unit Details updated successfully");
                $general->activityLog('Test Result Units', $_SESSION['userName'] . ' updated new test result Unit for ' . $_POST['unitName'], 'generic-test-result-units');
            }
        }else{
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if($lastId > 0){
                $_SESSION['alertMsg'] = _("Test Result Unit details added successfully");
                $general->activityLog('Test Result Units', $_SESSION['userName'] . ' added new test result unit for ' . $_POST['unitName'], 'generic-test-result-units');
            }
        }
    }
    error_log($db->getLastError());
    header("location:generic-test-result-units.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
