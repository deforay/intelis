<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

  

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "province_details";
$facilityTable = "facility_details";
$primaryKey = "province_id";

try {
	if (isset($_POST['provinceName']) && trim($_POST['provinceName']) != "") {

		$data = array(
			'province_name' 	=> $_POST['provinceName'],
			'province_code' 	=> $_POST['provinceCode'],
			'updated_datetime'	=> DateUtility::getCurrentDateTime()
		);
		if(isset($_POST['provinceId']) && $_POST['provinceId'] != ""){
			/* Update facility state */
			$facilityQuery = "SELECT facility_id, facility_state from $facilityTable where facility_state='" . $_POST['provinceNameOld'] . "' LIMIT 1";
			$facilityInfo = $db->query($facilityQuery);
			if (isset($facilityInfo[0]['facility_state']) && $facilityInfo[0]['facility_state'] != "") {
				$db = $db->where('facility_id', $facilityInfo[0]['facility_id']);
				$lastId = $db->update($facilityTable, array(
					'facility_state'	=> $_POST['provinceName'],
					'updated_datetime'	=> DateUtility::getCurrentDateTime()
				));
			}
			/* Update province details */
			$db = $db->where($primaryKey, base64_decode($_POST['provinceId']));
        	$lastId = $db->update($tableName, $data);
		} else{
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}
        if($lastId > 0){
            $_SESSION['alertMsg'] = "Province details saved successfully";
            $general->activityLog('Province details', $_SESSION['userName'] . ' added new province for ' . $_POST['provinceName'], 'common-reference');
        }
	}
	header("Location:province-details.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
