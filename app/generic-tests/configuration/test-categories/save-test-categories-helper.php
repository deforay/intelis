<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_generic_test_categories";

$testCategoryId = (int) base64_decode((string) $_POST['testCategoryId']);
$_POST['testCategory'] = trim((string) $_POST['testCategory']);
try {
    if (isset($_POST['testCategory']) && ($_POST['testCategory'] !== '' && $_POST['testCategory'] !== '0')) {

        $data = ['test_category_name' => $_POST['testCategory'], 'test_category_status' => $_POST['testCategoryStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($testCategoryId !== 0) {
            $db->where('test_category_id', $testCategoryId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Category updated successfully");
                $general->activityLog('Test category', $_SESSION['userName'] . ' updated new Test category for ' . $_POST['testCategory'], 'generic-test-categories');
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Category added successfully");
                $general->activityLog('Test Category', $_SESSION['userName'] . ' added new Test category for ' . $_POST['testCategory'], 'generic-test-categories');
            }
        }
    }
    //error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    header("location:generic-test-categories.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}
