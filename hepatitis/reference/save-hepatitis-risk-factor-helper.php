<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
#require_once('../startup.php');  
$general = new \Vlsm\Models\General();
$tableName = "r_hepatitis_risk_factors";
$primaryKey = "riskfactor_id";
// print_r($_POST);die;
try {
	if (isset($_POST['riskFactorName']) && trim($_POST['riskFactorName']) != "") {
		$data = array(
			'riskfactor_name' 		=> $_POST['riskFactorName'],
			'riskfactor_status' 	=> $_POST['riskFactorStatus'],
			'updated_datetime' 	=> $general->getDateTime(),
		);
		if(isset($_POST['riskFactorId']) && $_POST['riskFactorId'] != ""){
			$db = $db->where($primaryKey, base64_decode($_POST['riskFactorId']));
        	$lastId = $db->update($tableName, $data);
		} else{
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if($lastId > 0){
            $_SESSION['alertMsg'] = "Hepatitis Risk Factor details saved successfully";
            $general->activityLog('Hepatitis Risk Factor details', $_SESSION['userName'] . ' added new risk factor for ' . $_POST['riskFactorName'], 'hepatitis-reference');
        }
	}
	header("location:hepatitis-results.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
