<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_hepatitis_sample_type";
$primaryKey = "sample_id";
try {
	if (isset($_POST['sampleName']) && trim((string) $_POST['sampleName']) !== "") {
		$data = ['sample_name' => $_POST['sampleName'], 'status' => $_POST['sampleStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
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
			$general->activityLog('Hepatitis Sample Type details', $_SESSION['userName'] . ' added new Hepatitis sample type : ' . $_POST['sampleName'], 'hepatitis-reference');
		}
	}
	header("Location:hepatitis-sample-type.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
