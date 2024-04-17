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
	if (isset($_POST['sampleName']) && trim((string) $_POST['sampleName']) != "") {


		$data = array(
			'sample_name' => $_POST['sampleName'],
			'status' => $_POST['sampleStatus'],
			'updated_datetime' => DateUtility::getCurrentDateTime(),
		);

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _translate("Sample Type details added successfully");
		$general->activityLog('add-sample-type', $_SESSION['userName'] . ' added new reference sample type' . $_POST['sampleName'], 'reference-covid19-sample-type');
	}
	header("Location:covid19-sample-type.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => __FILE__,
		'line' => __LINE__,
		'trace' => $e->getTraceAsString(),
	]);
}
