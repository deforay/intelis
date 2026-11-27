<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_generic_test_methods";

$testMethodId = (int) base64_decode((string) $_POST['testMethodId']);
$_POST['testMethod'] = trim((string) $_POST['testMethod']);
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['testMethodId'] ?? ''));
$activityBefore = $activityLog->row('r_generic_test_methods', 'test_method_id', $activityId);

try {
    if (isset($_POST['testMethod']) && ($_POST['testMethod'] !== '' && $_POST['testMethod'] !== '0')) {

        $data = ['test_method_name' => $_POST['testMethod'], 'test_method_status' => $_POST['testMethodStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($testMethodId !== 0) {
            $db->where('test_method_id', $testMethodId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Method updated successfully");
                $activityLog->recordRowSave(
                    'generic-test-method-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests test method ' . $_POST['testMethod'],
                    'generic-test-method',
                    'r_generic_test_methods',
                    'test_method_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'test_method_name',
                    $activityBefore
                );
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Method added successfully");
                $activityLog->recordRowSave(
                    'generic-test-method-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests test method ' . $_POST['testMethod'],
                    'generic-test-method',
                    'r_generic_test_methods',
                    'test_method_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'test_method_name',
                    $activityBefore
                );
            }
        }
    }
    //error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    header("location:generic-test-methods.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    throw $exc;
}
