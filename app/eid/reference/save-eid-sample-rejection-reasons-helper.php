<?php

use App\Services\CommonService;
use App\Utilities\DateUtils;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
  
$general = new CommonService();
$tableName = "r_eid_sample_rejection_reasons";
$primaryKey = "rejection_reason_id";

try {
	if (isset($_POST['rejectionReasonName']) && trim($_POST['rejectionReasonName']) != "") {

		$data = array(
			'rejection_reason_name' 	=> $_POST['rejectionReasonName'],
            'rejection_type' 			=> $_POST['rejectionType'],
            'rejection_reason_status'	=> $_POST['rejectionReasonStatus'],
            'rejection_reason_code' 	=> $_POST['rejectionReasonCode'],
			'updated_datetime' 			=> DateUtils::getCurrentDateTime()
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
			$_SESSION['alertMsg'] = _("EID Sample Rejection Reasons details added successfully");
			$general->activityLog('EID Sample Rejection Reasons', $_SESSION['userName'] . ' added new reference Sample Rejection Reasons for  ' . $_POST['rejectionReasonName'], 'eid-reference');
		}
	}
	header("Location:eid-sample-rejection-reasons.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
