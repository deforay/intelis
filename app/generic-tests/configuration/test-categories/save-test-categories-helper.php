<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_generic_test_categories";

$testCategoryId = (int) base64_decode((string) $_POST['testCategoryId']);
$_POST['testCategory'] = trim((string) $_POST['testCategory']);
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['testCategoryId'] ?? ''));
$activityBefore = $activityLog->row('r_generic_test_categories', 'test_category_id', $activityId);

try {
    if (isset($_POST['testCategory']) && ($_POST['testCategory'] !== '' && $_POST['testCategory'] !== '0')) {

        $data = ['test_category_name' => $_POST['testCategory'], 'test_category_status' => $_POST['testCategoryStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($testCategoryId !== 0) {
            $db->where('test_category_id', $testCategoryId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Category updated successfully");
                $activityLog->recordRowSave(
                    'generic-test-category-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests test category ' . $_POST['testCategory'],
                    'generic-test-category',
                    'r_generic_test_categories',
                    'test_category_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'test_category_name',
                    $activityBefore
                );
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Category added successfully");
                $activityLog->recordRowSave(
                    'generic-test-category-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests test category ' . $_POST['testCategory'],
                    'generic-test-category',
                    'r_generic_test_categories',
                    'test_category_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'test_category_name',
                    $activityBefore
                );
            }
        }
    }
    //error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    header("location:generic-test-categories.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    throw $exc;
}
