<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
  
$general = new \Vlsm\Models\General();
$tableName = "r_tb_results";
$primaryKey = "result_id";
// print_r(base64_decode($_POST['resultId']));die;
try {
	if (isset($_POST['resultName']) && trim($_POST['resultName']) != "") {
		$data = array(
			'result_id'     => $_POST['resultName'],
			'result_type' 		=> ucfirst($_POST['resultType']),
			'result' 		=> ucfirst($_POST['resultName']),
			'status' 	    => $_POST['resultStatus'],
			'updated_datetime' 	=> $general->getCurrentDateTime(),
		);
		if(isset($_POST['resultId']) && $_POST['resultId'] != ""){
			$db = $db->where($primaryKey, base64_decode($_POST['resultId']))->where('result', $_POST['oldResultName'])->where('result_type', $_POST['oldResultType']);
        	$lastId = $db->update($tableName, $data);
		} else{
			$lastId = $db->insert($tableName, $data);
		}

		if($lastId > 0){
            $_SESSION['alertMsg'] = _("TB Results details saved successfully");
            $general->activityLog('TB Results details', $_SESSION['userName'] . ' added new results for ' . $_POST['resultName'], 'tb-reference');
        }
	}
	header("location:tb-results.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
