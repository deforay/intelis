<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_hepatitis_comorbidities";
$primaryKey = "comorbidity_id";
// print_r($_POST);die;
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['comorbidityId'] ?? ''));
$activityBefore = $activityLog->row('r_hepatitis_comorbidities', 'comorbidity_id', $activityId);

try {
	if (isset($_POST['comorbidityName']) && trim((string) $_POST['comorbidityName']) !== "") {
		$data = ['comorbidity_name'         => $_POST['comorbidityName'], 'comorbidity_status'     => $_POST['comorbidityStatus'], 'updated_datetime'     => DateUtility::getCurrentDateTime()];
		if (isset($_POST['comorbidityId']) && $_POST['comorbidityId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['comorbidityId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Hepatitis Comorbidity details saved successfully");
			$activityLog->recordRowSave(
			    'hepatitis-comorbidity-save',
			    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Hepatitis comorbidity ' . $_POST['comorbidityName'],
			    'hepatitis-comorbidity',
			    'r_hepatitis_comorbidities',
			    'comorbidity_id',
			    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
			    'comorbidity_name',
			    $activityBefore
			);
		}
	}
	header("Location:hepatitis-comorbidities.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}
