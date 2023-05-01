<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\VlService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}




/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$tableName = "form_vl";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";
$vl_result_category = null;
$isRejected = false;
$logVal = null;
$absDecimalVal = null;
$absVal = null;
$txtVal = null;
$finalResult = null;
$resultStatus = 8; // Awaiting Approval

try {

    $reasonForChanges = '';
    $allChange = '';
    if (isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] != '') {
        $allChange = $_POST['reasonForResultChangesHistory'];
    }
    if (isset($_POST['reasonForResultChanges']) && trim($_POST['reasonForResultChanges']) != '') {
        $reasonForChanges = $_SESSION['userName'] . '##' . $_POST['reasonForResultChanges'] . '##' . DateUtility::getCurrentDateTime();
    }
    if (trim($allChange) != '' && trim($reasonForChanges) != '') {
        $allChange = $reasonForChanges . 'vlsm' . $allChange;
    } else if (trim($reasonForChanges) != '') {
        $allChange =  $reasonForChanges;
    }

    //Set sample received date
    if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
        $sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['sampleReceivedDate'] = null;
    }

    //Set result prinetd date time
    if (isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab']) != "") {
        $sampleTestingDateLab = explode(" ", $_POST['sampleTestingDateAtLab']);
        $_POST['sampleTestingDateAtLab'] = DateUtility::isoDateFormat($sampleTestingDateLab[0]) . " " . $sampleTestingDateLab[1];
    } else {
        $_POST['sampleTestingDateAtLab'] = null;
    }
    //Set sample testing date
    if (isset($_POST['dateOfCompletionOfViralLoad']) && trim($_POST['dateOfCompletionOfViralLoad']) != "") {
        $dateofCompletionofViralLoad = explode(" ", $_POST['dateOfCompletionOfViralLoad']);
        $_POST['dateOfCompletionOfViralLoad'] = DateUtility::isoDateFormat($dateofCompletionofViralLoad[0]) . " " . $dateofCompletionofViralLoad[1];
    } else {
        $_POST['dateOfCompletionOfViralLoad'] = null;
    }
    //if(!isset($_POST['sampleCode']) || trim($_POST['sampleCode'])== ''){
    //    $_POST['sampleCode'] = null;
    //}
    $testingPlatform = '';
    if (isset($_POST['testingPlatform']) && trim($_POST['testingPlatform']) != '') {
        $platForm = explode("##", $_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
    }

    if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
        $isRejected = true;
        $finalResult = $_POST['vlResult'] = null;
        $_POST['vlLog'] = null;
        $resultStatus = 4;

        if (trim($_POST['rejectionReason']) == "other" && trim($_POST['newRejectionReason'] != '')) {
            $data = array(
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_reason_status' => 'active'
            );
            $id = $db->insert('r_vl_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $id;
        }
    }

    if (isset($_POST['vlResult']) && $_POST['vlResult'] == 'Below Detection Level' && $isRejected === false) {
        $_POST['vlResult'] = 'Below Detection Level';
        $_POST['vlLog'] = null;
    } else if ((isset($_POST['vlResult']) && $_POST['vlResult'] == 'Failed') || in_array(strtolower($_POST['vlResult']), ['fail', 'failed', 'failure'])) {
        $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?: 'Failed';
        $_POST['vlLog'] = null;
        $_POST['hivDetection'] = null;
        $resultStatus = 5; // Invalid/Failed
    } else if ((isset($_POST['vlResult']) && $_POST['vlResult'] == 'Error') || in_array(strtolower($_POST['vlResult']), ['error', 'err'])) {
        $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?: 'Error';
        $_POST['vlLog'] = null;
        $_POST['hivDetection'] = null;
        $resultStatus = 5; // Invalid/Failed
    } else if ((isset($_POST['vlResult']) && $_POST['vlResult'] == 'No Result') || in_array(strtolower($_POST['vlResult']), ['no result', 'no'])) {
        $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?: 'No Result';
        $_POST['vlLog'] = null;
        $_POST['hivDetection'] = null;
        $resultStatus = 11; // No Result
    } else if (isset($_POST['vlResult']) && trim(!empty($_POST['vlResult']))) {

        $resultStatus = 8; // Awaiting Approval

        $interpretedResults = $vlService->interpretViralLoadResult($_POST['vlResult']);

        //Result is saved as entered
        $finalResult  = $_POST['vlResult'];

        $logVal = $interpretedResults['logVal'];
        $absDecimalVal = $interpretedResults['absDecimalVal'];
        $absVal = $interpretedResults['absVal'];
        $txtVal = $interpretedResults['txtVal'];
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = null;
    }
    $vldata = array(
        'is_sample_rejected' => (isset($_POST['noResult']) && $_POST['noResult'] != '') ? $_POST['noResult'] :  null,
        'reason_for_sample_rejection' => $_POST['rejectionReason'] ?: null,
        'rejection_on' => (isset($_POST['rejectionDate']) && $_POST['noResult'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
        'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedDate'],
        'sample_tested_datetime' => $_POST['dateOfCompletionOfViralLoad'],
        'result_value_hiv_detection' => (isset($_POST['hivDetection']) && $_POST['hivDetection'] != '') ? $_POST['hivDetection'] :  null,
        'reason_for_failure' => (isset($_POST['reasonForFailure']) && $_POST['reasonForFailure'] != '') ? $_POST['reasonForFailure'] :  null,
        'vl_test_platform' => $testingPlatform,
        'result_value_absolute'                 => $absVal ?: null,
        'result_value_absolute_decimal'         => $absDecimalVal ?: null,
        'result_value_text'                     => $txtVal ?: null,
        'result'                                => $finalResult ?: null,
        'result_value_log'                      => $logVal ?: null,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '' ? $_POST['labId'] : null),
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
        'result_dispatched_datetime' => null,
        'reason_for_vl_result_changes' => $allChange,
        'last_modified_datetime' => $db->now(),
        'manual_result_entry' => 'yes',
        'result_status' => $resultStatus,
        'data_sync' => 0,
        'result_printed_datetime' => null
    );
    // if (isset($_POST['status']) && trim($_POST['status']) != '') {
    //     $vldata['result_status'] = $_POST['status'];
    //     //if(isset($_POST['rejectionReason'])){
    //     $vldata['reason_for_sample_rejection'] = $_POST['rejectionReason'];
    //     //}
    // }
    /* Updating the high and low viral load data */

    $vldata['vl_result_category'] = $vlService->getVLResultCategory($vldata['result_status'], $vldata['result']);

    if ($vldata['vl_result_category'] == 'failed' || $vldata['vl_result_category'] == 'invalid') {
        $vldata['result_status'] = 5;
    } elseif ($vldata['vl_result_category'] == 'rejected') {
        $vldata['result_status'] = 4;
    }

    $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
    $db->update($tableName, $vldata);
    $_SESSION['alertMsg'] = "VL result updated successfully";
    //Add event log
    $eventType = 'update-vl-result-drc';
    $action = $_SESSION['userName'] . ' updated a result data with the patient code ' . $_POST['dubPatientArtNo'];
    $resource = 'vl-result-drc';

    $general->activityLog($eventType, $action, $resource);

    //  $data=array(
    // 'event_type'=>$eventType,
    // 'action'=>$action,
    // 'resource'=>$resource,
    // 'date_time'=>\App\Utilities\DateUtility::getCurrentDateTime()
    // );
    // $db->insert($tableName1,$data);
    //Add update result log
    $data = array(
        'user_id' => $_SESSION['userId'],
        'vl_sample_id' => $_POST['vlSampleId'],
        'test_type' => 'vl',
        'updated_on' => DateUtility::getCurrentDateTime()
    );
    $db->insert($tableName2, $data);
    header("Location:vlTestResult.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
