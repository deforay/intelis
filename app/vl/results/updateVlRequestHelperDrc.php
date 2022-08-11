<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();



$general = new \Vlsm\Models\General();
$tableName = "form_vl";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";
$vl_result_category = NULL;
try {
    //Set sample received date
    if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
        $sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = $general->isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['sampleReceivedDate'] = NULL;
    }
    //Set sample rejection reason
    if (isset($_POST['status']) && trim($_POST['status']) != '') {
        if ($_POST['status'] == 4) {
            if (trim($_POST['rejectionReason']) == "other" && trim($_POST['newRejectionReason'] != '')) {
                $data = array(
                    'rejection_reason_name' => $_POST['newRejectionReason'],
                    'rejection_reason_status' => 'active'
                );
                $id = $db->insert('r_vl_sample_rejection_reasons', $data);
                $_POST['rejectionReason'] = $id;
            }
        } else {
            $_POST['rejectionReason'] = NULL;
        }
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


    $textResult =  null;
    if (isset($_POST['vlTND']) && $_POST['vlTND'] == 'yes' && $_POST['rejectionReason'] == NULL) {
        $textResult = $_POST['vlResult'] = 'Target not Detected';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['vlLt20']) && $_POST['vlLt20'] == 'yes' && $_POST['rejectionReason'] == NULL) {
        $textResult = $_POST['vlResult'] = '< 20';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['vlLt40']) && $_POST['vlLt40'] == 'yes' && $_POST['rejectionReason'] == NULL) {
        $textResult = $_POST['vlResult'] = '< 40';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['vlLt400']) && $_POST['vlLt400'] == 'yes' && $_POST['rejectionReason'] == NULL) {
        $textResult = $_POST['vlResult'] = '< 400';
        $_POST['vlLog'] = '';
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = $general->isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = NULL;
    }
    $vldata = array(
        'rejection_on' => (isset($_POST['rejectionDate']) && $_POST['noResult'] == 'yes') ? $general->isoDateFormat($_POST['rejectionDate']) : null,
        'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedDate'],
        //'sample_code'=>$_POST['sampleCode'],
        'sample_tested_datetime' => $_POST['dateOfCompletionOfViralLoad'],
        'vl_test_platform' => $testingPlatform,
        'result_value_log' => $_POST['vlLog'],
        'result' => $_POST['vlResult'],
        'result_value_text' => $textResult,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'last_modified_datetime' => $db->now(),
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '' ? $_POST['labId'] :  NULL),
        'data_sync' => 0,
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $general->getCurrentDateTime() : "",
        'result_printed_datetime' => NULL,
        'result_dispatched_datetime' => NULL,
        'vl_result_category' => $vl_result_category
    );
    if (isset($_POST['status']) && trim($_POST['status']) != '') {
        $vldata['result_status'] = $_POST['status'];
        //if(isset($_POST['rejectionReason'])){
        $vldata['reason_for_sample_rejection'] = $_POST['rejectionReason'];
        //}
    }
    /* Updating the high and low viral load data */
    if ($vldata['result_status'] == 4 || $vldata['result_status'] == 7) {
        $vlDb = new \Vlsm\Models\Vl();
        $vldata['vl_result_category'] = $vlDb->getVLResultCategory($vldata['result_status'], $vldata['result']);
    }

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
