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
$tableName = "r_hepatitis_comorbidities";
$primaryKey = "comorbidity_id";
// print_r($_POST);die;
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
			$general->activityLog('Hepatitis Comorbidity details', $_SESSION['userName'] . ' added new comorbidity for ' . $_POST['comorbidityName'], 'hepatitis-reference');
		}
	}
	header("Location:hepatitis-comorbidities.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
