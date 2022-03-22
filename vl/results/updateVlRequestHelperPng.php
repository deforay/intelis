<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();


$general = new \Vlsm\Models\General();
$tableName = "vl_request_form";
$tableName1 = "activity_log";
$vlTestReasonTable = "r_vl_test_reasons";
$tableName2 = "log_result_updates";
$vl_result_category = NULL;
try {

    if (isset($_POST['failedTestDate']) && trim($_POST['failedTestDate']) != "") {
        $failedtestDate = explode(" ", $_POST['failedTestDate']);
        $_POST['failedTestDate'] = $general->dateFormat($failedtestDate[0]) . " " . $failedtestDate[1];
    } else {
        $_POST['failedTestDate'] = NULL;
    }

    if (isset($_POST['regStartDate']) && trim($_POST['regStartDate']) != "") {
        $_POST['regStartDate'] = $general->dateFormat($_POST['regStartDate']);
    } else {
        $_POST['regStartDate'] = NULL;
    }

    if (isset($_POST['receivedDate']) && trim($_POST['receivedDate']) != "") {
        $sampleReceivedDate = explode(" ", $_POST['receivedDate']);
        $_POST['receivedDate'] = $general->dateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['receivedDate'] = NULL;
    }
    if (isset($_POST['testDate']) && trim($_POST['testDate']) != "") {
        $sampletestDate = explode(" ", $_POST['testDate']);
        $_POST['testDate'] = $general->dateFormat($sampletestDate[0]) . " " . $sampletestDate[1];
    } else {
        $_POST['testDate'] = NULL;
    }
    if (isset($_POST['qcDate']) && trim($_POST['qcDate']) != "") {
        $_POST['qcDate'] = $general->dateFormat($_POST['qcDate']);
    } else {
        $_POST['qcDate'] = NULL;
    }
    if (isset($_POST['reportDate']) && trim($_POST['reportDate']) != "") {
        $_POST['reportDate'] = $general->dateFormat($_POST['reportDate']);
    } else {
        $_POST['reportDate'] = NULL;
    }
    if (isset($_POST['clinicDate']) && trim($_POST['clinicDate']) != "") {
        $_POST['clinicDate'] = $general->dateFormat($_POST['clinicDate']);
    } else {
        $_POST['clinicDate'] = NULL;
    }

    if ($_POST['testingTech'] != '') {
        $platForm = explode("##", $_POST['testingTech']);
        $_POST['testingTech'] = $platForm[0];
    }
    if ($_POST['failedTestingTech'] != '') {
        $platForm = explode("##", $_POST['failedTestingTech']);
        $_POST['failedTestingTech'] = $platForm[0];
    }
    if (isset($_POST['isSampleRejected']) && trim($_POST['isSampleRejected']) == 'no') {
        $_POST['rejectionReason'] = NULL;
    }
    if (isset($_POST['isSampleRejected']) && trim($_POST['isSampleRejected']) == 'yes') {
        $vl_result_category = 'rejected';
        $_POST['vlResult'] = NULL;
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = $general->dateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = NULL;
    }

    $vldata = array(
        'is_sample_rejected' => (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] != '') ? $_POST['isSampleRejected'] : NULL,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] : NULL,
        'rejection_on' => (isset($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? $general->dateFormat($_POST['rejectionDate']) : null,
        'batch_quality' => (isset($_POST['batchQuality']) && $_POST['batchQuality'] != '' ? $_POST['batchQuality'] : NULL),
        'sample_test_quality' => (isset($_POST['testQuality']) && $_POST['testQuality'] != '' ? $_POST['testQuality'] : NULL),
        'sample_batch_id' => (isset($_POST['batchNo']) && $_POST['batchNo'] != '' ? $_POST['batchNo'] : NULL),
        'failed_test_date' => $_POST['failedTestDate'],
        'failed_test_tech' => (isset($_POST['failedTestingTech']) && $_POST['failedTestingTech'] != '') ? $_POST['failedTestingTech'] : NULL,
        'failed_vl_result' => (isset($_POST['failedvlResult']) && $_POST['failedvlResult'] != '' ? $_POST['failedvlResult'] : NULL),
        'failed_batch_quality' => (isset($_POST['failedbatchQuality']) && $_POST['failedbatchQuality'] != '' ? $_POST['failedbatchQuality'] : NULL),
        'failed_sample_test_quality' => (isset($_POST['failedtestQuality']) && $_POST['failedtestQuality'] != '' ? $_POST['failedtestQuality'] : NULL),
        'failed_batch_id' => (isset($_POST['failedbatchNo']) && $_POST['failedbatchNo'] != '' ? $_POST['failedbatchNo'] : NULL),
        'lab_id' => (isset($_POST['laboratoryId']) && $_POST['laboratoryId'] != '' ? $_POST['laboratoryId'] : NULL),
        'sample_type' => (isset($_POST['sampleType']) && $_POST['sampleType'] != '' ? $_POST['sampleType'] : NULL),
        'sample_received_at_vl_lab_datetime' => $_POST['receivedDate'],
        'tech_name_png' => (isset($_POST['techName']) && $_POST['techName'] != '') ? $_POST['techName'] : NULL,
        'sample_tested_datetime' => (isset($_POST['testDate']) && $_POST['testDate'] != '' ? $_POST['testDate'] : NULL),
        //'last_viral_load_result'=>(isset($_POST['vlResult']) && $_POST['vlResult']!='' ? $_POST['vlResult'] :  NULL),
        'vl_test_platform' => (isset($_POST['testingTech']) && $_POST['testingTech'] != '') ? $_POST['testingTech'] : NULL,
        'result' => (isset($_POST['vlResult']) && trim($_POST['vlResult']) != '') ? $_POST['vlResult'] : NULL,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'qc_tech_name' => (isset($_POST['qcTechName']) && $_POST['qcTechName'] != '' ? $_POST['qcTechName'] : NULL),
        'qc_tech_sign' => (isset($_POST['qcTechSign']) && $_POST['qcTechSign'] != '' ? $_POST['qcTechSign'] : NULL),
        'qc_date' => $_POST['qcDate'],
        'clinic_date' => $_POST['clinicDate'],
        'report_date' => $_POST['reportDate'],
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $general->getDateTime() : "",
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $db->now(),
        'data_sync' => 0,
        'result_printed_datetime' => NULL,
        'result_dispatched_datetime' => NULL,
        'vl_result_category' => $vl_result_category
    );
    //print_r($vldata);die;
    if (isset($_POST['status']) && trim($_POST['status']) != '') {
        $vldata['result_status'] = $_POST['status'];
    }
    /* Updating the high and low viral load data */
    if ($vldata['result_status'] == 4 || $vldata['result_status'] == 7) {
        $vlDb = new \Vlsm\Models\Vl();
        $vldata['vl_result_category'] = $vlDb->getVLResultCategory($vldata['result_status'], $vldata['result']);
    }
    $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
    $id = $db->update($tableName, $vldata);

    if ($id > 0) {
        $_SESSION['alertMsg'] = "VL result updated successfully";
        //Add update result log
        $data = array(
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $_POST['vlSampleId'],
            'test_type' => 'vl',
            'updated_on' => $general->getDateTime()
        );
        $db->insert($tableName2, $data);
    } else {
        $_SESSION['alertMsg'] = "Please try again later";
    }
    header("location:vlResultApproval.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
