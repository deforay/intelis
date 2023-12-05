<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_generic_test_methods";

$testMethodId = (int) base64_decode((string) $_POST['testMethodId']);
$_POST['testMethod'] = trim((string) $_POST['testMethod']);
try {
    if (!empty($_POST['testMethod'])) {

        $data = array(
            'test_method_name' => $_POST['testMethod'],
            'test_method_status' => $_POST['testMethodStatus'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        if (!empty($testMethodId)) {
            $db = $db->where('test_method_id', $testMethodId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Method updated successfully");
                $general->activityLog('Test Method', $_SESSION['userName'] . ' updated new Test Method for ' . $_POST['testMethod'], 'generic-test-methods');
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Method added successfully");
                $general->activityLog('Test Method', $_SESSION['userName'] . ' added new Test Method for ' . $_POST['testMethod'], 'generic-test-methods');
            }
        }
    }
    //error_log($db->getLastError());
    header("location:generic-test-methods.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
