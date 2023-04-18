<?php

use App\Models\General;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
  
$general = new General();
$tableName = "r_covid19_results";
$primaryKey = "result_id";
// print_r(base64_decode($_POST['resultId']));die;
try {
	if (isset($_POST['resultName']) && trim($_POST['resultName']) != "") {
		$data = array(
			'result' 		=> ($_POST['resultName']),
			'status' 	    => $_POST['resultStatus'],
			'updated_datetime' 	=> DateUtils::getCurrentDateTime(),
		);
		if(isset($_POST['resultId']) && $_POST['resultId'] != ""){
			$db = $db->where($primaryKey, base64_decode($_POST['resultId']));
        	$lastId = $db->update($tableName, $data);
		} else{
			$lastId = $db->insert($tableName, $data);
		}

		if($lastId > 0){
            $_SESSION['alertMsg'] = _("Covid-19 Results details saved successfully");
            $general->activityLog('Covid-19 Results details', $_SESSION['userName'] . ' added new results for ' . $_POST['resultName'], 'covid19-reference');
        }
	}
	header("location:covid19-results.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
