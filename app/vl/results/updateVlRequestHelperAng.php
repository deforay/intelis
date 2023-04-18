<?php

use App\Models\General;
use App\Models\Vl;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();



$general = new General();
$tableName = "form_vl";
$tableName2 = "log_result_updates";
$vl_result_category = null;
$vlModel = new Vl();


try {
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

    $isRejection = false;
    if (isset($_POST['noResult']) && $_POST['noResult'] == 'yes') {
        $vl_result_category = 'rejected';
        $isRejection = true;
        $_POST['vlResult'] = '';
        $_POST['vlLog'] = '';
    }

    if (isset($_POST['tnd']) && $_POST['tnd'] == 'yes' && $isRejection == false) {
        $_POST['vlResult'] = 'Target Not Detected';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['ldl']) && $_POST['ldl'] == 'yes' && $isRejection == false) {
        $_POST['vlResult'] = 'Low Detection Level';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['hdl']) && $_POST['hdl'] == 'yes' && $isRejection == false) {
        $_POST['vlResult'] = 'High Detection Level';
        $_POST['vlLog'] = '';
    }

    if (isset($_POST['vlResult']) && trim($_POST['vlResult']) != '') {
        $_POST['result'] = $_POST['vlResult'];
    } else if ($_POST['vlLog'] != '') {
        $_POST['result'] = $_POST['vlLog'];
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
    $vldata = array(
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '') ? $_POST['labId'] :  null,
        'lab_technician' => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  null,
        'vl_test_platform' => $testingPlatform,
        'test_methods' => (isset($_POST['testMethods']) && $_POST['testMethods'] != '') ? $_POST['testMethods'] :  null,
        'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedOn'],
        'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
        'result_dispatched_datetime' => $_POST['resultDispatchedOn'],
        'vl_focal_person' => (isset($_POST['vlFocalPerson']) && $_POST['vlFocalPerson'] != '') ? $_POST['vlFocalPerson'] :  null,
        'is_sample_rejected' => (isset($_POST['noResult']) && $_POST['noResult'] != '') ? $_POST['noResult'] :  null,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] :  null,
        'rejection_on' => (isset($_POST['rejectionDate']) && $_POST['noResult'] == 'yes') ? DateUtils::isoDateFormat($_POST['rejectionDate']) : null,
        'result_value_absolute' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected' && $_POST['vlResult'] != 'Low Detection Level' && $_POST['vlResult'] != 'High Detection Level')) ? $_POST['vlResult'] :  null,
        'result_value_absolute_decimal' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected' && $_POST['vlResult'] != 'Low Detection Level' && $_POST['vlResult'] != 'High Detection Level')) ? number_format((float)$_POST['vlResult'], 2, '.', '') :  null,
        'result' => (isset($_POST['result']) && $_POST['result'] != '') ? $_POST['result'] :  null,
        'result_value_log' => (isset($_POST['vlLog']) && $_POST['vlLog'] != '') ? $_POST['vlLog'] :  null,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  null,
        'lab_tech_comments' => (isset($_POST['labComments']) && trim($_POST['labComments']) != '') ? trim($_POST['labComments']) :  null,
        'result_status' => (isset($_POST['status']) && $_POST['status'] != '') ? $_POST['status'] :  null,
        'reason_for_vl_result_changes' => $allChange,
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtils::getCurrentDateTime() : "",
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $db->now(),
        'data_sync' => 0,
        'result_printed_datetime' => null,
        'vl_result_category' => $vl_result_category
    );
    /* Updating the high and low viral load data */
    
    $vldata['vl_result_category'] = $vlModel->getVLResultCategory($vldata['result_status'], $vldata['result']);
    
    if ($vldata['vl_result_category'] == 'failed' || $vldata['vl_result_category'] == 'invalid') {
        $vldata['result_status'] = 5;
    } elseif ($vldata['vl_result_category'] == 'rejected') {
        $vldata['result_status'] = 4;
    }

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
        header("location:vlTestResult.php");
    } else {
        $_SESSION['alertMsg'] = "Please try again later";
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
