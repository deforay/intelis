<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
ob_start();
require_once('../../startup.php');


$general = new \Vlsm\Models\General();
$tableName = "form_hepatitis";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";
$testTableName = 'hepatitis_tests';

$resultSentToSource = null;

try {
	//Set sample received date
	if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
		$sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
		$_POST['sampleReceivedDate'] = $general->dateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
	} else {
		$_POST['sampleReceivedDate'] = NULL;
	}

	if (isset($_POST['sampleTestedDateTime']) && trim($_POST['sampleTestedDateTime']) != "") {
		$sampleTestedDate = explode(" ", $_POST['sampleTestedDateTime']);
		$_POST['sampleTestedDateTime'] = $general->dateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
	} else {
		$_POST['sampleTestedDateTime'] = NULL;
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
		$_POST['reviewedOn'] = $general->dateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
	} else {
		$_POST['reviewedOn'] = NULL;
	}

	$hepatitisData = array(
		'sample_received_at_vl_lab_datetime'  => $_POST['sampleReceivedDate'],
		'lab_id'                              => isset($_POST['labId']) ? $_POST['labId'] : null,
		'sample_condition'  				  => isset($_POST['sampleCondition']) ? $_POST['sampleCondition'] : (isset($_POST['specimenQuality']) ? $_POST['specimenQuality'] : null),
		'sample_tested_datetime'  			  => isset($_POST['sampleTestedDateTime']) ? $_POST['sampleTestedDateTime'] : null,
		'vl_testing_site'  			  		  => isset($_POST['vlTestingSite']) ? $_POST['vlTestingSite'] : null,
		'is_sample_rejected'                  => isset($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,
		'result'                              => isset($_POST['result']) ? $_POST['result'] : null,
		'hcv_vl_result'                       => isset($_POST['hcv']) ? $_POST['hcv'] : null,
		'hbv_vl_result'                       => isset($_POST['hbv']) ? $_POST['hbv'] : null,
		'hcv_vl_count'                        => isset($_POST['hcvCount']) ? $_POST['hcvCount'] : null,
		'hbv_vl_count'                        => isset($_POST['hbvCount']) ? $_POST['hbvCount'] : null,
		'hepatitis_test_platform'             => isset($_POST['hepatitisPlatform']) ? $_POST['hepatitisPlatform'] : null,
		'import_machine_name'                 => isset($_POST['machineName']) ? $_POST['machineName'] : null,
		'is_result_authorised'                => isset($_POST['isResultAuthorized']) ? $_POST['isResultAuthorized'] : null,
		'result_reviewed_by' 				  => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
		'result_reviewed_datetime' 			  => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'authorized_by'                       => isset($_POST['authorizedBy']) ? $_POST['authorizedBy'] : null,
		'authorized_on' 					  => isset($_POST['authorizedOn']) ? $general->dateFormat($_POST['authorizedOn']) : null,
		'revised_by' 						  => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
		'revised_on' 						  => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $general->getDateTime() : "",
		'result_status'                       => 8,
		'result_sent_to_source'               => $resultSentToSource,
		'data_sync'                           => 0,
		'last_modified_by'                     => $_SESSION['userId'],
		'last_modified_datetime'               => $general->getDateTime(),
		'result_printed_datetime' 			  => NULL,
		'result_dispatched_datetime' 		  => NULL,
		'reason_for_vl_test'				  => isset($_POST['reasonVlTest']) ? $_POST['reasonVlTest'] : null,
	);

	$db = $db->where('hepatitis_id', $_POST['hepatitisSampleId']);
	$id = $db->update($tableName, $hepatitisData);
	if ($id > 0) {
		$_SESSION['alertMsg'] = "Hepatitis result updated successfully";
	} else {
		$_SESSION['alertMsg'] = "Please try again later";
	}
	//Add event log
	$eventType = 'update-hepatitis-result';
	$action = ucwords($_SESSION['userName']) . ' updated a result for the hepatitis sample no. ' . $_POST['sampleCode'];
	$resource = 'hepatitis-result';

	$general->activityLog($eventType, $action, $resource);

	$data = array(
		'user_id' => $_SESSION['userId'],
		'vl_sample_id' => $_POST['hepatitisSampleId'],
		'test_type' => 'hepatitis',
		'updated_on' => $general->getDateTime()
	);
	$db->insert($tableName2, $data);

	header("location:hepatitis-manual-results.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
