<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);


$tableName = "r_generic_test_reasons";

$testReasonId = (int) base64_decode((string) $_POST['testReasonId']);
$_POST['testReason'] = trim((string) $_POST['testReason']);
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['testReasonId'] ?? ''));
$activityBefore = $activityLog->row('r_generic_test_reasons', 'test_reason_id', $activityId);

try {
    if (isset($_POST['testReason']) && ($_POST['testReason'] !== '' && $_POST['testReason'] !== '0')) {

        $data = ['test_reason' => $_POST['testReason'], 'test_reason_code' => trim((string) $_POST['testReasonCode']), 'test_reason_status' => $_POST['testReasonStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($testReasonId !== 0) {
            $db->where('test_reason_id', $testReasonId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Testing reason updated successfully");
                $activityLog->recordRowSave(
                    'generic-test-reason-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests testing reason ' . $_POST['testReason'],
                    'generic-test-reason',
                    'r_generic_test_reasons',
                    'test_reason_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'test_reason',
                    $activityBefore
                );
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Testing reason added successfully");
                $activityLog->recordRowSave(
                    'generic-test-reason-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests testing reason ' . $_POST['testReason'],
                    'generic-test-reason',
                    'r_generic_test_reasons',
                    'test_reason_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'test_reason',
                    $activityBefore
                );
            }
        }
    }

    header("location:generic-testing-reason.php");
} catch (Exception $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    throw $e;
}
