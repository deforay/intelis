<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}



/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "r_vl_sample_type";
$primaryKey = "sample_id";

try {
	if (isset($_POST['sampleName']) && trim($_POST['sampleName']) != "") {

		$data = array(
			'sample_name' => $_POST['sampleName'],
			'status' => $_POST['sampleStatus'],
			'updated_datetime' => DateUtility::getCurrentDateTime(),
		);
		if (isset($_POST['sampleId']) && $_POST['sampleId'] != "") {
			$db = $db->where($primaryKey, base64_decode($_POST['sampleId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _("VL Sample details saved successfully");
			$general->activityLog('VL Sample Type details', $_SESSION['userName'] . ' added new sample type for ' . $_POST['sampleName'], 'vl-reference');
		}
	}
	header("Location:vl-sample-type.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
