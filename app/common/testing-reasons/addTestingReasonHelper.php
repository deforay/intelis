<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$tableName = "r_testing_reasons";

/*echo "<pre>";
print_r($_POST);
die;*/
$_POST['testReason'] = trim($_POST['testReason']);
try {
    if (!empty($_POST['testReason'])) {
       
        $data = array(
            'test_reason' => $_POST['testReason'],
            'test_reason_code' => $_POST['testReasonCode'],
            'test_reason_status' => $_POST['testReasonStatus'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        
        $id = $db->insert($tableName, $data);
        $lastId = $db->getInsertId();
        if($lastId > 0){
            $_SESSION['alertMsg'] = _("Testing reason added successfully");
            $general->activityLog('Testing Reason', $_SESSION['userName'] . ' added new testing reason for ' . $_POST['testReason'], 'common-testing-reason');
        }
        
    }
    //error_log($db->getLastError());
    header("location:testingReason.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
