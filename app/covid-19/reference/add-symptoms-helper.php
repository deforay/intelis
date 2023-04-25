<?php

use App\Services\CommonService;
use App\Utilities\DateUtils;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

  


$general = new CommonService();



$tableName = "r_covid19_symptoms";

try {
	if (isset($_POST['symptomsName']) && trim($_POST['symptomsName']) != "") {

		$data = array(
            'symptom_name' => $_POST['symptomsName'],
            'parent_symptom' => $_POST['parentSymptom'],
			'symptom_status' => $_POST['symptomsStatus'],
			'updated_datetime' => DateUtils::getCurrentDateTime(),
		);

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _("Symptom details added successfully");
		$general->activityLog('add-symptoms', $_SESSION['userName'] . ' added new reference symptom' . $_POST['symptomsName'], 'reference-covid19-symptoms');
	}
	header("Location:covid19-symptoms.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
