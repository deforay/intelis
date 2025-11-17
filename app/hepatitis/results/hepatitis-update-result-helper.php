<?php

use const SAMPLE_STATUS\PENDING_APPROVAL;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "form_hepatitis";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";
$testTableName = 'hepatitis_tests';

$resultSentToSource = null;

try {
	//Set sample received date

	$resultSentToSource = 'pending';

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
     $_POST['hcvCount'] = $_POST['hbvCount'] = null;
     $resultSentToSource = 'pending';
 } elseif (empty($_POST['hcvCount']) && empty($_POST['hbvCount'])) {
     $resultSentToSource = null;
 }

	$hepatitisData = [
		'sample_received_at_lab_datetime' => DateUtility::isoDateFormat($_POST['sampleReceivedDate'] ?? '', true),
		'lab_id' => $_POST['labId'] ?? null,
		'sample_condition' => $_POST['sampleCondition'] ?? ($_POST['specimenQuality'] ?? null),
		'sample_tested_datetime' => DateUtility::isoDateFormat($_POST['sampleTestedDateTime'] ?? '', true),
		'vl_testing_site' => $_POST['vlTestingSite'] ?? null,
		'is_sample_rejected' => ($_POST['isSampleRejected'] ?? null),
		'result' => $_POST['result'] ?? null,
		'hcv_vl_count' => $_POST['hcvCount'] ?? null,
		'hbv_vl_count' => $_POST['hbvCount'] ?? null,
		'hepatitis_test_platform' => $_POST['hepatitisPlatform'] ?? null,
		'import_machine_name' => $_POST['machineName'] ?? null,
		'is_result_authorised' => $_POST['isResultAuthorized'] ?? null,
		'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
		'result_reviewed_datetime' => DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true),
		'authorized_by' => $_POST['authorizedBy'] ?? null,
		'authorized_on' => isset($_POST['authorizedOn']) ? DateUtility::isoDateFormat($_POST['authorizedOn']) : null,
		'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : null,
		'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
		'result_status' => PENDING_APPROVAL,
		'result_sent_to_source' => $resultSentToSource,
		'data_sync' => 0,
		'last_modified_by' => $_SESSION['userId'],
		'last_modified_datetime' => DateUtility::getCurrentDateTime(),
		'result_printed_datetime' => null,
		'result_dispatched_datetime' => null,
		'reason_for_vl_test' => $_POST['reasonVlTest'] ?? null,
	];

	$db->where('hepatitis_id', $_POST['hepatitisSampleId']);
	$getPrevResult = $db->getOne('form_hepatitis');
	if ($getPrevResult['result'] != "" && $getPrevResult['result'] != $_POST['result']) {
		$hepatitisData['result_modified'] = "yes";
	} else {
		$hepatitisData['result_modified'] = "no";
	}

	$db->where('hepatitis_id', $_POST['hepatitisSampleId']);
	$id = $db->update($tableName, $hepatitisData);
	error_log($db->getLastError() . PHP_EOL);
	if ($id === true) {
		$_SESSION['alertMsg'] = _translate("Hepatitis result updated successfully");
	} else {
		$_SESSION['alertMsg'] = _translate("Please try again later");
	}
	//Add event log
	$eventType = 'update-hepatitis-result';
	$action = $_SESSION['userName'] . ' updated a result for the hepatitis sample no. ' . $_POST['sampleCode'];
	$resource = 'hepatitis-result';

	$general->activityLog($eventType, $action, $resource);

	header("Location:hepatitis-manual-results.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
