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
$tableName = "r_testing_reasons";

/*echo "<pre>";
print_r($_POST);
die;*/
$testReasonId = (int) base64_decode($_POST['testReasonId']);
$_POST['testReason'] = trim($_POST['testReason']);
try {
    if (!empty($_POST['testReason'])) {
       
        $data = array(
            'test_reason' => $_POST['testReason'],
            'test_reason_code' => trim($_POST['testReasonCode']),
            'test_reason_status' => $_POST['testReasonStatus'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        
        $db = $db->where('test_reason_id', $testReasonId);
        $db->update($tableName, $data);
        $_SESSION['alertMsg'] = _("Testing reason updated successfully");
    }
    //error_log($db->getLastError());
    header("location:testingReason.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
