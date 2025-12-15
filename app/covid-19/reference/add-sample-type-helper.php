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


$tableName = "r_covid19_sample_type";

try {
	if (isset($_POST['sampleName']) && trim((string) $_POST['sampleName']) !== "") {


		$data = ['sample_name' => $_POST['sampleName'], 'status' => $_POST['sampleStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _translate("Sample Type details added successfully");
		$general->activityLog('add-sample-type', $_SESSION['userName'] . ' added new Covid-19 sample type : ' . $_POST['sampleName'], 'reference-covid19-sample-type');
	}
	header("Location:covid19-sample-type.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
