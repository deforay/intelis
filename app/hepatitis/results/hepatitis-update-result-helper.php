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
$tableName = "form_hepatitis";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";
$testTableName = 'hepatitis_tests';

$resultSentToSource = null;

try {
	//Set sample received date
	if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
		$sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
		$_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
	} else {
		$_POST['sampleReceivedDate'] = null;
	}

	if (isset($_POST['sampleTestedDateTime']) && trim($_POST['sampleTestedDateTime']) != "") {
		$sampleTestedDate = explode(" ", $_POST['sampleTestedDateTime']);
		$_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
	} else {
		$_POST['sampleTestedDateTime'] = null;
	}



	$resultSentToSource = 'pending';

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['hcvCount'] = null;
		$_POST['hbvCount'] = null;
		$resultSentToSource = 'pending';
	} else if (empty($_POST['hcvCount']) && empty($_POST['hbvCount'])) {
		$resultSentToSource = null;
	}

	if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
		$reviewedOn = explode(" ", $_POST['reviewedOn']);
		$_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
	} else {
		$_POST['reviewedOn'] = null;
	}

	$hepatitisData = array(
		'sample_received_at_vl_lab_datetime'  => $_POST['sampleReceivedDate'],
		'lab_id'                              => $_POST['labId'] ?? null,
		'sample_condition'  				  => $_POST['sampleCondition'] ?? ($_POST['specimenQuality'] ?? null),
		'sample_tested_datetime'  			  => $_POST['sampleTestedDateTime'] ?? null,
		'vl_testing_site'  			  		  => $_POST['vlTestingSite'] ?? null,
		'is_sample_rejected'                  => $_POST['isSampleRejected'] ?? null,
		'result'                              => $_POST['result'] ?? null,
		'hcv_vl_result'                       => $_POST['hcv'] ?? null,
		'hbv_vl_result'                       => $_POST['hbv'] ?? null,
		'hcv_vl_count'                        => $_POST['hcvCount'] ?? null,
		'hbv_vl_count'                        => $_POST['hbvCount'] ?? null,
		'hepatitis_test_platform'             => $_POST['hepatitisPlatform'] ?? null,
		'import_machine_name'                 => $_POST['machineName'] ?? null,
		'is_result_authorised'                => $_POST['isResultAuthorized'] ?? null,
		'result_reviewed_by' 				  => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
		'result_reviewed_datetime' 			  => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'authorized_by'                       => $_POST['authorizedBy'] ?? null,
		'authorized_on' 					  => isset($_POST['authorizedOn']) ? DateUtility::isoDateFormat($_POST['authorizedOn']) : null,
		'revised_by' 						  => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : null,
		'revised_on' 						  => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
		'result_status'                       => 8,
		'result_sent_to_source'               => $resultSentToSource,
		'data_sync'                           => 0,
		'last_modified_by'                     => $_SESSION['userId'],
		'last_modified_datetime'               => DateUtility::getCurrentDateTime(),
		'result_printed_datetime' 			  => null,
		'result_dispatched_datetime' 		  => null,
		'reason_for_vl_test'				  => $_POST['reasonVlTest'] ?? null,
	);

	$db = $db->where('hepatitis_id', $_POST['hepatitisSampleId']);
	$id = $db->update($tableName, $hepatitisData);
	error_log ($db->getLastError() . PHP_EOL);
	if ($id > 0) {
		$_SESSION['alertMsg'] = _("Hepatitis result updated successfully");
	} else {
		$_SESSION['alertMsg'] = _("Please try again later");
	}
	//Add event log
	$eventType = 'update-hepatitis-result';
	$action = $_SESSION['userName'] . ' updated a result for the hepatitis sample no. ' . $_POST['sampleCode'];
	$resource = 'hepatitis-result';

	$general->activityLog($eventType, $action, $resource);

	// $data = array(
	// 	'user_id' => $_SESSION['userId'],
	// 	'vl_sample_id' => $_POST['hepatitisSampleId'],
	// 	'test_type' => 'hepatitis',
	// 	'updated_on' => \App\Utilities\DateUtility::getCurrentDateTime()
	// );
	// $db->insert($tableName2, $data);
	error_log ($db->getLastError() . PHP_EOL);

	header("Location:hepatitis-manual-results.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
