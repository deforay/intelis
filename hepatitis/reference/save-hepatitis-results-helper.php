<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
  
$general = new \Vlsm\Models\General();
$tableName = "r_hepatitis_results";
$primaryKey = "result_id";
// print_r(base64_decode($_POST['resultId']));die;
try {
	if (isset($_POST['resultName']) && trim($_POST['resultName']) != "") {
		$data = array(
            'result_id'     => strtolower($_POST['resultName']),
			'result' 		=> ucfirst($_POST['resultName']),
			'status' 	    => $_POST['resultStatus'],
			'updated_datetime' 	=> $general->getDateTime(),
		);
		if(isset($_POST['resultId']) && $_POST['resultId'] != ""){
			$db = $db->where($primaryKey, base64_decode($_POST['resultId']))->where('result', $_POST['oldResultName']);
        	$lastId = $db->update($tableName, $data);
		} else{
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if($lastId > 0){
            $_SESSION['alertMsg'] = _("Hepatitis Results details saved successfully");
            $general->activityLog('Hepatitis Results details', $_SESSION['userName'] . ' added new results for ' . $_POST['resultName'], 'hepatitis-reference');
        }
	}
	header("location:hepatitis-results.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
