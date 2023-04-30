<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

  


/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);



$tableName = "r_covid19_comorbidities";

try {
	if (isset($_POST['comorbidityName']) && trim($_POST['comorbidityName']) != "") {


		$data = array(
			'comorbidity_name' => $_POST['comorbidityName'],
			'comorbidity_status' => $_POST['comorbidityStatus'],
			'updated_datetime' => DateUtility::getCurrentDateTime(),
		);

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _("Comorbidity details added successfully");
		$general->activityLog('add-comorbidity', $_SESSION['userName'] . ' added new reference comorbidity ' . $_POST['comorbidityName'], 'reference-covid19-comorbidity');
	}
	header("Location:covid19-comorbidities.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
