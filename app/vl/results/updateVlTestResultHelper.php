<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\VlService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$tableName = "form_vl";
$tableName2 = "log_result_updates";
$vl_result_category = null;
$vlResult = null;
$logVal = null;
$absDecimalVal = null;
$absVal = null;
$txtVal = null;
$finalResult = null;
try {
    $instanceId = $general->getInstanceId();
    $testingPlatform = null;
    if (isset($_POST['testingPlatform']) && trim($_POST['testingPlatform']) != '') {
        $platForm = explode("##", $_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
    }
    if (isset($_POST['sampleReceivedOn']) && trim($_POST['sampleReceivedOn']) != "") {
        $_POST['sampleReceivedOn'] = DateUtility::isoDateFormat($_POST['sampleReceivedOn'], true);
    } else {
        $_POST['sampleReceivedOn'] = null;
    }


    if (isset($_POST['sampleReceivedAtHubOn']) && trim($_POST['sampleReceivedAtHubOn']) != "") {
        $_POST['sampleReceivedAtHubOn'] = DateUtility::isoDateFormat($_POST['sampleReceivedAtHubOn'], true);
    } else {
        $_POST['sampleReceivedAtHubOn'] = null;
    }

    if (isset($_POST['approvedOnDateTime']) && trim($_POST['approvedOnDateTime']) != "") {
        $_POST['approvedOnDateTime'] = DateUtility::isoDateFormat($_POST['approvedOnDateTime'], true);
    } else {
        $_POST['approvedOnDateTime'] = null;
    }


    if (isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab']) != "") {
        $_POST['sampleTestingDateAtLab'] = DateUtility::isoDateFormat($_POST['sampleTestingDateAtLab'], true);
    } else {
        $_POST['sampleTestingDateAtLab'] = null;
    }
    if (isset($_POST['resultDispatchedOn']) && trim($_POST['resultDispatchedOn']) != "") {
        $_POST['resultDispatchedOn'] = DateUtility::isoDateFormat($_POST['resultDispatchedOn'], true);
    } else {
        $_POST['resultDispatchedOn'] = null;
    }

    if (!empty($_POST['newRejectionReason'])) {
        $rejectionReasonQuery = "SELECT rejection_reason_id
                    FROM r_vl_sample_rejection_reasons
                    WHERE rejection_reason_name like ?";
        $rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
        if (empty($rejectionResult)) {
            $data = array(
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active'
            );
            $id = $db->insert('r_vl_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $id;
        } else {
            $_POST['rejectionReason'] = $rejectionResult['rejection_reason_id'];
        }
    }

    if ($formId == '5') {
        $_POST['vlResult'] = $_POST['finalViralLoadResult'] ?? $_POST['cphlvlResult'] ?? $_POST['vlResult'] ?? null;
    }

    $isRejected = false;
    $finalResult = null;
    $resultStatus = 8; // Awaiting Approval
    if (isset($_POST['noResult']) && $_POST['noResult'] === 'yes') {
        $isRejected = true;
        $finalResult = $_POST['vlResult'] = $_POST['vlLog'] = null;
        $resultStatus = 4;
    }

    $_POST['result'] = null;
    if (!empty($_POST['vlResult'])) {

        $_POST['result'] = $_POST['vlResult'];
        $resultStatus = 8; // Awaiting Approval

        if (in_array(strtolower($_POST['vlResult']), ['bdl', 'below detection level'])) {
            $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?? 'Below Detection Level';
            $_POST['vlResult'] = 'Below Detection Level';
            $_POST['vlLog'] = null;
        } elseif (in_array(strtolower($_POST['vlResult']), ['fail', 'failed', 'failure'])) {
            $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?? 'Failed';
            $_POST['vlLog'] = null;
            $_POST['hivDetection'] = null;
            $resultStatus = 5; // Invalid/Failed
        } elseif (in_array(strtolower($_POST['vlResult']), ['error', 'err'])) {
            $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?? 'Error';
            $_POST['vlLog'] = null;
            $_POST['hivDetection'] = null;
            $resultStatus = 5; // Invalid/Failed
        } elseif (in_array(strtolower($_POST['vlResult']), ['no result', 'no'])) {
            $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?? 'No Result';
            $_POST['vlLog'] = null;
            $_POST['hivDetection'] = null;
            $resultStatus = 11; // No Result
        } else {

            $interpretedResults = $vlService->interpretViralLoadResult($_POST['vlResult']);

            //Result is saved as entered
            $finalResult  = $_POST['vlResult'];

            $logVal = $interpretedResults['logVal'] ?? null;
            $absDecimalVal = $interpretedResults['absDecimalVal'] ?? null;
            $absVal = $interpretedResults['absVal'] ?? null;
            $txtVal = $interpretedResults['txtVal'] ?? null;
        }
    } elseif (!empty($_POST['vlLog'])) {
        $resultStatus = 8; // Awaiting Approval
        $finalResult = $_POST['result'] = pow(10, $_POST['vlLog']);
    }

    $finalResult = (isset($_POST['hivDetection']) && $_POST['hivDetection'] != '') ? $_POST['hivDetection'] . ' ' . $finalResult :  $finalResult;


    $reasonForChanges = null;
    $allChange = null;
    if (isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] != '') {
        $allChange = $_POST['reasonForResultChangesHistory'];
    }
    if (isset($_POST['reasonForResultChanges']) && trim($_POST['reasonForResultChanges']) != '') {
        $reasonForChanges = $_SESSION['userName'] . '##' . $_POST['reasonForResultChanges'] . '##' . DateUtility::getCurrentDateTime();
    }
    if (!empty($allChange) && !empty($reasonForChanges)) {
        $allChange = $reasonForChanges . 'vlsm' . $allChange;
    } elseif (!empty($reasonForChanges)) {
        $allChange =  $reasonForChanges;
    }
    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'], true);
    } else {
        $_POST['reviewedOn'] = null;
    }

    /** For PNG */
    if (isset($_POST['failedTestDate']) && trim($_POST['failedTestDate']) != "") {
        $_POST['failedTestDate'] = DateUtility::isoDateFormat($_POST['failedTestDate'], true);
    } else {
        $_POST['failedTestDate'] = null;
    }

    if (isset($_POST['qcDate']) && trim($_POST['qcDate']) != "") {
        $_POST['qcDate'] = DateUtility::isoDateFormat($_POST['qcDate']);
    } else {
        $_POST['qcDate'] = null;
    }
    if (isset($_POST['reportDate']) && trim($_POST['reportDate']) != "") {
        $_POST['reportDate'] = DateUtility::isoDateFormat($_POST['reportDate']);
    } else {
        $_POST['reportDate'] = null;
    }
    if (isset($_POST['clinicDate']) && trim($_POST['clinicDate']) != "") {
        $_POST['clinicDate'] = DateUtility::isoDateFormat($_POST['clinicDate']);
    } else {
        $_POST['clinicDate'] = null;
    }

    if ($_POST['failedTestingTech'] != '') {
        $platForm = explode("##", $_POST['failedTestingTech']);
        $_POST['failedTestingTech'] = $platForm[0];
    }

    /** DRC */
    if (isset($_POST['dateOfCompletionOfViralLoad']) && trim($_POST['dateOfCompletionOfViralLoad']) != "") {
        $_POST['dateOfCompletionOfViralLoad'] = DateUtility::isoDateFormat($_POST['dateOfCompletionOfViralLoad'], true);
    } else {
        $_POST['dateOfCompletionOfViralLoad'] = null;
    }


    $vlData = array(
        'vlsm_instance_id' => $instanceId,
        'lab_id' => $_POST['labId'] ?? null,
        'vl_test_platform' => $testingPlatform ?? null,
        'sample_received_at_hub_datetime' => $_POST['sampleReceivedAtHubOn'],
        'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedOn'],
        'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
        'result_dispatched_datetime' => $_POST['resultDispatchedOn'] ?? null,
        'is_sample_rejected' => (isset($_POST['noResult']) && $_POST['noResult'] != '') ? $_POST['noResult'] :  null,
        'reason_for_sample_rejection' => $_POST['rejectionReason'] ?? null,
        'rejection_on' => DateUtility::isoDateFormat($_POST['rejectionDate']),
        'result_value_absolute'                 => $absVal ?? null,
        'result_value_absolute_decimal'         => $absDecimalVal ?? null,
        'result_value_text'                     => $txtVal ?? null,
        'cphl_vl_result'                                => $finalResult ?? null,
        'result'                                => $finalResult ?? null,
        'result_value_log'                      => $logVal ?? null,
        'result_value_hiv_detection' => $_POST['hivDetection'] ?? null,
        'reason_for_failure' => $_POST['reasonForFailure'] ?? null,
        'result_reviewed_by' => $_POST['reviewedBy'] ?? null,
        'result_reviewed_datetime' => $_POST['reviewedOn'] ?? null,
        'vl_focal_person' => $_POST['vlFocalPerson'] ?? null,
        'vl_focal_person_phone_number' => $_POST['vlFocalPersonPhoneNumber'] ?? null,
        'tested_by' => $_POST['testedBy'] ?? null,
        'result_approved_by' => $_POST['approvedBy'] ?? null,
        'result_approved_datetime' => $_POST['approvedOnDateTime'] ?? null,
        'lab_tech_comments' => $_POST['labComments'] ?? null,
        'reason_for_vl_result_changes' => $allChange ?? null,
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? ($_SESSION['userId'] ?? $_POST['userId']) : null,
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
        'last_modified_by' => $_SESSION['userId'] ?? $_POST['userId'],
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'manual_result_entry' => 'yes',
        'result_status' => $resultStatus,
        'data_sync' => 0,
        'result_printed_datetime' => null,
        'failed_test_date' => $_POST['failedTestDate'] ?? null,
        'qc_date' => $_POST['qcDate'] ?? null,
        'clinic_date' => $_POST['clinicDate'] ?? null,
        'report_date' => $_POST['reportDate'] ?? null,
        'batch_quality' => $_POST['batchQuality'] ?? null,
        'sample_test_quality' => $_POST['testQuality'] ?? null,
        'sample_batch_id' => $_POST['batchNo'] ?? null,
        'failed_test_tech' => $_POST['failedTestingTech'] ?? null,
        'failed_vl_result' => $_POST['failedvlResult'] ?? null,
        'failed_batch_quality' => $_POST['failedbatchQuality'] ?? null,
        'failed_sample_test_quality' => $_POST['failedtestQuality'] ?? null,
        'failed_batch_id' => $_POST['failedbatchNo'] ?? null,
        'tech_name_png' => $_POST['techName'] ?? null,
        'qc_tech_name' => $_POST['qcTechName'] ?? null,
        'qc_tech_sign' => $_POST['qcTechSign'] ?? null,
    );


    if (isset($_POST['noResult']) && $_POST['noResult'] == 'yes') {
        $vlData['result_status'] = 4;
    }

    $vlData['vl_result_category'] = $vlService->getVLResultCategory($vlData['result_status'], $vlData['result']);


    if ($vlData['vl_result_category'] == 'failed' || $vlData['vl_result_category'] == 'invalid') {
        $vlData['result_status'] = 5;
    } elseif ($vlData['vl_result_category'] == 'rejected') {
        $vlData['result_status'] = 4;
    }

    $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
    $id = $db->update($tableName, $vlData);
    if ($id === true) {
        $_SESSION['alertMsg'] = _("VL request updated successfully");
        //Log result updates
        $data = array(
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $_POST['vlSampleId'],
            'test_type' => 'vl',
            'updated_on' => DateUtility::getCurrentDateTime()
        );
        $db->insert($tableName2, $data);
    } else {
        $_SESSION['alertMsg'] = _("Please try again later");
    }

    header("Location:vlTestResult.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
