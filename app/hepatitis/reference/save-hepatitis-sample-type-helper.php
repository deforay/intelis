<?php

use App\Models\General;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
  
$general = new General();
$tableName = "r_hepatitis_sample_type";
$primaryKey = "sample_id";
try {
	if (isset($_POST['sampleName']) && trim($_POST['sampleName']) != "") {
		$data = array(
			'sample_name' 		=> $_POST['sampleName'],
			'status' 			=> $_POST['sampleStatus'],
			'updated_datetime' 	=> DateUtils::getCurrentDateTime(),
		);
		if(isset($_POST['sampleId']) && $_POST['sampleId'] != ""){
			$db = $db->where($primaryKey, base64_decode($_POST['sampleId']));
        	$lastId = $db->update($tableName, $data);
		} else{
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if($lastId > 0){
            $_SESSION['alertMsg'] = _("Hepatitis Sample details saved successfully");
            $general->activityLog('Hepatitis Sample Type details', $_SESSION['userName'] . ' added new sample type for ' . $_POST['sampleName'], 'hepatitis-reference');
        }
	}
	header("location:hepatitis-sample-type.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
