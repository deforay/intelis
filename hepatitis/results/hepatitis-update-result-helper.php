<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
ob_start();
require_once('../../startup.php');


$general = new \Vlsm\Models\General($db);
$tableName = "form_hepatitis";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";
$testTableName = 'hepatitis_tests';

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



	$hepatitisData = array(
		'sample_received_at_vl_lab_datetime'  => $_POST['sampleReceivedDate'],
		'lab_id'                              => isset($_POST['labId']) ? $_POST['labId'] : null,
		'sample_condition'  				  => isset($_POST['sampleCondition']) ? $_POST['sampleCondition'] : (isset($_POST['specimenQuality']) ? $_POST['specimenQuality'] : null),
		'sample_tested_datetime'  			  => isset($_POST['sampleTestedDateTime']) ? $_POST['sampleTestedDateTime'] : null,
		'vl_testing_site'  			  		  => isset($_POST['vlTestingSite']) ? $_POST['vlTestingSite'] : null,
		'result'                              => isset($_POST['result']) ? $_POST['result'] : null,
		'hcv_vl_result'                       => isset($_POST['hcv']) ? $_POST['hcv'] : null,
		'hbv_vl_result'                       => isset($_POST['hbv']) ? $_POST['hbv'] : null,
		'hcv_vl_count'                        => isset($_POST['hcvCount']) ? $_POST['hcvCount'] : null,
		'hbv_vl_count'                        => isset($_POST['hbvCount']) ? $_POST['hbvCount'] : null,
		'is_result_authorised'                => isset($_POST['isResultAuthorized']) ? $_POST['isResultAuthorized'] : null,
		'authorized_by'                       => isset($_POST['authorizedBy']) ? $_POST['authorizedBy'] : null,
		'authorized_on' 					  => isset($_POST['authorizedOn']) ? $general->dateFormat($_POST['authorizedOn']) : null,
		'result_status'                       => 8,
		'data_sync'                           => 0,
		'last_modified_by'                    => $_SESSION['userId'],
		'last_modified_datetime'              => $general->getDateTime()
	);

	$db = $db->where('hepatitis_id', $_POST['hepatitisSampleId']);
	$id = $db->update($tableName, $hepatitisData);
	if($id > 0){
		$_SESSION['alertMsg'] = "Hepatitis result updated successfully";
	} else{
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
