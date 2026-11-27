<?php

use App\Registries\ContainerRegistry;
use App\Services\ActivityLogService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;



// echo "<pre>";print_r($_POST);die;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "r_generic_sample_rejection_reasons";
$primaryKey = "rejection_reason_id";

// Read before the save, so the activity log lists what changed.
/** @var ActivityLogService $activityLog */
$activityLog = ContainerRegistry::get(ActivityLogService::class);
$activityId = (int) base64_decode((string) ($_POST['rejectionReasonId'] ?? ''));
$activityBefore = $activityLog->row('r_generic_sample_rejection_reasons', 'rejection_reason_id', $activityId);

try {
	if (isset($_POST['rejectionReasonName']) && trim((string) $_POST['rejectionReasonName']) !== "") {

		$data = ['rejection_reason_name' 	=> $_POST['rejectionReasonName'], 'rejection_reason_code'		=> $_POST['rejectionReasonCode'], 'rejection_reason_status' 	=> $_POST['rejectionReasonStatus'], 'updated_datetime' 			=> DateUtility::getCurrentDateTime()];
		if (isset($_POST['rejectionType']) && $_POST['rejectionType'] != "") {
			$data["rejection_type"] = $_POST["rejectionType"];
		}
		if (isset($_POST['rejectionReasonId']) && $_POST['rejectionReasonId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['rejectionReasonId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Lab Tests Sample Rejection Reasons details added successfully");
			$activityLog->recordRowSave(
			    'generic-rejection-reason-save',
			    ($activityBefore === null ? 'Added ' : 'Updated ') . 'Custom Tests rejection reason ' . $_POST['rejectionReasonName'],
			    'generic-rejection-reason',
			    'r_generic_sample_rejection_reasons',
			    'rejection_reason_id',
			    $activityBefore === null ? (int) $db->getInsertId() : $activityId,
			    'rejection_reason_name',
			    $activityBefore
			);
		}
	}
	header("Location:generic-sample-rejection-reasons.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
	throw $e;
}
