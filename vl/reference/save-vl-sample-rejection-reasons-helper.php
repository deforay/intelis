<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
// echo "<pre>";print_r($_POST);die;
#require_once('../startup.php');  
$general = new \Vlsm\Models\General($db);
$tableName = "r_sample_rejection_reasons";
$primaryKey = "rejection_reason_id";

try {
	if (isset($_POST['rejectionReasonName']) && trim($_POST['rejectionReasonName']) != "") {

		$data = array(
			'rejection_reason_name' 	=> $_POST['rejectionReasonName'],
            'rejection_type' 			=> $_POST['rejectionType'],
            'rejection_reason_status'	=> $_POST['rejectionReasonCode'],
            'rejection_reason_code' 	=> $_POST['rejectionReasonStatus'],
			'updated_datetime' 			=> $general->getDateTime()
		);

		if(isset($_POST['rejectionReasonId']) && $_POST['rejectionReasonId'] != ""){
			$db = $db->where($primaryKey, base64_decode($_POST['rejectionReasonId']));
        	$lastId = $db->update($tableName, $data);
		} else{
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
        if($lastId > 0){
			$_SESSION['alertMsg'] = "Viral Load Sample Rejection Reasons details added successfully";
			$general->activityLog('Save Sample Rejection Reasons For VL', $_SESSION['userName'] . ' added new reference Sample Rejection Reasons for VL  ' . $_POST['rejectionReasonName'], 'vl-reference');
		}
	}
	header("location:vl-sample-rejection-reasons.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
