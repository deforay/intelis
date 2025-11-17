<?php

use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "form_covid19";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";
$testTableName = 'covid19_tests';

try {
	$covid19Data = [
		'sample_received_at_lab_datetime' => DateUtility::isoDateFormat($_POST['sampleReceivedDate'] ?? '', true),
		'lab_id' => $_POST['labId'] ?? null,
		'is_sample_rejected' => $_POST['isSampleRejected'] ?? null,
		'result' => $_POST['result'] ?? null,
		'is_result_authorised' => $_POST['isResultAuthorized'] ?? null,
		'authorized_by' => $_POST['authorizedBy'] ?? null,
		'authorized_on' => DateUtility::isoDateFormat($_POST['authorizedOn'] ?? ''),
		'reason_for_changing' => (empty($_POST['reasonForChanging'])) ? null : $_POST['reasonForChanging'],
		'rejection_on' => (isset($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
		'result_status' => RECEIVED_AT_TESTING_LAB,
		'data_sync' => 0,
		'reason_for_sample_rejection' => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
		'last_modified_by' => $_SESSION['userId'],
		'last_modified_datetime' => DateUtility::getCurrentDateTime()
	];


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
	if (isset($_POST['covid19SampleId']) && $_POST['covid19SampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
		if (!empty($_POST['testName'])) {
			foreach ($_POST['testName'] as $testKey => $testerName) {
				$covid19TestData = ['covid19_id' => $_POST['covid19SampleId'], 'test_name' => $_POST['testName'][$testKey], 'facility_id' => $_POST['labId'] ?? null, 'sample_tested_datetime' => DateUtility::isoDateFormat($_POST['testDate'][$testKey] ?? '', true), 'result' => $_POST['testResult'][$testKey]];
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
	} else {
		$db->where('covid19_id', $_POST['covid19SampleId']);
		$id = $db->delete($testTableName);
		$covid19Data['sample_tested_datetime'] = null;
	}
	$db->where('covid19_id', $_POST['covid19SampleId']);
	$id = $db->update($tableName, $covid19Data);

	$_SESSION['alertMsg'] = "Covid-19 result updated successfully";
	//Add event log
	$eventType = 'update-covid-19-result';
	$action = $_SESSION['userName'] . ' updated a result for the Covid-19 sample no. ' . $_POST['sampleCode'];
	$resource = 'covid-19-result';

	$general->activityLog($eventType, $action, $resource);

	$data = ['user_id' => $_SESSION['userId'], 'vl_sample_id' => $_POST['covid19SampleId'], 'test_type' => 'covid19', 'updated_datetime' => DateUtility::getCurrentDateTime()];
	$db->insert($tableName2, $data);

	header("Location:update-record-confirmatory-tests.php");
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
