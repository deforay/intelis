<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_generic_test_failure_reasons";

$testFailureReasonId = (int) base64_decode($_POST['testFailureReasonId']);
$_POST['testFailureReason'] = trim($_POST['testFailureReason']);
try {
    if (!empty($_POST['testFailureReason'])) {
       
        $data = array(
            'test_failure_reason' => $_POST['testFailureReason'],
            'test_failure_reason_code' => trim($_POST['testFailureReasonCode']),
            'test_failure_reason_status' => $_POST['testFailureReasonStatus'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        if(isset($testFailureReasonId) && !empty($testFailureReasonId)){
            $db = $db->where('test_failure_reason_id', $testFailureReasonId);
            $lastId = $db->update($tableName, $data);
            if($lastId > 0){
                $_SESSION['alertMsg'] = _("Test Failure reason updated successfully");
                $general->activityLog('Test Failure Reason', $_SESSION['userName'] . ' updated new test failure reason for ' . $_POST['testFailureReason'], 'generic-test-failure-reason');
            }
        }else{
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if($lastId > 0){
                $_SESSION['alertMsg'] = _("Test Failure reason added successfully");
                $general->activityLog('Test Failure Reason', $_SESSION['userName'] . ' added new test failure reason for ' . $_POST['testFailureReason'], 'generic-test-failure-reason');
            }
        }
    }
    //error_log($db->getLastError());
    header("location:generic-test-failure-reason.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
