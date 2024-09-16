<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;



// echo "<pre>";print_r($_POST);die;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_vl_sample_rejection_reasons";
$primaryKey = "rejection_reason_id";

try {
	if (isset($_POST['rejectionReasonName']) && trim((string) $_POST['rejectionReasonName']) != "") {

		$data = array(
			'rejection_reason_name' 	=> $_POST['rejectionReasonName'],
			'rejection_type' 			=> $_POST['rejectionType'],
			'rejection_reason_code'	=> $_POST['rejectionReasonCode'],
			'rejection_reason_status' 	=> $_POST['rejectionReasonStatus'],
			'updated_datetime' 			=> DateUtility::getCurrentDateTime()
		);

		if (isset($_POST['rejectionReasonId']) && $_POST['rejectionReasonId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['rejectionReasonId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("VL Sample Rejection Reasons details added successfully");
			$general->activityLog('VL Sample Rejection Reasons For VL', $_SESSION['userName'] . ' added new reference Sample Rejection Reasons for VL  ' . $_POST['rejectionReasonName'], 'vl-reference');
		}
	}
	header("Location:/vl/reference/vl-sample-rejection-reasons.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
