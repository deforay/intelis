<?php

use App\Models\General;
use App\Models\Vl;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}




$general = new General();
$vlModel = new Vl();
$tableName = "form_vl";
$tableName2 = "log_result_updates";

$logVal = null;
$absDecimalVal = null;
$absVal = null;
$txtVal = null;
$finalResult = null;

try {
    //var_dump($_POST);die;
    $instanceId = '';
    if (isset($_SESSION['instanceId'])) {
        $instanceId = $_SESSION['instanceId'];
    }
    $testingPlatform = '';
    if (isset($_POST['testingPlatform']) && trim($_POST['testingPlatform']) != '') {
        $platForm = explode("##", $_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
    }
    if (isset($_POST['sampleReceivedOn']) && trim($_POST['sampleReceivedOn']) != "") {
        $sampleReceivedDateLab = explode(" ", $_POST['sampleReceivedOn']);
        $_POST['sampleReceivedOn'] = DateUtils::isoDateFormat($sampleReceivedDateLab[0]) . " " . $sampleReceivedDateLab[1];
    } else {
        $_POST['sampleReceivedOn'] = null;
    }
    if (isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab']) != "") {
        $sampleTestingDateAtLab = explode(" ", $_POST['sampleTestingDateAtLab']);
        $_POST['sampleTestingDateAtLab'] = DateUtils::isoDateFormat($sampleTestingDateAtLab[0]) . " " . $sampleTestingDateAtLab[1];
    } else {
        $_POST['sampleTestingDateAtLab'] = null;
    }
    if (isset($_POST['resultDispatchedOn']) && trim($_POST['resultDispatchedOn']) != "") {
        $resultDispatchedOn = explode(" ", $_POST['resultDispatchedOn']);
        $_POST['resultDispatchedOn'] = DateUtils::isoDateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
    } else {
        $_POST['resultDispatchedOn'] = null;
    }

    if (isset($_POST['newRejectionReason']) && trim($_POST['newRejectionReason']) != "") {
        $rejectionReasonQuery = "SELECT rejection_reason_id FROM r_vl_sample_rejection_reasons where rejection_reason_name='" . $_POST['newRejectionReason'] . "' OR rejection_reason_name='" . strtolower($_POST['newRejectionReason']) . "' OR rejection_reason_name='" . (strtolower($_POST['newRejectionReason'])) . "'";
        $rejectionResult = $db->rawQuery($rejectionReasonQuery);
        if (!isset($rejectionResult[0]['rejection_reason_id'])) {
            $data = array(
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active'
            );
            $id = $db->insert('r_vl_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $id;
        } else {
            $_POST['rejectionReason'] = $rejectionResult[0]['rejection_reason_id'];
        }
    }

    $isRejected = false;
    $textValue = null;
    $finalResult = null;
    $resultStatus = 8; // Awaiting Approval    
    if (isset($_POST['noResult']) && $_POST['noResult'] === 'yes') {
        $isRejected = true;
        $finalResult = $_POST['vlResult'] = null;
        $_POST['vlLog'] = null;
        $resultStatus = 4;
    }


    if (isset($_POST['lt20']) && $_POST['lt20'] == 'yes' && $isRejected === false) {
        $_POST['vlResult'] = '< 20';
        $_POST['vlLog'] = '';
    } else if (isset($_POST['lt40']) && $_POST['lt40'] == 'yes' && $isRejected === false) {
        $_POST['vlResult'] = '< 40';
        $_POST['vlLog'] = '';
    } else if (isset($_POST['tnd']) && $_POST['tnd'] == 'yes' && $isRejected === false) {
        $_POST['vlResult'] = $textValue = 'Target Not Detected';
        $_POST['vlLog'] = '';
    } else if (isset($_POST['bdl']) && $_POST['bdl'] == 'yes' && $isRejected === false) {
        $_POST['vlResult'] = 'Below Detection Level';
        $_POST['vlLog'] = '';
    }

    if (
        (isset($_POST['failed']) && $_POST['failed'] == 'yes')
        || in_array(strtolower($_POST['vlResult']), ['fail', 'failed', 'failure', 'error', 'err'])
    ) {
        $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?: 'Failed';
        $_POST['vlLog'] = '';
        $resultStatus = 5; // Invalid/Failed
    } else if (isset($_POST['invalid']) && $_POST['invalid'] == 'yes' && $isRejected === false) {
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

    $reasonForChanges = '';
    $allChange = '';
    if (isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] != '') {
        $allChange = $_POST['reasonForResultChangesHistory'];
    }
    if (isset($_POST['reasonForResultChanges']) && trim($_POST['reasonForResultChanges']) != '') {
        $reasonForChanges = $_SESSION['userName'] . '##' . $_POST['reasonForResultChanges'] . '##' . DateUtils::getCurrentDateTime();
    }
    if (trim($allChange) != '' && trim($reasonForChanges) != '') {
        $allChange = $reasonForChanges . 'vlsm' . $allChange;
    } else if (trim($reasonForChanges) != '') {
        $allChange =  $reasonForChanges;
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = DateUtils::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = null;
    }

    //echo $reasonForChanges;die;
    $vldata = array(
        'vlsm_instance_id' => $instanceId,
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '') ? $_POST['labId'] :  null,
        'vl_test_platform' => $testingPlatform,
        //'test_methods'=>(isset($_POST['testMethods']) && $_POST['testMethods']!='') ? $_POST['testMethods'] :  null,
        'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedOn'],
        'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
        'result_dispatched_datetime' => !empty($_POST['resultDispatchedOn']) ? $_POST['resultDispatchedOn'] : null,
        'is_sample_rejected' => (isset($_POST['noResult']) && $_POST['noResult'] != '') ? $_POST['noResult'] :  null,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] :  null,
        'rejection_on' => (isset($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtils::isoDateFormat($_POST['rejectionDate']) : null,
        'result_value_absolute'                 => $absVal ?: null,
        'result_value_absolute_decimal'         => $absDecimalVal ?: null,
        'result_value_text'                     => $txtVal ?: null,
        'result'                                => $finalResult ?: null,
        'result_value_log'                      => $logVal ?: null,
        'result_status'                         => $resultStatus,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  null,
        'lab_tech_comments' => (isset($_POST['labComments']) && trim($_POST['labComments']) != '') ? trim($_POST['labComments']) :  null,
        'reason_for_vl_result_changes' => $allChange,
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtils::getCurrentDateTime() : "",
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $db->now(),
        'manual_result_entry' => 'yes',
        'data_sync' => 0,
        'result_printed_datetime' => null,
    );

    if (isset($_POST['noResult']) && $_POST['noResult'] == 'yes') {
        $vldata['result_status'] = 4;
    }
    /* Updating the high and low viral load data */

    $vldata['vl_result_category'] = $vlModel->getVLResultCategory($vldata['result_status'], $vldata['result']);
    if ($vldata['vl_result_category'] == 'failed' || $vldata['vl_result_category'] == 'invalid') {
        $vldata['result_status'] = 5;
    } elseif ($vldata['vl_result_category'] == 'rejected') {
        $vldata['result_status'] = 4;
    }
    //echo "<pre>";var_dump($vldata);die;
    $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
    $id = $db->update($tableName, $vldata);
    if ($id > 0) {
        $_SESSION['alertMsg'] = "VL request updated successfully";
        //Log result updates
        $data = array(
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $_POST['vlSampleId'],
            'test_type' => 'vl',
            'updated_on' => DateUtils::getCurrentDateTime()
        );
        $db->insert($tableName2, $data);
    } else {
        $_SESSION['alertMsg'] = "Please try again later";
    }
    header("Location:/vl/results/vlTestResult.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
