<?php

use App\Services\CommonService;
use App\Utilities\DateUtils;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

  


$general = new CommonService();



$tableName = "r_covid19_sample_type";
$sampleId = base64_decode($_POST['sampleId']);

try {
	if (isset($_POST['sampleName']) && trim($_POST['sampleName']) != "") {


		$data = array(
			'sample_name' => $_POST['sampleName'],
			'status' => $_POST['sampleStatus'],
			'updated_datetime' => DateUtils::getCurrentDateTime(),
		);

        $db = $db->where('sample_id', $sampleId);
        $db->update($tableName, $data);

		$_SESSION['alertMsg'] = "Sample details updated successfully";
		$general->activityLog('update-sample-type', $_SESSION['userName'] . ' updated new reference sample type' . $_POST['sampleName'], 'reference-covid19-sample type');
	}
	header("Location:covid19-sample-type.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
