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



$tableName = "r_covid19_comorbidities";

try {
	if (isset($_POST['comorbidityName']) && trim((string) $_POST['comorbidityName']) !== "") {


		$data = ['comorbidity_name' => $_POST['comorbidityName'], 'comorbidity_status' => $_POST['comorbidityStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _translate("Comorbidity details added successfully");
		$general->activityLog('add-comorbidity', $_SESSION['userName'] . ' added new reference comorbidity ' . $_POST['comorbidityName'], 'reference-covid19-comorbidity');
	}
	header("Location:covid19-comorbidities.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
