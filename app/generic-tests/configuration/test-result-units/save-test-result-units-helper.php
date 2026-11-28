<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;




$tableName = "r_generic_test_result_units";

$unitId = (int) base64_decode((string) $_POST['unitId']);
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['unitId'] ?? ''));
$activityBefore = $activityLog->row('r_generic_test_result_units', 'unit_id', $activityId);

try {
    if (!empty($_POST['unitName'])) {

        $data = ['unit_name' => trim((string) $_POST['unitName']), 'unit_status' => $_POST['status'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($unitId !== 0) {
            $db->where('unit_id', $unitId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Result Unit Details updated successfully");
                $activityLog->recordRowSave(
                    'generic-result-unit-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests result unit ' . $_POST['unitName'],
                    'generic-result-unit',
                    'r_generic_test_result_units',
                    'unit_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'unit_name',
                    $activityBefore, [], ['unit_name', 'unit_status']
                );
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Test Result Unit details added successfully");
                $activityLog->recordRowSave(
                    'generic-result-unit-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests result unit ' . $_POST['unitName'],
                    'generic-result-unit',
                    'r_generic_test_result_units',
                    'unit_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'unit_name',
                    $activityBefore, [], ['unit_name', 'unit_status']
                );
            }
        }
    }
    header("location:generic-test-result-units.php");
} catch (Exception $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    throw $e;
}
