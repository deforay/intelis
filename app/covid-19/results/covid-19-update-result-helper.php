<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}


/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$facilityDb = \App\Registries\ContainerRegistry::get(FacilitiesService::class);

$tableName = "form_covid19";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";
$testTableName = 'covid19_tests';
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

	$resultSentToSource = null;

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['result'] = null;
		$resultSentToSource = 'pending';
	}

	if (!empty($_POST['result'])) {
		$resultSentToSource = 'pending';
	}

	if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
		$reviewedOn = explode(" ", $_POST['reviewedOn']);
		$_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
	} else {
		$_POST['reviewedOn'] = null;
	}

	$covid19Data = array(
		'sample_received_at_vl_lab_datetime'  => $_POST['sampleReceivedDate'],
		'lab_id'                              => $_POST['labId'] ?? null,
		'sample_condition'  				  => $_POST['sampleCondition'] ?? ($_POST['specimenQuality'] ?? null),
		'lab_technician' 					  => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  null,
		'testing_point'                       => $_POST['testingPoint'] ?? null,
		'is_sample_rejected'                  => $_POST['isSampleRejected'] ?? null,
		'result'                              => $_POST['result'] ?? null,
		'result_sent_to_source'               => $resultSentToSource,
		'other_diseases'        			  => (isset($_POST['otherDiseases']) && $_POST['result'] != 'positive') ? $_POST['otherDiseases'] : null,
		'tested_by'                       	  => $_POST['testedBy'] ?? null,
		'result_approved_by' 				  => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  null,
		'result_approved_datetime' 			  => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] :  null,
		'is_result_authorised'                => $_POST['isResultAuthorized'] ?? null,
		'authorized_by'                       => $_POST['authorizedBy'] ?? null,
		'authorized_on' 					  => isset($_POST['authorizedOn']) ? DateUtility::isoDateFormat($_POST['authorizedOn']) : null,
		'revised_by' 						  => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
		'revised_on' 						  => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
		'result_reviewed_by' 				  => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
		'result_reviewed_datetime' 			  => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'reason_for_changing'				  => (isset($_POST['reasonForChanging']) && !empty($_POST['reasonForChanging'])) ? $_POST['reasonForChanging'] : null,
		'rejection_on'	 					  => (isset($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
		'result_status'                       => 8,
		'data_sync'                           => 0,
		'reason_for_sample_rejection'         => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
		'last_modified_by'                     => $_SESSION['userId'],
		'result_printed_datetime' 			  => null,
		'result_dispatched_datetime' 		  => null,
		'last_modified_datetime'               => DateUtility::getCurrentDateTime()
	);

	if (!empty($_POST['labId'])) {
		$facility = $facilityDb->getFacilityById($_POST['labId']);
		if (isset($facility['contact_person']) && $facility['contact_person'] != "") {
			$covid19Data['lab_manager'] = $facility['contact_person'];
		}
	}
	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$covid19Data['result'] = null;
		$covid19Data['result_status'] = 4;
	}
	if (isset($_POST['deletedRow']) && trim($_POST['deletedRow']) != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
		$deleteRows = explode(',', $_POST['deletedRow']);
		foreach ($deleteRows as $delete) {
			$db = $db->where('test_id', base64_decode($delete));
			$id = $db->delete($testTableName);
		}
	}
	// echo "<pre>";print_r($_POST);die;
	if (isset($_POST['covid19SampleId']) && $_POST['covid19SampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
		if (isset($_POST['testName']) && count($_POST['testName']) > 0) {
			foreach ($_POST['testName'] as $testKey => $testName) {
				if (trim($_POST['testName'][$testKey]) != "") {
					if (isset($_POST['testDate'][$testKey]) && trim($_POST['testDate'][$testKey]) != "") {
						$testedDateTime = explode(" ", $_POST['testDate'][$testKey]);
						$_POST['testDate'][$testKey] = DateUtility::isoDateFormat($testedDateTime[0]) . " " . $testedDateTime[1];
					} else {
						$_POST['testDate'][$testKey] = null;
					}
					$covid19TestData = array(
						'covid19_id'			=> $_POST['covid19SampleId'],
						'test_name'				=> ($_POST['testName'][$testKey] == 'other') ? $_POST['testNameOther'][$testKey] : $_POST['testName'][$testKey],
						'facility_id'           => $_POST['labId'] ?? null,
						'sample_tested_datetime' => $_POST['testDate'][$testKey],
						'testing_platform'      => $_POST['testingPlatform'][$testKey] ?? null,
						'kit_lot_no'      		=> (strpos($testName, 'RDT') !== false) ? $_POST['lotNo'][$testKey] : null,
						'kit_expiry_date'      	=> (strpos($testName, 'RDT') !== false) ? DateUtility::isoDateFormat($_POST['expDate'][$testKey]) : null,
						'result'				=> $_POST['testResult'][$testKey],
					);
					if (isset($_POST['testId'][$testKey]) && $_POST['testId'][$testKey] != '') {
						$db = $db->where('test_id', base64_decode($_POST['testId'][$testKey]));
						$db->update($testTableName, $covid19TestData);
					} else {
						$db->insert($testTableName, $covid19TestData);
					}
					$covid19Data['sample_tested_datetime'] = date('Y-m-d H:i:s', strtotime($_POST['testDate'][$testKey]));
					$covid19Data['covid19_test_platform'] = $_POST['testingPlatform'][$testKey];
					$covid19Data['covid19_test_name'] = $_POST['testName'][$testKey];
				}
			}
		}
	} else {
		$db = $db->where('covid19_id', $_POST['covid19SampleId']);
		$id = $db->delete($testTableName);
		$covid19Data['sample_tested_datetime'] = null;
	}
	/* echo "<pre>";
	print_r($covid19Data);die; */
	$db = $db->where('covid19_id', $_POST['covid19SampleId']);
	$id = $db->update($tableName, $covid19Data);
	if ($id > 0) {
		$_SESSION['alertMsg'] = _("Covid-19 result updated successfully");
	} else {
		$_SESSION['alertMsg'] = _("Please try again later");
	}
	//Add event log
	$eventType = 'update-covid-19-result';
	$action = $_SESSION['userName'] . ' updated a result for the Covid-19 sample Code/ID. ' . $_POST['sampleCode'] . ' (' . $_POST['covid19SampleId'] . ')';
	$resource = 'covid-19-result';

	$general->activityLog($eventType, $action, $resource);

	$data = array(
		'user_id' => $_SESSION['userId'],
		'vl_sample_id' => $_POST['covid19SampleId'],
		'test_type' => 'covid19',
		'updated_on' => DateUtility::getCurrentDateTime()
	);
	$db->insert($tableName2, $data);

	header("Location:covid-19-manual-results.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
