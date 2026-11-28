<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Registries\ContainerRegistry;





$tableName = "r_generic_symptoms";
$symptomId = (int) base64_decode((string) $_POST['symptomId']);
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['symptomId'] ?? ''));
$activityBefore = $activityLog->row('r_generic_symptoms', 'symptom_id', $activityId);

try {
    if (!empty($_POST['symptomName'])) {

        $data = ['symptom_name' => trim((string) $_POST['symptomName']), 'symptom_code' => trim((string) $_POST['symptomCode']), 'symptom_status' => $_POST['status'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        if ($symptomId !== 0) {
            $db->where('symptom_id', $symptomId);
            $lastId = $db->update($tableName, $data);
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Symptoms Details updated successfully");
                $activityLog->recordRowSave(
                    'generic-symptom-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests symptom ' . $_POST['symptomName'],
                    'generic-symptom',
                    'r_generic_symptoms',
                    'symptom_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'symptom_name',
                    $activityBefore
                );
            }
        } else {
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if ($lastId > 0) {
                $_SESSION['alertMsg'] = _translate("Symptoms details added successfully");
                $activityLog->recordRowSave(
                    'generic-symptom-save',
                    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests symptom ' . $_POST['symptomName'],
                    'generic-symptom',
                    'r_generic_symptoms',
                    'symptom_id',
                    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
                    'symptom_name',
                    $activityBefore
                );
            }
        }
    }
    error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    header("location:generic-symptoms.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    throw $exc;
}
