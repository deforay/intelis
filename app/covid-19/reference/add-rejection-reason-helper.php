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



$tableName = "r_covid19_sample_rejection_reasons";

try {
	if (isset($_POST['rejectionReasonName']) && trim($_POST['rejectionReasonName']) != "") {

		$data = array(
			'rejection_reason_name' => $_POST['rejectionReasonName'],
			'rejection_type' => $_POST['rejectionType'],
			'rejection_reason_status' => $_POST['rejectionReasonStatus'],
			'rejection_reason_code' => $_POST['rejectionReasonCode'],
			'updated_datetime' => DateUtility::getCurrentDateTime(),
		);

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _("Covid-19 Sample Rejection Reasons details added successfully");
		$general->activityLog('add-Sample Rejection Reasons', $_SESSION['userName'] . ' added new reference Sample Rejection Reasons ' . $_POST['rejectionReasonName'], 'reference-covid19-Sample Rejection Reasons');
	}
	header("Location:covid19-sample-rejection-reasons.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
