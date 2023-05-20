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
$tableName = "r_eid_test_reasons";
$primaryKey = "test_reason_id";
try {
	if (isset($_POST['testReasonName']) && trim($_POST['testReasonName']) != "") {
		$data = array(
			'test_reason_name' 		=> $_POST['testReasonName'],
			'parent_reason' 		=> (isset($_POST['parentReason']) && $_POST['parentReason'] != "") ? $_POST['parentReason'] : 0,
			'test_reason_status'    => $_POST['testReasonStatus'],
			'updated_datetime'  	=> DateUtility::getCurrentDateTime(),
		);
		if (isset($_POST['testReasonId']) && $_POST['testReasonId'] != "") {
			$db = $db->where($primaryKey, base64_decode($_POST['testReasonId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _("EID Test Reason details saved successfully");
			$general->activityLog('EID Test Reason details', $_SESSION['userName'] . ' added new Test Reason for ' . $_POST['testReasonName'], 'eid-reference');
		}
	}
	header("Location:eid-test-reasons.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
