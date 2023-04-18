<?php

use App\Models\General;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

  


$general = new General();



$tableName = "r_tb_sample_type";

try {
	if (isset($_POST['sampleName']) && trim($_POST['sampleName']) != "") {


		$data = array(
			'sample_name' => $_POST['sampleName'],
			'status' => $_POST['sampleStatus'],
			'updated_datetime' => DateUtils::getCurrentDateTime(),
		);

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _("Sample Type details added successfully");
		$general->activityLog('add-sample-type', $_SESSION['userName'] . ' added new reference sample type' . $_POST['sampleName'], 'reference-tb-sample-type');
	}
	header("location:tb-sample-type.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
