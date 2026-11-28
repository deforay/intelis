<?php

use App\Utilities\DateUtility;
use App\Services\ActivityLogService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_hepatitis_risk_factors";
$primaryKey = "riskfactor_id";
// print_r($_POST);die;
// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['riskFactorId'] ?? ''));
$activityBefore = $activityLog->row('r_hepatitis_risk_factors', 'riskfactor_id', $activityId);

try {
	if (isset($_POST['riskFactorName']) && trim((string) $_POST['riskFactorName']) !== "") {
		$data = ['riskfactor_name'         => $_POST['riskFactorName'], 'riskfactor_status'     => $_POST['riskFactorStatus'], 'updated_datetime'     => DateUtility::getCurrentDateTime()];
		if (isset($_POST['riskFactorId']) && $_POST['riskFactorId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['riskFactorId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Hepatitis Risk Factor details saved successfully");
			$activityLog->recordRowSave(
			    'hepatitis-risk-factor-save',
			    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Hepatitis risk factor ' . $_POST['riskFactorName'],
			    'hepatitis-risk-factor',
			    'r_hepatitis_risk_factors',
			    'riskfactor_id',
			    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
			    'riskfactor_name',
			    $activityBefore
			);
		}
	}
	header("Location:hepatitis-risk-factors.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}
