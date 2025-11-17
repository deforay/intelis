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



$tableName = "r_covid19_symptoms";

try {
	if (isset($_POST['symptomsName']) && trim((string) $_POST['symptomsName']) !== "") {

		$data = ['symptom_name' => $_POST['symptomsName'], 'parent_symptom' => $_POST['parentSymptom'], 'symptom_status' => $_POST['symptomsStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _translate("Symptom details added successfully");
		$general->activityLog('add-symptoms', $_SESSION['userName'] . ' added new reference symptom' . $_POST['symptomsName'], 'reference-covid19-symptoms');
	}
	header("Location:covid19-symptoms.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
