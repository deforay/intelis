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
$tableName = "form_vl";
$tableName1 = "activity_log";
$vlTestReasonTable = "r_vl_test_reasons";
$tableName2 = "log_result_updates";
$vl_result_category = null;
try {

    if (isset($_POST['failedTestDate']) && trim($_POST['failedTestDate']) != "") {
        $failedtestDate = explode(" ", $_POST['failedTestDate']);
        $_POST['failedTestDate'] = DateUtility::isoDateFormat($failedtestDate[0]) . " " . $failedtestDate[1];
    } else {
        $_POST['failedTestDate'] = null;
    }

    if (isset($_POST['regStartDate']) && trim($_POST['regStartDate']) != "") {
        $_POST['regStartDate'] = DateUtility::isoDateFormat($_POST['regStartDate']);
    } else {
        $_POST['regStartDate'] = null;
    }

    if (isset($_POST['receivedDate']) && trim($_POST['receivedDate']) != "") {
        $sampleReceivedDate = explode(" ", $_POST['receivedDate']);
        $_POST['receivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['receivedDate'] = null;
    }
    if (isset($_POST['testDate']) && trim($_POST['testDate']) != "") {
        $sampletestDate = explode(" ", $_POST['testDate']);
        $_POST['testDate'] = DateUtility::isoDateFormat($sampletestDate[0]) . " " . $sampletestDate[1];
    } else {
        $_POST['testDate'] = null;
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

    if ($_POST['testingTech'] != '') {
        $platForm = explode("##", $_POST['testingTech']);
        $_POST['testingTech'] = $platForm[0];
    }
    if ($_POST['failedTestingTech'] != '') {
        $platForm = explode("##", $_POST['failedTestingTech']);
        $_POST['failedTestingTech'] = $platForm[0];
    }
    if (isset($_POST['isSampleRejected']) && trim($_POST['isSampleRejected']) == 'no') {
        $_POST['rejectionReason'] = null;
    }
    if (isset($_POST['isSampleRejected']) && trim($_POST['isSampleRejected']) == 'yes') {
        $vl_result_category = 'rejected';
        $_POST['vlResult'] = null;
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = null;
    }

    $vldata = array(
        'is_sample_rejected' => (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] != '') ? $_POST['isSampleRejected'] : null,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] : null,
        'rejection_on' => (isset($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
        'batch_quality' => (isset($_POST['batchQuality']) && $_POST['batchQuality'] != '' ? $_POST['batchQuality'] : null),
        'sample_test_quality' => (isset($_POST['testQuality']) && $_POST['testQuality'] != '' ? $_POST['testQuality'] : null),
        'sample_batch_id' => (isset($_POST['batchNo']) && $_POST['batchNo'] != '' ? $_POST['batchNo'] : null),
        'failed_test_date' => $_POST['failedTestDate'],
        'failed_test_tech' => (isset($_POST['failedTestingTech']) && $_POST['failedTestingTech'] != '') ? $_POST['failedTestingTech'] : null,
        'failed_vl_result' => (isset($_POST['failedvlResult']) && $_POST['failedvlResult'] != '' ? $_POST['failedvlResult'] : null),
        'failed_batch_quality' => (isset($_POST['failedbatchQuality']) && $_POST['failedbatchQuality'] != '' ? $_POST['failedbatchQuality'] : null),
        'failed_sample_test_quality' => (isset($_POST['failedtestQuality']) && $_POST['failedtestQuality'] != '' ? $_POST['failedtestQuality'] : null),
        'failed_batch_id' => (isset($_POST['failedbatchNo']) && $_POST['failedbatchNo'] != '' ? $_POST['failedbatchNo'] : null),
        'lab_id' => (isset($_POST['laboratoryId']) && $_POST['laboratoryId'] != '' ? $_POST['laboratoryId'] : null),
        'sample_type' => (isset($_POST['sampleType']) && $_POST['sampleType'] != '' ? $_POST['sampleType'] : null),
        'sample_received_at_vl_lab_datetime' => $_POST['receivedDate'],
        'tech_name_png' => (isset($_POST['techName']) && $_POST['techName'] != '') ? $_POST['techName'] : null,
        'sample_tested_datetime' => (isset($_POST['testDate']) && $_POST['testDate'] != '' ? $_POST['testDate'] : null),
        //'last_viral_load_result'=>(isset($_POST['vlResult']) && $_POST['vlResult']!='' ? $_POST['vlResult'] : null),
        'vl_test_platform' => (isset($_POST['testingTech']) && $_POST['testingTech'] != '') ? $_POST['testingTech'] : null,
        'result' => (isset($_POST['vlResult']) && trim($_POST['vlResult']) != '') ? $_POST['vlResult'] : null,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'qc_tech_name' => (isset($_POST['qcTechName']) && $_POST['qcTechName'] != '' ? $_POST['qcTechName'] : null),
        'qc_tech_sign' => (isset($_POST['qcTechSign']) && $_POST['qcTechSign'] != '' ? $_POST['qcTechSign'] : null),
        'qc_date' => $_POST['qcDate'],
        'clinic_date' => $_POST['clinicDate'],
        'report_date' => $_POST['reportDate'],
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : "",
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $db->now(),
        'data_sync' => 0,
        'result_printed_datetime' => null,
        'result_dispatched_datetime' => null,
        'vl_result_category' => $vl_result_category
    );
    //print_r($vldata);die;
    if (isset($_POST['status']) && trim($_POST['status']) != '') {
        $vldata['result_status'] = $_POST['status'];
    }

    $vlService = ContainerRegistry::get(VlService::class);
    $vldata['vl_result_category'] = $vlService->getVLResultCategory($vldata['result_status'], $vldata['result']);
    if ($vldata['vl_result_category'] == 'failed' || $vldata['vl_result_category'] == 'invalid') {
        $vldata['result_status'] = 5;
    } elseif ($vldata['vl_result_category'] == 'rejected') {
        $vldata['result_status'] = 4;
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
            'updated_on' => DateUtility::getCurrentDateTime()
        );
        $db->insert($tableName2, $data);
    } else {
        $_SESSION['alertMsg'] = "Please try again later";
    }
    header("Location:vlResultApproval.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
