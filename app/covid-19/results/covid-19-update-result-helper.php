<?php

use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\REJECTED;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$tableName = "form_covid19";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";
$testTableName = 'covid19_tests';
try {
	//Set sample received date
	if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) !== "") {
		$sampleReceivedDate = explode(" ", (string) $_POST['sampleReceivedDate']);
		$_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
	} else {
		$_POST['sampleReceivedDate'] = null;
	}

	if (isset($_POST['sampleTestedDateTime']) && trim((string) $_POST['sampleTestedDateTime']) !== "") {
		$sampleTestedDate = explode(" ", (string) $_POST['sampleTestedDateTime']);
		$_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
	} else {
		$_POST['sampleTestedDateTime'] = null;
	}

	$resultSentToSource = null;

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['result'] = null;
		$resultSentToSource = 'pending';
	}

	if (!empty($_POST['newRejectionReason'])) {
		$rejectionReasonQuery = "SELECT rejection_reason_id
					FROM r_covid19_sample_rejection_reasons
					WHERE rejection_reason_name like ?";
		$rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
		if (empty($rejectionResult)) {
			$data = ['rejection_reason_name' => $_POST['newRejectionReason'], 'rejection_type' => 'general', 'rejection_reason_status' => 'active', 'updated_datetime' => DateUtility::getCurrentDateTime()];
			$id = $db->insert('r_covid19_sample_rejection_reasons', $data);
			$_POST['sampleRejectionReason'] = $id;
		} else {
			$_POST['sampleRejectionReason'] = $rejectionResult['rejection_reason_id'];
		}
	}

	if (!empty($_POST['result'])) {
		$resultSentToSource = 'pending';
	}

	$_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true);
	$_POST['approvedOn'] = DateUtility::isoDateFormat($_POST['approvedOn'] ?? '', true);
	$_POST['authorizedOn'] = DateUtility::isoDateFormat($_POST['authorizedOn'] ?? '', true);



	$covid19Data = [
		'sample_received_at_lab_datetime' => $_POST['sampleReceivedDate'],
		'lab_id' => $_POST['labId'] ?? null,
		'sample_condition' => $_POST['specimenQuality'] ?? ($_POST['specimenQuality'] ?? null),
		'lab_technician' => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] : null,
		'testing_point' => $_POST['testingPoint'] ?? null,
		'is_sample_rejected' => ($_POST['isSampleRejected'] ?? null),
		'result' => $_POST['result'] ?? null,
		'result_sent_to_source' => $resultSentToSource,
		'other_diseases' => (isset($_POST['otherDiseases']) && $_POST['result'] != 'positive') ? $_POST['otherDiseases'] : null,
		'tested_by' => $_POST['testedBy'] ?? null,
		'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] : null,
		'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] : null,
		'is_result_authorised' => $_POST['isResultAuthorized'] ?? null,
		'authorized_by' => $_POST['authorizedBy'] ?? null,
		'authorized_on' => (isset($_POST['authorizedOn']) && $_POST['authorizedOn'] != '') ? $_POST['authorizedOn'] : null,
		'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
		'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
		'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
		'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'reason_for_changing' => (empty($_POST['reasonForChanging'])) ? null : $_POST['reasonForChanging'],
		'rejection_on' => (isset($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
		'result_status' => PENDING_APPROVAL,
		'data_sync' => 0,
		'reason_for_sample_rejection' => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
		'recommended_corrective_action' => (isset($_POST['correctiveAction']) && trim((string) $_POST['correctiveAction']) !== '') ? $_POST['correctiveAction'] : null,
		'last_modified_by' => $_SESSION['userId'],
		'result_printed_datetime' => null,
		'result_dispatched_datetime' => null,
		'last_modified_datetime' => DateUtility::getCurrentDateTime()
	];

	$db->where('covid19_id', $_POST['covid19SampleId']);
	$getPrevResult = $db->getOne('form_covid19');
	if ($getPrevResult['result'] != "" && $getPrevResult['result'] != $_POST['result']) {
		$covid19Data['result_modified'] = "yes";
	} else {
		$covid19Data['result_modified'] = "no";
	}

	if (!empty($_POST['labId'])) {
		$facility = $facilitiesService->getFacilityById($_POST['labId']);
		if (isset($facility['contact_person']) && $facility['contact_person'] != "") {
			$covid19Data['lab_manager'] = $facility['contact_person'];
		}
	}
	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$covid19Data['result'] = null;
		$covid19Data['result_status'] = REJECTED;
	}
	if (isset($_POST['deletedRow']) && trim((string) $_POST['deletedRow']) !== '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
		$deleteRows = explode(',', (string) $_POST['deletedRow']);
		foreach ($deleteRows as $delete) {
			$db->where('test_id', base64_decode($delete));
			$id = $db->delete($testTableName);
		}
	}
	// echo "<pre>";print_r($_POST);die;
	if (isset($_POST['covid19SampleId']) && $_POST['covid19SampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
		if (!empty($_POST['testName'])) {
			foreach ($_POST['testName'] as $testKey => $testName) {
				if (trim((string) $_POST['testName'][$testKey]) !== "") {
					$covid19TestData = ['covid19_id' => $_POST['covid19SampleId'], 'test_name' => ($_POST['testName'][$testKey] == 'other') ? $_POST['testNameOther'][$testKey] : $_POST['testName'][$testKey], 'facility_id' => $_POST['labId'] ?? null, 'sample_tested_datetime' => DateUtility::isoDateFormat($_POST['testDate'][$testKey] ?? '', true), 'testing_platform' => $_POST['testingPlatform'][$testKey] ?? null, 'kit_lot_no' => (str_contains((string)$testName, 'RDT')) ? $_POST['lotNo'][$testKey] : null, 'kit_expiry_date' => (str_contains((string)$testName, 'RDT')) ? DateUtility::isoDateFormat($_POST['expDate'][$testKey]) : null, 'result' => $_POST['testResult'][$testKey]];
					if (isset($_POST['testId'][$testKey]) && $_POST['testId'][$testKey] != '') {
						$db->where('test_id', base64_decode((string) $_POST['testId'][$testKey]));
						$db->update($testTableName, $covid19TestData);
					} else {
						$db->insert($testTableName, $covid19TestData);
					}
					$covid19Data['sample_tested_datetime'] = DateUtility::isoDateFormat($_POST['testDate'][$testKey] ?? '', true);
					$covid19Data['covid19_test_platform'] = $_POST['testingPlatform'][$testKey];
					$covid19Data['covid19_test_name'] = $_POST['testName'][$testKey];
				}
			}
		}
	} else {
		$db->where('covid19_id', $_POST['covid19SampleId']);
		$id = $db->delete($testTableName);
		$covid19Data['sample_tested_datetime'] = null;
	}
	/* echo "<pre>";
		  print_r($covid19Data);die; */
	$db->where('covid19_id', $_POST['covid19SampleId']);
	$id = $db->update($tableName, $covid19Data);
	if ($id === true) {
		$_SESSION['alertMsg'] = _translate("Covid-19 result updated successfully");
	} else {
		$_SESSION['alertMsg'] = _translate("Please try again later");
	}
	//Add event log
	$eventType = 'update-covid-19-result';
	$action = $_SESSION['userName'] . ' updated result for the sample id ' . $_POST['sampleCode'] . ' and patientId ' . $_POST['patientId'];
	$resource = 'covid-19-result';

	$general->activityLog($eventType, $action, $resource);

	$data = ['user_id' => $_SESSION['userId'], 'vl_sample_id' => $_POST['covid19SampleId'], 'test_type' => 'covid19', 'updated_datetime' => DateUtility::getCurrentDateTime()];
	$db->insert($tableName2, $data);

	header("Location:covid-19-manual-results.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
