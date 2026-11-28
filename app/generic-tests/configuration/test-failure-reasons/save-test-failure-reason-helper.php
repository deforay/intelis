<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_generic_test_failure_reasons";

$testFailureReasonId = (int) base64_decode((string) $_POST['testFailureReasonId']);
$_POST['testFailureReason'] = trim((string) $_POST['testFailureReason']);
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['testFailureReasonId'] ?? ''));
$activityBefore = $activityLog->row('r_generic_test_failure_reasons', 'test_failure_reason_id', $activityId);

try {
    if (isset($_POST['testFailureReason']) && ($_POST['testFailureReason'] !== '' && $_POST['testFailureReason'] !== '0')) {

        $data = ['test_failure_reason' => $_POST['testFailureReason'], 'test_failure_reason_code' => trim((string) $_POST['testFailureReasonCode']), 'test_failure_reason_status' => $_POST['testFailureReasonStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($testFailureReasonId !== 0) {
            $db->where('test_failure_reason_id', $testFailureReasonId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Failure reason updated successfully");
                $activityLog->recordRowSave(
                    'generic-test-failure-reason-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests test failure reason ' . $_POST['testFailureReason'],
                    'generic-test-failure-reason',
                    'r_generic_test_failure_reasons',
                    'test_failure_reason_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'test_failure_reason',
                    $activityBefore
                );
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Failure reason added successfully");
                $activityLog->recordRowSave(
                    'generic-test-failure-reason-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests test failure reason ' . $_POST['testFailureReason'],
                    'generic-test-failure-reason',
                    'r_generic_test_failure_reasons',
                    'test_failure_reason_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'test_failure_reason',
                    $activityBefore
                );
            }
        }
    }
    //error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    header("location:generic-test-failure-reason.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    throw $exc;
}
