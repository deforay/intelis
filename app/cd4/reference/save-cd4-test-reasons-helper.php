<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_cd4_test_reasons";
$primaryKey = "test_reason_id";
try {
	if (isset($_POST['testReasonName']) && trim((string) $_POST['testReasonName']) !== "") {
		$data = ['test_reason_name' 		=> $_POST['testReasonName'], 'parent_reason' 		=> (isset($_POST['parentReason']) && $_POST['parentReason'] != "") ? $_POST['parentReason'] : 0, 'test_reason_status'    => $_POST['testReasonStatus'], 'updated_datetime'  	=> DateUtility::getCurrentDateTime()];
		if (isset($_POST['testReasonId']) && $_POST['testReasonId'] != "") {
			$db->where($primaryKey, base64_decode((string) $_POST['testReasonId']));
			$lastId = $db->update($tableName, $data);
		} else {
			$data['data_sync'] = 0;
			$db->insert($tableName, $data);
			$lastId = $db->getInsertId();
		}

		if ($lastId > 0) {
			$_SESSION['alertMsg'] = _translate("CD4 Test Reason details saved successfully");
			$general->activityLog('CD4 Test Reason details', $_SESSION['userName'] . ' added new Test Reason for ' . $_POST['testReasonName'], 'cd4-reference');
		}
	}
	header("Location:cd4-test-reasons.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
}
