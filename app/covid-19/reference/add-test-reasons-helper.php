<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

  

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);



$tableName = "r_covid19_test_reasons";

try {
	if (isset($_POST['testReasonName']) && trim($_POST['testReasonName']) != "") {


		$data = array(
            'test_reason_name' => $_POST['testReasonName'],
            'parent_reason' => $_POST['parentReason'],
			'test_reason_status' => $_POST['testReasonStatus'],
			'updated_datetime' => DateUtility::getCurrentDateTime(),
		);

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _("COVID 19 Test reasons details added successfully");
		$general->activityLog('add-test-reasons', $_SESSION['userName'] . ' added new reference test reasons' . $_POST['testReasonName'], 'reference-covid19-test-reasons');
	}
	header("Location:covid19-test-reasons.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
