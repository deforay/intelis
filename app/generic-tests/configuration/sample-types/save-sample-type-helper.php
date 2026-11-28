<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_generic_sample_types";
$sampleTypeId = (int) base64_decode((string) $_POST['sampleTypeId']);
$_POST['sampleTypeName'] = trim((string) $_POST['sampleTypeName']);
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['sampleTypeId'] ?? ''));
$activityBefore = $activityLog->row('r_generic_sample_types', 'sample_type_id', $activityId);

try {
    if (isset($_POST['sampleTypeName']) && ($_POST['sampleTypeName'] !== '' && $_POST['sampleTypeName'] !== '0')) {

        $data = ['sample_type_name' => $_POST['sampleTypeName'], 'sample_type_code' => $_POST['sampleTypeCode'], 'sample_type_status' => $_POST['sampleTypeStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($sampleTypeId !== 0) {
            $db->where('sample_type_id', $sampleTypeId);
            $lastId = $db->update($tableName, $data);
            $_SESSION['alertMsg'] = _translate("Sample type updated successfully");
            if ($lastId > 0) {
                $activityLog->recordRowSave(
                    'generic-sample-type-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests sample type ' . $_POST['sampleTypeName'],
                    'generic-sample-type',
                    'r_generic_sample_types',
                    'sample_type_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'sample_type_name',
                    $activityBefore
                );
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Sample type added successfully");
                $activityLog->recordRowSave(
                    'generic-sample-type-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests sample type ' . $_POST['sampleTypeName'],
                    'generic-sample-type',
                    'r_generic_sample_types',
                    'sample_type_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'sample_type_name',
                    $activityBefore
                );
            }
        }
    }
    error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    header("location:generic-sample-type.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    throw $exc;
}
