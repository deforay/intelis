<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

#require_once('../startup.php');  

$general = new \Vlsm\Models\General();

$tableName = "geographical_divisions";
$primaryKey = "geo_id";

try {
	if (isset($_POST['geoName']) && trim($_POST['geoName']) != "") {
		$lastId = 0;
		$data = array(
			'geo_name' 			=> $_POST['geoName'],
			'geo_code' 			=> $_POST['geoCode'],
			'geo_parent' 		=> (isset($_POST['geoParent']) && trim($_POST['geoParent']) != "") ? $_POST['geoParent'] : '',
			'geo_status' 		=> $_POST['geoStatus'],
			'updated_datetime'	=> $general->getDateTime()
		);
		if (isset($_POST['geoId']) && $_POST['geoId'] != "") {
			$db = $db->where($primaryKey, base64_decode($_POST['geoId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['created_by'] = $_SESSION['userId'];
			$data['created_on'] = $general->getDateTime();
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
		if ($lastId > 0) {
			$_SESSION['alertMsg'] = "Geographical Divisions details saved successfully";
			$general->activityLog('Geographical Divisions details', $_SESSION['userName'] . ' added new geographical divisions for ' . $_POST['geoName'], 'common-reference');
		}
	}
	header("location:geographical-divisions-details.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
