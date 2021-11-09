<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

#require_once('../startup.php');  

$general = new \Vlsm\Models\General();

$tableName = "r_funding_sources";
$primaryKey = "funding_source_id";

try {
	if (isset($_POST['fundingSrcName']) && trim($_POST['fundingSrcName']) != "") {

		$data = array(
			'funding_source_name' 	=> $_POST['fundingSrcName'],
			'funding_source_status' => $_POST['fundingStatus'],
			'updated_datetime'		=> $general->getDateTime()
		);
		if(isset($_POST['fundingId']) && $_POST['fundingId'] != ""){
			$db = $db->where($primaryKey, base64_decode($_POST['fundingId']));
        	$lastId = $db->update($tableName, $data);
		} else{
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
        if($lastId > 0){
            $_SESSION['alertMsg'] = "Funding Source saved successfully";
            $general->activityLog('Funding Source', $_SESSION['userName'] . ' added new Funding Source for ' . $_POST['fundingSrcName'], 'common-reference');
        }
	}
	header("location:funding-sources.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
