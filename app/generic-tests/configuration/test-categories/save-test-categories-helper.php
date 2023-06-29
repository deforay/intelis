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
$tableName = "r_generic_test_categories";

$testCategoryId = (int) base64_decode($_POST['testCategoryId']);
$_POST['testCategory'] = trim($_POST['testCategory']);
try {
    if (!empty($_POST['testCategory'])) {

        $data = array(
            'test_category_name' => $_POST['testCategory'],
            'test_category_status' => $_POST['testCategoryStatus'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        if (!empty($testCategoryId)) {
            $db = $db->where('test_category_id', $testCategoryId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _("Test Category updated successfully");
                $general->activityLog('Test category', $_SESSION['userName'] . ' updated new Test category for ' . $_POST['testCategory'], 'generic-test-categories');
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _("Test Category added successfully");
                $general->activityLog('Test Category', $_SESSION['userName'] . ' added new Test category for ' . $_POST['testCategory'], 'generic-test-categories');
            }
        }
    }
    //error_log($db->getLastError());
    header("location:generic-test-categories.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
