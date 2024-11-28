<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\SecurityService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_vl_test_reasons";
$primaryKey = "test_reason_id";
try {
	if (isset($_POST['testReasonName']) && trim((string) $_POST['testReasonName']) != "") {
		$data = array(
			'test_reason_name' 		=> $_POST['testReasonName'],
			'parent_reason' 		=> (isset($_POST['parentReason']) && $_POST['parentReason'] != "") ? $_POST['parentReason'] : 0,
			'test_reason_status'    => $_POST['testReasonStatus'],
			'updated_datetime'  	=> DateUtility::getCurrentDateTime(),
		);
		if (isset($_POST['testReasonId']) && $_POST['testReasonId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['testReasonId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("VL Test Reason details saved successfully");
			$general->activityLog('VL Test Reason details', $_SESSION['userName'] . ' added new Test Reason for ' . $_POST['testReasonName'], 'vl-reference');
		}
	}
	MiscUtility::redirect("/vl/reference/vl-test-reasons.php");
} catch (Throwable $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
