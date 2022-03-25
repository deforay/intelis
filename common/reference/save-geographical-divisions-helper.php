<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

#require_once('../startup.php');

$db = MysqliDb::getInstance();

$general = new \Vlsm\Models\General();
$provinceTable = "province_details";
try {
	if (isset($_POST['geoName']) && trim($_POST['geoName']) != "") {
		$lastId = 0;
		$data = array(
			'geo_name' 			=> $_POST['geoName'],
			'geo_code' 			=> $_POST['geoCode'],
			'geo_parent' 		=> (isset($_POST['geoParent']) && trim($_POST['geoParent']) != "") ? $_POST['geoParent'] : 0,
			'geo_status' 		=> $_POST['geoStatus'],
			'updated_datetime'	=> $db->now()
		);
		if (isset($_POST['geoId']) && $_POST['geoId'] != "") {
			$db = $db->where("geo_id", base64_decode($_POST['geoId']));
			$lastId = $db->update("geographical_divisions", $data);
			$geoId = base64_decode($_POST['geoId']);
		} else {
			$data['created_by'] = $_SESSION['userId'];
			$data['created_on'] = $general->getDateTime();
			$data['data_sync'] = 0;
			$db->insert("geographical_divisions", $data);
			$lastId = $db->getInsertId();
			$geoId = $lastId;
		}
		if (!isset($data['geo_parent']) || $data['geo_parent'] == 0) {

			$provinceData = array(
				'province_id' => $geoId,
				'province_name' => $_POST['geoName'],
				'province_code' => $_POST['geoCode'],
				'updated_datetime' => $db->now()
			);

			$updateColumns = array_keys($provinceData);
			$lastInsertId = "province_id";
			$db->onDuplicate($updateColumns, $lastInsertId);
			$id = $db->insert($provinceTable, $provinceData);
		}
		if ($lastId > 0) {

			$facilityData = array();
			if ($data['geo_parent'] == 0) {
				$facilityData['facility_state'] = $data['geo_name'];
				$facilityData['facility_state_id'] = $data['geo_id'];
				$db->where("facility_state", $data['geo_name']);
				$db->where("facility_state_id", $data['geo_id']);
			} else {
				$facilityData['facility_state_id'] = $data['geo_parent'];
				$facilityData['facility_district'] = $data['geo_name'];
				$facilityData['facility_district_id'] = $data['geo_id'];
				$db->where('facility_state', $data['geo_parent']);
				$db->where("facility_district", $data['geo_name']);
				$db->where("facility_district_id", $data['geo_id']);
			}
			$db->update("facility_details", $facilityData);

			$_SESSION['alertMsg'] = _("Geographical Divisions details saved successfully");
			$general->activityLog('Geographical Divisions details', $_SESSION['userName'] . ' saved geographical division - ' . $_POST['geoName'], 'common-reference');
		}
	}
	header("location:geographical-divisions-details.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
