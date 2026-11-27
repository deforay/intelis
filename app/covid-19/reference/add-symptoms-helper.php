<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;







/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);




$tableName = "r_covid19_symptoms";

// A new row only, so the activity log lists every field it was saved with.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = 0;
$activityBefore = $activityLog->row('r_covid19_symptoms', 'symptom_id', $activityId);

try {
	if (isset($_POST['symptomsName']) && trim((string) $_POST['symptomsName']) !== "") {

		$data = ['symptom_name' => $_POST['symptomsName'], 'parent_symptom' => $_POST['parentSymptom'], 'symptom_status' => $_POST['symptomsStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _translate("Symptom details added successfully");
		$activityLog->recordRowSave(
		    'covid19-symptom-save',
		    ($activityBefore === null ? 'Added ' : 'Updated ') . 'COVID-19 symptom ' . $_POST['symptomsName'],
		    'covid19-symptom',
		    'r_covid19_symptoms',
		    'symptom_id',
		    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
		    'symptom_name',
		    $activityBefore
		);
	}
	header("Location:covid19-symptoms.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}
