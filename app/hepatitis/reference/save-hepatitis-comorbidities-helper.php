<?php

use App\Models\General;
use App\Utilities\DateUtils;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
  
$general = new General();
$tableName = "r_hepatitis_comorbidities";
$primaryKey = "comorbidity_id";
// print_r($_POST);die;
try {
	if (isset($_POST['comorbidityName']) && trim($_POST['comorbidityName']) != "") {
		$data = array(
			'comorbidity_name' 		=> $_POST['comorbidityName'],
			'comorbidity_status' 	=> $_POST['comorbidityStatus'],
			'updated_datetime' 	=> DateUtils::getCurrentDateTime(),
		);
		if(isset($_POST['comorbidityId']) && $_POST['comorbidityId'] != ""){
			$db = $db->where($primaryKey, base64_decode($_POST['comorbidityId']));
        	$lastId = $db->update($tableName, $data);
		} else{
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if($lastId > 0){
            $_SESSION['alertMsg'] = _("Hepatitis Comorbidity details saved successfully");
            $general->activityLog('Hepatitis Comorbidity details', $_SESSION['userName'] . ' added new comorbidity for ' . $_POST['comorbidityName'], 'hepatitis-reference');
        }
	}
	header("Location:hepatitis-comorbidities.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
