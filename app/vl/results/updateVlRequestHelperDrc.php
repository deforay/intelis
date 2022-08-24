<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();



$general = new \Vlsm\Models\General();
$vlModel = new \Vlsm\Models\Vl();

$tableName = "form_vl";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";
$vl_result_category = NULL;
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
        $reasonForChanges = $_SESSION['userName'] . '##' . $_POST['reasonForResultChanges'] . '##' . $general->getCurrentDateTime();
    }
    if (trim($allChange) != '' && trim($reasonForChanges) != '') {
        $allChange = $reasonForChanges . 'vlsm' . $allChange;
    } else if (trim($reasonForChanges) != '') {
        $allChange =  $reasonForChanges;
    }

    //Set sample received date
    if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
        $sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = $general->isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['sampleReceivedDate'] = NULL;
    }

    //Set result prinetd date time
    if (isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab']) != "") {
        $sampleTestingDateLab = explode(" ", $_POST['sampleTestingDateAtLab']);
        $_POST['sampleTestingDateAtLab'] = $general->isoDateFormat($sampleTestingDateLab[0]) . " " . $sampleTestingDateLab[1];
    } else {
        $_POST['sampleTestingDateAtLab'] = NULL;
    }
    //Set sample testing date
    if (isset($_POST['dateOfCompletionOfViralLoad']) && trim($_POST['dateOfCompletionOfViralLoad']) != "") {
        $dateofCompletionofViralLoad = explode(" ", $_POST['dateOfCompletionOfViralLoad']);
        $_POST['dateOfCompletionOfViralLoad'] = $general->isoDateFormat($dateofCompletionofViralLoad[0]) . " " . $dateofCompletionofViralLoad[1];
    } else {
        $_POST['dateOfCompletionOfViralLoad'] = NULL;
    }
    //if(!isset($_POST['sampleCode']) || trim($_POST['sampleCode'])== ''){
    //    $_POST['sampleCode'] = NULL;
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

    if (isset($_POST['vlTND']) && $_POST['vlTND'] == 'yes' && $isRejected == false) {
        $finalResult = $_POST['vlResult'] = 'Target not Detected';
        $_POST['vlLog'] = '';
    } else if (isset($_POST['vlLt20']) && $_POST['vlLt20'] == 'yes' && $isRejected == false) {
        $finalResult = $_POST['vlResult'] = '< 20';
        $_POST['vlLog'] = '';
    } else if (isset($_POST['vlLt40']) && $_POST['vlLt40'] == 'yes' && $isRejected == false) {
        $finalResult = $_POST['vlResult'] = '< 40';
        $_POST['vlLog'] = '';
    } else if (isset($_POST['vlLt400']) && $_POST['vlLt400'] == 'yes' && $isRejected == false) {
        $finalResult = $_POST['vlResult'] = '< 400';
        $_POST['vlLog'] = '';
    }

    if (
        (isset($_POST['failed']) && $_POST['failed'] == 'yes')
        || in_array(strtolower($_POST['vlResult']), ['fail', 'failed', 'failure', 'error', 'err'])
    ) {
        $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?: 'Failed';
        $_POST['vlLog'] = '';
        $resultStatus = 5; // Invalid/Failed
    } else if (isset($_POST['invalid']) && $_POST['invalid'] == 'yes' && $isRejected == false) {
        $finalResult = $_POST['vlResult'] = 'Invalid';
        $_POST['vlLog'] = '';
        $resultStatus = 5; // Invalid/Failed
    } else if (isset($_POST['vlResult']) && trim(!empty($_POST['vlResult']))) {

        $resultStatus = 8; // Awaiting Approval
        $interpretedResults = $vlModel->interpretViralLoadResult($_POST['vlResult']);

        //Result is saved as entered
        $finalResult  = $_POST['vlResult'];

        $logVal = $interpretedResults['logVal'];
        $absDecimalVal = $interpretedResults['absDecimalVal'];
        $absVal = $interpretedResults['absVal'];
        $txtVal = $interpretedResults['txtVal'];
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = $general->isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = NULL;
    }
    $vldata = array(
        'is_sample_rejected' => (isset($_POST['noResult']) && $_POST['noResult'] != '') ? $_POST['noResult'] :  NULL,
        'reason_for_sample_rejection' => $_POST['rejectionReason'] ?: null,
        'rejection_on' => (isset($_POST['rejectionDate']) && $_POST['noResult'] == 'yes') ? $general->isoDateFormat($_POST['rejectionDate']) : null,
        'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedDate'],
        'sample_tested_datetime' => $_POST['dateOfCompletionOfViralLoad'],
        'vl_test_platform' => $testingPlatform,
        'result_value_absolute'                 => $absVal ?: null,
        'result_value_absolute_decimal'         => $absDecimalVal ?: null,
        'result_value_text'                     => $txtVal ?: null,
        'result'                                => $finalResult ?: null,
        'result_value_log'                      => $logVal ?: null,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '' ? $_POST['labId'] :  NULL),
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $general->getCurrentDateTime() : "",
        'result_dispatched_datetime' => NULL,
        'reason_for_vl_result_changes' => $allChange,
        'last_modified_datetime' => $db->now(),
        'manual_result_entry' => 'yes',
        'result_status' => $resultStatus,
        'data_sync' => 0,
        'result_printed_datetime' => NULL
    );
    // if (isset($_POST['status']) && trim($_POST['status']) != '') {
    //     $vldata['result_status'] = $_POST['status'];
    //     //if(isset($_POST['rejectionReason'])){
    //     $vldata['reason_for_sample_rejection'] = $_POST['rejectionReason'];
    //     //}
    // }
    /* Updating the high and low viral load data */
    //if ($vldata['result_status'] == 4 || $vldata['result_status'] == 7) {
    $vldata['vl_result_category'] = $vlModel->getVLResultCategory($vldata['result_status'], $vldata['result']);
    //}

    $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
    $db->update($tableName, $vldata);
    $_SESSION['alertMsg'] = "VL result updated successfully";
    //Add event log
    $eventType = 'update-vl-result-drc';
    $action = ucwords($_SESSION['userName']) . ' updated a result data with the patient code ' . $_POST['dubPatientArtNo'];
    $resource = 'vl-result-drc';

    $general->activityLog($eventType, $action, $resource);

    //  $data=array(
    // 'event_type'=>$eventType,
    // 'action'=>$action,
    // 'resource'=>$resource,
    // 'date_time'=>$general->getCurrentDateTime()
    // );
    // $db->insert($tableName1,$data);
    //Add update result log
    $data = array(
        'user_id' => $_SESSION['userId'],
        'vl_sample_id' => $_POST['vlSampleId'],
        'test_type' => 'vl',
        'updated_on' => $general->getCurrentDateTime()
    );
    $db->insert($tableName2, $data);
    header("location:vlTestResult.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
