<?php

use App\Models\General;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

  


$general = new General();



$tableName = "r_covid19_test_reasons";
$testReasonId = base64_decode($_POST['testReasonId']);

try {
	if (isset($_POST['testReasonName']) && trim($_POST['testReasonName']) != "") {


		$data = array(
            'test_reason_name' => $_POST['testReasonName'],
            'parent_reason' => $_POST['parentReason'],
			'test_reason_status' => $_POST['testReasonStatus'],
			'updated_datetime' => DateUtils::getCurrentDateTime(),
		);

        $db = $db->where('test_reason_id', $testReasonId);
        $db->update($tableName, $data);

		$_SESSION['alertMsg'] = "Test reason details updated successfully";
		$general->activityLog('update-test-reasons', $_SESSION['userName'] . ' updated new reference test reasons' . $_POST['test_reason_name'], 'reference-covid19-test-reasons');
	}
	header("location:covid19-test-reasons.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
