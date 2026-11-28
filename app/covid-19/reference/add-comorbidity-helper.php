<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;







/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);




$tableName = "r_covid19_comorbidities";

// A new row only, so the activity log lists every field it was saved with.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = 0;
$activityBefore = $activityLog->row('r_covid19_comorbidities', 'comorbidity_id', $activityId);

try {
	if (isset($_POST['comorbidityName']) && trim((string) $_POST['comorbidityName']) !== "") {


		$data = ['comorbidity_name' => $_POST['comorbidityName'], 'comorbidity_status' => $_POST['comorbidityStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _translate("Comorbidity details added successfully");
		$activityLog->recordRowSave(
		    'covid19-comorbidity-save',
		    ($activityBefore === null ? 'Added ' : 'Updated ') . 'COVID-19 comorbidity ' . $_POST['comorbidityName'],
		    'covid19-comorbidity',
		    'r_covid19_comorbidities',
		    'comorbidity_id',
		    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
		    'comorbidity_name',
		    $activityBefore
		);
	}
	header("Location:covid19-comorbidities.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}
