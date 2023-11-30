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
$tableName = "r_generic_test_reasons";

$testReasonId = (int) base64_decode((string) $_POST['testReasonId']);
$_POST['testReason'] = trim((string) $_POST['testReason']);
try {
    if (!empty($_POST['testReason'])) {

        $data = array(
            'test_reason' => $_POST['testReason'],
            'test_reason_code' => trim((string) $_POST['testReasonCode']),
            'test_reason_status' => $_POST['testReasonStatus'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        if (!empty($testReasonId)) {
            $db = $db->where('test_reason_id', $testReasonId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Testing reason updated successfully");
                $general->activityLog('Testing Reason', $_SESSION['userName'] . ' updated new testing reason for ' . $_POST['testReason'], 'generic-testing-reason');
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Testing reason added successfully");
                $general->activityLog('Testing Reason', $_SESSION['userName'] . ' added new testing reason for ' . $_POST['testReason'], 'generic-testing-reason');
            }
        }
    }
    //error_log($db->getLastError());
    header("location:generic-testing-reason.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
