<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_hepatitis_risk_factors";
$primaryKey = "riskfactor_id";
// print_r($_POST);die;
try {
	if (isset($_POST['riskFactorName']) && trim((string) $_POST['riskFactorName']) != "") {
		$data = array(
			'riskfactor_name'         => $_POST['riskFactorName'],
			'riskfactor_status'     => $_POST['riskFactorStatus'],
			'updated_datetime'     => DateUtility::getCurrentDateTime(),
		);
		if (isset($_POST['riskFactorId']) && $_POST['riskFactorId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['riskFactorId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("Hepatitis Risk Factor details saved successfully");
			$general->activityLog('Hepatitis Risk Factor details', $_SESSION['userName'] . ' added new risk factor for ' . $_POST['riskFactorName'], 'hepatitis-reference');
		}
	}
	header("Location:hepatitis-risk-factors.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
