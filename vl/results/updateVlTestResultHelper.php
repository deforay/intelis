<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../../startup.php');


$general = new \Vlsm\Models\General($db);
$tableName = "vl_request_form";
$tableName2 = "log_result_updates";
$vl_result_category = NULL;
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
        $_POST['sampleReceivedOn'] = $general->dateFormat($sampleReceivedDateLab[0]) . " " . $sampleReceivedDateLab[1];
    } else {
        $_POST['sampleReceivedOn'] = NULL;
    }


    if (isset($_POST['sampleReceivedAtHubOn']) && trim($_POST['sampleReceivedAtHubOn']) != "") {
        $sampleReceivedAtHubOn = explode(" ", $_POST['sampleReceivedAtHubOn']);
        $_POST['sampleReceivedAtHubOn'] = $general->dateFormat($sampleReceivedAtHubOn[0]) . " " . $sampleReceivedAtHubOn[1];
    } else {
        $_POST['sampleReceivedAtHubOn'] = NULL;
    }

    if (isset($_POST['approvedOnDateTime']) && trim($_POST['approvedOnDateTime']) != "") {
        $approvedOnDateTime = explode(" ", $_POST['approvedOnDateTime']);
        $_POST['approvedOnDateTime'] = $general->dateFormat($approvedOnDateTime[0]) . " " . $approvedOnDateTime[1];
    } else {
        $_POST['approvedOnDateTime'] = NULL;
    }


    if (isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab']) != "") {
        $sampleTestingDateAtLab = explode(" ", $_POST['sampleTestingDateAtLab']);
        $_POST['sampleTestingDateAtLab'] = $general->dateFormat($sampleTestingDateAtLab[0]) . " " . $sampleTestingDateAtLab[1];
    } else {
        $_POST['sampleTestingDateAtLab'] = NULL;
    }
    if (isset($_POST['resultDispatchedOn']) && trim($_POST['resultDispatchedOn']) != "") {
        $resultDispatchedOn = explode(" ", $_POST['resultDispatchedOn']);
        $_POST['resultDispatchedOn'] = $general->dateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
    } else {
        $_POST['resultDispatchedOn'] = NULL;
    }

    if (isset($_POST['newRejectionReason']) && trim($_POST['newRejectionReason']) != "") {
        $rejectionReasonQuery = "SELECT rejection_reason_id FROM r_vl_sample_rejection_reasons where rejection_reason_name='" . $_POST['newRejectionReason'] . "' OR rejection_reason_name='" . strtolower($_POST['newRejectionReason']) . "' OR rejection_reason_name='" . ucfirst(strtolower($_POST['newRejectionReason'])) . "'";
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
    $finalResult = null;
    $textResult = null;
    if (isset($_POST['noResult']) && $_POST['noResult'] == 'yes') {
        $vl_result_category = 'rejected';
        $isRejected = true;
        $finalResult = $_POST['vlResult'] = $_POST['vlLog'] = null;
    }

    if (isset($_POST['tnd']) && $_POST['tnd'] == 'yes' && $isRejected == false) {
        $finalResult = $textResult = 'Target Not Detected';
        $_POST['vlResult'] = $_POST['vlLog'] = null;
    } else if (isset($_POST['bdl']) && $_POST['bdl'] == 'yes' && $isRejected == false) {
        $finalResult = $textResult = 'Below Detection Level';
        $_POST['vlResult'] = $_POST['vlLog'] = null;
    } else if (!empty($_POST['vlResult'])) {
        $finalResult = (float)$_POST['vlResult'];
    } else if ($_POST['vlLog'] != '') {
        $finalResult = (float)$_POST['vlLog'];
    }

    $reasonForChanges = '';
    $allChange = '';
    if (isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] != '') {
        $allChange = $_POST['reasonForResultChangesHistory'];
    }
    if (isset($_POST['reasonForResultChanges']) && trim($_POST['reasonForResultChanges']) != '') {
        $reasonForChanges = $_SESSION['userName'] . '##' . $_POST['reasonForResultChanges'] . '##' . $general->getDateTime();
    }
    if (trim($allChange) != '' && trim($reasonForChanges) != '') {
        $allChange = $reasonForChanges . 'vlsm' . $allChange;
    } else if (trim($reasonForChanges) != '') {
        $allChange =  $reasonForChanges;
    }
    if (isset($_POST['approvedBy']) && trim($_POST['approvedBy']) != '') {
        $vlObj = new \Vlsm\Models\Vl($db);
        $vl_result_category = $vlObj->vlResultCategory($_POST['vlResult']);
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = $general->dateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = NULL;
    }
    //echo $reasonForChanges;die;
    $vldata = array(
        'vlsm_instance_id' => $instanceId,
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '') ? $_POST['labId'] :  NULL,
        'vl_test_platform' => $testingPlatform,
        //'test_methods'=>(isset($_POST['testMethods']) && $_POST['testMethods']!='') ? $_POST['testMethods'] :  NULL,
        'sample_received_at_hub_datetime' => $_POST['sampleReceivedAtHubOn'],
        'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedOn'],
        'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
        'result_dispatched_datetime' => $_POST['resultDispatchedOn'],
        'is_sample_rejected' => (isset($_POST['noResult']) && $_POST['noResult'] != '') ? $_POST['noResult'] :  NULL,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] :  NULL,
        'rejection_on' => (isset($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? $general->dateFormat($_POST['rejectionDate']) : null,
        'result_value_log' => (isset($_POST['vlLog']) && $_POST['vlLog'] != '') ? $_POST['vlLog'] :  NULL,
        'result_value_absolute' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected' && $_POST['vlResult'] != 'Below Detection Level')) ? $_POST['vlResult'] :  NULL,
        'result_value_text' => $textResult,
        'result_value_absolute_decimal' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected' && $_POST['vlResult'] != 'Below Detection Level')) ? number_format((float)$_POST['vlResult'], 2, '.', '') :  NULL,
        'result' => $finalResult,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'vl_focal_person' => (isset($_POST['vlFocalPerson']) && $_POST['vlFocalPerson'] != '') ? $_POST['vlFocalPerson'] :  NULL,
        'vl_focal_person_phone_number' => (isset($_POST['vlFocalPersonPhoneNumber']) && $_POST['vlFocalPersonPhoneNumber'] != '') ? $_POST['vlFocalPersonPhoneNumber'] :  NULL,
        'tested_by' => (isset($_POST['testedBy']) && $_POST['testedBy'] != '') ? $_POST['testedBy'] :  NULL,
        'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  NULL,
        'result_approved_datetime' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedOnDateTime'] :  NULL,
        'approver_comments' => (isset($_POST['labComments']) && trim($_POST['labComments']) != '') ? trim($_POST['labComments']) :  NULL,
        'result_status' => (isset($_POST['status']) && $_POST['status'] != '') ? $_POST['status'] :  NULL,
        'reason_for_vl_result_changes' => $allChange,
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $general->getDateTime() : "",
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $general->getDateTime(),
        'manual_result_entry' => 'yes',
        'data_sync' => 0,
        'result_printed_datetime' => NULL,
        'result_dispatched_datetime' => NULL,
        'vl_result_category' => $vl_result_category
    );
    $lock = $general->getGlobalConfig('lock_approved_vl_samples');
    if ($_POST['status'] == 7 && $lock == 'yes') {
        $vldata['locked'] = 'yes';
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
            'updated_on' => $general->getDateTime()
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
