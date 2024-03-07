<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_hepatitis_sample_type";
$primaryKey = "sample_id";
try {
	if (isset($_POST['sampleName']) && trim((string) $_POST['sampleName']) != "") {
		$data = array(
			'sample_name'         => $_POST['sampleName'],
			'status'             => $_POST['sampleStatus'],
			'updated_datetime'     => DateUtility::getCurrentDateTime(),
		);
		if (isset($_POST['sampleId']) && $_POST['sampleId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['sampleId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Hepatitis Sample details saved successfully");
			$general->activityLog('Hepatitis Sample Type details', $_SESSION['userName'] . ' added new sample type for ' . $_POST['sampleName'], 'hepatitis-reference');
		}
	}
	header("Location:hepatitis-sample-type.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => __FILE__,
		'line' => __LINE__,
		'trace' => $e->getTraceAsString(),
	]);
}
