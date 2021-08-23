<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

#require_once('../startup.php');  


$general = new \Vlsm\Models\General($db);



$tableName = "r_covid19_sample_type";

try {
	if (isset($_POST['sampleName']) && trim($_POST['sampleName']) != "") {


		$data = array(
			'sample_name' => $_POST['sampleName'],
			'status' => $_POST['sampleStatus'],
			'updated_datetime' => $general->getDateTime(),
		);

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = "Sample Type details added successfully";
		$general->activityLog('add-sample-type', $_SESSION['userName'] . ' added new reference sample type' . $_POST['sampleName'], 'reference-covid19-sample-type');
	}
	header("location:covid19-sample-type.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
