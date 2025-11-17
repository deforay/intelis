<?php

use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\REJECTED;
use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);
$tableName = "form_generic";
$testTableName = "generic_test_results";
$vl_result_category = null;
$logVal = null;
$absDecimalVal = null;
$absVal = null;
$txtVal = null;
try {


    if (isset($_POST['subTestResult']) && is_array($_POST['subTestResult']) && !empty($_POST['subTestResult'][0])) {
        $_POST['subTestResult'] = implode("##", $_POST['subTestResult']);
    } else {
        $_POST['subTestResult'] = 'default';
    }

    $instanceId = '';
    if (isset($_SESSION['instanceId'])) {
        $instanceId = $_SESSION['instanceId'];
    }
    $testingPlatform = '';
    if (isset($_POST['testPlatform']) && trim((string) $_POST['testPlatform']) !== '') {
        $platForm = explode("##", (string) $_POST['testPlatform']);
        $testingPlatform = $platForm[0];
    }
    if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) !== "") {
        $sampleReceivedDateLab = explode(" ", (string) $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDateLab[0]) . " " . $sampleReceivedDateLab[1];
    } else {
        $_POST['sampleReceivedDate'] = null;
    }


    if (isset($_POST['sampleReceivedAtHubOn']) && trim((string) $_POST['sampleReceivedAtHubOn']) !== "") {
        $sampleReceivedAtHubOn = explode(" ", (string) $_POST['sampleReceivedAtHubOn']);
        $_POST['sampleReceivedAtHubOn'] = DateUtility::isoDateFormat($sampleReceivedAtHubOn[0]) . " " . $sampleReceivedAtHubOn[1];
    } else {
        $_POST['sampleReceivedAtHubOn'] = null;
    }

    if (isset($_POST['approvedOn']) && trim((string) $_POST['approvedOn']) !== "") {
        $approvedOn = explode(" ", (string) $_POST['approvedOn']);
        $_POST['approvedOn'] = DateUtility::isoDateFormat($approvedOn[0]) . " " . $approvedOn[1];
    } else {
        $_POST['approvedOn'] = null;
    }


    if (isset($_POST['sampleTestingDateAtLab']) && trim((string) $_POST['sampleTestingDateAtLab']) !== "") {
        $sampleTestingDateAtLab = explode(" ", (string) $_POST['sampleTestingDateAtLab']);
        $_POST['sampleTestingDateAtLab'] = DateUtility::isoDateFormat($sampleTestingDateAtLab[0]) . " " . $sampleTestingDateAtLab[1];
    } else {
        $_POST['sampleTestingDateAtLab'] = null;
    }
    if (isset($_POST['resultDispatchedOn']) && trim((string) $_POST['resultDispatchedOn']) !== "") {
        $resultDispatchedOn = explode(" ", (string) $_POST['resultDispatchedOn']);
        $_POST['resultDispatchedOn'] = DateUtility::isoDateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
    } else {
        $_POST['resultDispatchedOn'] = null;
    }

    if (isset($_POST['newRejectionReason']) && trim((string) $_POST['newRejectionReason']) !== "") {
        $rejectionReasonQuery = "SELECT rejection_reason_id FROM r_generic_sample_rejection_reasons where rejection_reason_name='" . $_POST['newRejectionReason'] . "' OR rejection_reason_name='" . strtolower((string) $_POST['newRejectionReason']) . "' OR rejection_reason_name='" . (strtolower((string) $_POST['newRejectionReason'])) . "'";
        $rejectionResult = $db->rawQuery($rejectionReasonQuery);
        if (!isset($rejectionResult[0]['rejection_reason_id'])) {
            $data = ['rejection_reason_name' => $_POST['newRejectionReason'], 'rejection_type' => 'general', 'rejection_reason_status' => 'active'];
            $id = $db->insert('r_generic_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $id;
        } else {
            $_POST['rejectionReason'] = $rejectionResult[0]['rejection_reason_id'];
        }
    }

    $isRejected = false;
    $resultStatus = PENDING_APPROVAL; // Awaiting Approval
    if (($_POST['isSampleRejected'] ?? null) === 'yes') {
        $isRejected = true;
        $resultStatus = REJECTED; // Rejected
    }

    $reasonForChanges = '';
    $allChange = '';
    if (isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] != '') {
        $allChange = $_POST['reasonForResultChangesHistory'];
    }
    if (isset($_POST['reasonForResultChanges']) && trim((string) $_POST['reasonForResultChanges']) !== '') {
        $reasonForChanges = $_SESSION['userName'] . '##' . $_POST['reasonForResultChanges'] . '##' . DateUtility::getCurrentDateTime();
    }
    if (!empty($allChange) && ($reasonForChanges !== '' && $reasonForChanges !== '0')) {
        $allChange = $reasonForChanges . 'vlsm' . $allChange;
    } elseif (trim($reasonForChanges) !== '') {
        $allChange = $reasonForChanges;
    }

    $_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true);
	$_POST['approvedOn'] = DateUtility::isoDateFormat($_POST['approvedOn'] ?? '', true);

    $interpretationResult = null;
    if (!empty($_POST['resultInterpretation'])) {
        foreach ($_POST['resultInterpretation'] as $row) {
            $interpretationResult = $row;
        }
    }

    $dataToUpdate = [
        'vlsm_instance_id' => $instanceId,
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '') ? $_POST['labId'] : null,
        'test_platform' => $testingPlatform,
        'sample_received_at_hub_datetime' => $_POST['sampleReceivedAtHubOn'],
        'sample_received_at_lab_datetime' => $_POST['sampleReceivedDate'],
        'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
        'reason_for_testing' => (isset($_POST['reasonForTesting']) && $_POST['reasonForTesting'] != '') ? $_POST['reasonForTesting'] : null,
        'result_dispatched_datetime' => empty($_POST['resultDispatchedOn']) ? null : $_POST['resultDispatchedOn'],
        'is_sample_rejected' => (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] != '') ? $_POST['isSampleRejected'] : null,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] : null,
        'rejection_on' => (empty($_POST['rejectionDate'])) ? null : DateUtility::isoDateFormat($_POST['rejectionDate']),
        'result' => $_POST['result'] ?: null,
        'final_result_interpretation' => $interpretationResult,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'testing_lab_focal_person' => (isset($_POST['vlFocalPerson']) && $_POST['vlFocalPerson'] != '') ? $_POST['vlFocalPerson'] : null,
        'testing_lab_focal_person_phone_number' => (isset($_POST['vlFocalPersonPhoneNumber']) && $_POST['vlFocalPersonPhoneNumber'] != '') ? $_POST['vlFocalPersonPhoneNumber'] : null,
        'tested_by' => (isset($_POST['testedBy']) && $_POST['testedBy'] != '') ? $_POST['testedBy'] : null,
        'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] : null,
        'result_approved_datetime' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedOn'] : null,
        'lab_tech_comments' => (isset($_POST['labComments']) && trim((string) $_POST['labComments']) !== '') ? trim((string) $_POST['labComments']) : null,
        'reason_for_test_result_changes' => $allChange,
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : null,
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'manual_result_entry' => 'yes',
        'result_status' => $resultStatus,
        'data_sync' => 0,
        'sub_tests' => (isset($_POST['subTestResult']) && is_array($_POST['subTestResult'])) ? implode("##", $_POST['subTestResult']) : $_POST['subTestResult'],
        'result_printed_datetime' => null
    ];


    if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
        $dataToUpdate['result_status'] = REJECTED;
    }

    if (isset($_POST['vlSampleId']) && $_POST['vlSampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
        $finalResult = "";
        if (!empty($_POST['testName'])) {
            $db->where('generic_id', $_POST['vlSampleId']);
            $db->delete('generic_test_results');
            if (isset($_POST['subTestResult']) && !empty($_POST['subTestResult'])) {
                foreach ($_POST['testName'] as $subTestName => $subTests) {
                    foreach ($subTests as $testKey => $testKitName) {
                        if (!empty($testKitName)) {
                            $testData = ['generic_id' => $_POST['vlSampleId'], 'sub_test_name' => $subTestName, 'result_type' => $_POST['resultType'][$subTestName], 'test_name' => ($testKitName == 'other') ? $_POST['testNameOther'][$subTestName][$testKey] : $testKitName, 'facility_id' => $_POST['labId'] ?? null, 'sample_tested_datetime' => DateUtility::isoDateFormat($_POST['testDate'][$subTestName][$testKey] ?? '', true), 'testing_platform' => $_POST['testingPlatform'][$subTestName][$testKey] ?? null, 'kit_lot_no' => (str_contains((string)$testKitName, 'RDT')) ? $_POST['lotNo'][$subTestName][$testKey] : null, 'kit_expiry_date' => (str_contains((string)$testKitName, 'RDT')) ? DateUtility::isoDateFormat($_POST['expDate'][$subTestName][$testKey]) : null, 'result_unit' => $_POST['testResultUnit'][$subTestName][$testKey], 'result' => $_POST['testResult'][$subTestName][$testKey], 'final_result' => $_POST['finalResult'][$subTestName], 'final_result_unit' => $_POST['finalTestResultUnit'][$subTestName], 'final_result_interpretation' => $_POST['resultInterpretation'][$subTestName]];
                            $db->insert('generic_test_results', $testData);
                            if (isset($_POST['finalResult'][$subTestName]) && !empty($_POST['finalResult'][$subTestName]) && !empty($finalResult)) {
                                $finalResult = $_POST['finalResult'][$subTestName];
                            } else {
                                foreach ($_POST['finalResult'] as $key => $value) {
                                    if (isset($value) && !empty($value) && empty($finalResult)) {
                                        $finalResult = $value;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($_POST['testName'] as $testKey => $testKitName) {
                    if (!empty($_POST['testName'][$testKey][0])) {
                        $testData = ['generic_id' => $_POST['vlSampleId'] ?? null, 'sub_test_name' => null, 'result_type' => $_POST['resultType'][$testKey][0] ?? null, 'test_name' => ($_POST['testName'][$testKey][0] == 'other') ? $_POST['testNameOther'][$testKey][0] : $_POST['testName'][$testKey][0], 'facility_id' => $_POST['labId'] ?? null, 'sample_tested_datetime' => (isset($_POST['testDate'][$testKey][0]) && !empty($_POST['testDate'][$testKey][0])) ? DateUtility::isoDateFormat($_POST['testDate'][$testKey][0]) : null, 'testing_platform' => $_POST['testingPlatform'][$testKey][0] ?? null, 'kit_lot_no' => (str_contains((string)$_POST['testName'][$testKey][0], 'RDT')) ? $_POST['lotNo'][$testKey][0] : null, 'kit_expiry_date' => (str_contains((string)$_POST['testName'][$testKey][0], 'RDT')) ? DateUtility::isoDateFormat($_POST['expDate'][$testKey][0]) : null, 'result_unit' => $_POST['testResultUnit'][$testKey][0] ?? null, 'result' => $_POST['testResult'][$testKey][0] ?? null];
                        foreach ($_POST['finalResult'] as $key => $value) {
                            if (isset($value) && !empty($value)) {
                                $testData['final_result'] = $value;
                            }
                            if (isset($_POST['finalTestResultUnit'][$key]) && !empty($_POST['finalTestResultUnit'][$key])) {
                                $testData['final_result_unit'] = $_POST['finalTestResultUnit'][$key];
                            }
                            if (isset($_POST['resultInterpretation'][$key]) && !empty($_POST['resultInterpretation'][$key])) {
                                $testData['final_result_interpretation'] = $_POST['resultInterpretation'][$key];
                            }
                        }
                        $db->insert('generic_test_results', $testData);
                        if (isset($testData['final_result']) && !empty($testData['final_result'])) {
                            $finalResult = $testData['final_result'];
                        }
                    }
                }
            }
        }
        $dataToUpdate['result'] = $finalResult;
    } else {
        $db->where('generic_id', $_POST['vlSampleId']);
        $db->delete('generic_test_results');
        $genericData['sample_tested_datetime'] = null;
    }

    $db->where('sample_id', $_POST['vlSampleId']);
    $id = $db->update($tableName, $dataToUpdate);

    $patientId = (isset($_POST['artNo']) && $_POST['artNo'] != '') ? ' and patient id ' . $_POST['artNo'] : '';
    if ($id === true) {
        $_SESSION['alertMsg'] = _translate("Lab Tests results updated successfully");

        $eventType = 'update-lab-test-result';
        $action = $_SESSION['userName'] . ' updated result for the sample id ' . $_POST['sampleCode'] . $patientId;
        $resource = 'lab-test-result';

        $general->activityLog($eventType, $action, $resource);
    } else {
        $_SESSION['alertMsg'] = _translate("Please try again later");
    }

    header("Location:generic-test-results.php");
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'last_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}
