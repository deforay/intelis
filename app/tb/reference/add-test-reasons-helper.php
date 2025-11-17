<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;







/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);



$tableName = "r_tb_test_reasons";

try {
	if (isset($_POST['testReasonName']) && trim((string) $_POST['testReasonName']) !== "") {


		$data = ['test_reason_name' => $_POST['testReasonName'], 'parent_reason' => $_POST['parentReason'], 'test_reason_status' => $_POST['testReasonStatus'], 'updated_datetime' => DateUtility::getCurrentDateTime()];

		$db->insert($tableName, $data);
		$lastId = $db->getInsertId();

		$_SESSION['alertMsg'] = _translate("Test reasons details added successfully");
		$general->activityLog('add-test-reasons', $_SESSION['userName'] . ' added new reference test reasons' . $_POST['testReasonName'], 'reference-tb-test-reasons');
	}
	header("Location:tb-test-reasons.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
