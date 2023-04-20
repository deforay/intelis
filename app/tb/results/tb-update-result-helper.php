<?php

use App\Models\General;
use App\Models\GeoLocations;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new General();
$geoLocationDb = new GeoLocations();

$tableName = "form_tb";
$tableName1 = "activity_log";
$testTableName = 'tb_tests';

try {
    //system config
    $systemConfigQuery = "SELECT * FROM system_config";
    $systemConfigResult = $db->query($systemConfigQuery);
    $sarr = array();
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
        $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
    }
    $instanceId = '';
    if (!empty($_SESSION['instanceId'])) {
        $instanceId = $_SESSION['instanceId'];
    }

    if (empty($instanceId) && $_POST['instanceId']) {
        $instanceId = $_POST['instanceId'];
    }
    if (!empty($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != "") {
        $sampleCollectionDate = explode(" ", $_POST['sampleCollectionDate']);
        $_POST['sampleCollectionDate'] = DateUtils::isoDateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
    } else {
        $_POST['sampleCollectionDate'] = null;
    }

    //Set sample received date
    if (!empty($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
        $sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = DateUtils::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['sampleReceivedDate'] = null;
    }
    if (!empty($_POST['resultDispatchedDatetime']) && trim($_POST['resultDispatchedDatetime']) != "") {
        $resultDispatchedDatetime = explode(" ", $_POST['resultDispatchedDatetime']);
        $_POST['resultDispatchedDatetime'] = DateUtils::isoDateFormat($resultDispatchedDatetime[0]) . " " . $resultDispatchedDatetime[1];
    } else {
        $_POST['resultDispatchedDatetime'] = null;
    }
    if (!empty($_POST['sampleTestedDateTime']) && trim($_POST['sampleTestedDateTime']) != "") {
        $sampleTestedDate = explode(" ", $_POST['sampleTestedDateTime']);
        $_POST['sampleTestedDateTime'] = DateUtils::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
    } else {
        $_POST['sampleTestedDateTime'] = null;
    }

    if (!empty($_POST['arrivalDateTime']) && trim($_POST['arrivalDateTime']) != "") {
        $arrivalDate = explode(" ", $_POST['arrivalDateTime']);
        $_POST['arrivalDateTime'] = DateUtils::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
    } else {
        $_POST['arrivalDateTime'] = null;
    }

    if (!empty($_POST['requestedDate']) && trim($_POST['requestedDate']) != "") {
        $arrivalDate = explode(" ", $_POST['requestedDate']);
        $_POST['requestedDate'] = DateUtils::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
    } else {
        $_POST['requestedDate'] = null;
    }

    if (empty(trim($_POST['sampleCode']))) {
        $_POST['sampleCode'] = null;
    }

    if ($_SESSION['instanceType'] == 'remoteuser') {
        $sampleCode = 'remote_sample_code';
        $sampleCodeKey = 'remote_sample_code_key';
    } else {
        $sampleCode = 'sample_code';
        $sampleCodeKey = 'sample_code_key';
    }

    $status = 6;
    if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
        $status = 9;
    }

    $resultSentToSource = null;

    if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
        $_POST['result'] = null;
        $status = 4;
        $resultSentToSource = 'pending';
    }
    if (!empty($_POST['patientDob'])) {
        $_POST['patientDob'] = DateUtils::isoDateFormat($_POST['patientDob']);
    }

    if (!empty($_POST['result'])) {
        $resultSentToSource = 'pending';
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = DateUtils::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = null;
    }

    if (isset($_POST['approvedOn']) && trim($_POST['approvedOn']) != "") {
        $approvedOn = explode(" ", $_POST['approvedOn']);
        $_POST['approvedOn'] = DateUtils::isoDateFormat($approvedOn[0]) . " " . $approvedOn[1];
    } else {
        $_POST['approvedOn'] = null;
    }
    if (isset($_POST['province']) && $_POST['province'] != "") {
        $province = explode("##", $_POST['province']);
        $provinceDetails = $geoLocationDb->getByName($province[0]);
        $_POST['provinceId'] = $provinceDetails['geo_id'];
    }

    $tbData = array(
        'specimen_quality'                    => !empty($_POST['testNumber']) ? $_POST['testNumber'] : null,
        'lab_id'                              => !empty($_POST['labId']) ? $_POST['labId'] : null,
        'reason_for_tb_test'                  => !empty($_POST['reasonForTbTest']) ? json_encode($_POST['reasonForTbTest']) : null,
        'tests_requested'                     => !empty($_POST['testTypeRequested']) ? json_encode($_POST['testTypeRequested']) : null,
        'specimen_type'                       => !empty($_POST['specimenType']) ? $_POST['specimenType'] : null,
      //  'sample_collection_date'              => !empty($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
        'sample_received_at_lab_datetime'     => !empty($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
        'is_sample_rejected'                  => !empty($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,
        'result'                              => !empty($_POST['result']) ? $_POST['result'] : null,
        'xpert_mtb_result'                    => !empty($_POST['xPertMTMResult']) ? $_POST['xPertMTMResult'] : null,
        'result_sent_to_source'               => $resultSentToSource,
        'result_dispatched_datetime'          => !empty($_POST['resultDispatchedDatetime']) ? $_POST['resultDispatchedDatetime'] : null,
        'result_reviewed_by'                  => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime'            => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by'                  => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != "") ? $_POST['approvedBy'] : "",
        'result_approved_datetime'            => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != "") ? $_POST['approvedOn'] : null,
        'sample_tested_datetime'              => (isset($_POST['sampleTestedDateTime']) && $_POST['sampleTestedDateTime'] != "") ? $_POST['sampleTestedDateTime'] : null,
        'tested_by'                           => !empty($_POST['testedBy']) ? $_POST['testedBy'] : null,
        'rejection_on'                        => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtils::isoDateFormat($_POST['rejectionDate']) : null,
        'result_status'                       => $status,
        'data_sync'                           => 0,
        'reason_for_sample_rejection'         => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
        'sample_registered_at_lab'            => $db->now(),
        'last_modified_by'                    => $_SESSION['userId'],
        'last_modified_datetime'              => $db->now(),
        'request_created_by'                  => $_SESSION['userId'],
        'last_modified_by'                    => $_SESSION['userId'],
        'lab_technician'                      => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  $_SESSION['userId']
    );
//echo '<pre>'; print_r($tbData); die;
    $id = 0;

    if (isset($_POST['tbSampleId']) && $_POST['tbSampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
        if (isset($_POST['testResult']) && count($_POST['testResult']) > 0) {
            $db = $db->where('tb_id', $_POST['tbSampleId']);
            $db->delete($testTableName);

            foreach ($_POST['testResult'] as $testKey => $testResult) {
                if (isset($testResult) && !empty($testResult) && trim($testResult) != "") {
                    $db->insert($testTableName, array(
                        'tb_id'             => $_POST['tbSampleId'],
                        'actual_no'         => isset($_POST['actualNo'][$testKey]) ? $_POST['actualNo'][$testKey] : null,
                        'test_result'       => $testResult,
                        'updated_datetime'  => DateUtils::getCurrentDateTime()
                    ));
                }
            }
        }
    } else {
        $db = $db->where('tb_id', $_POST['tbSampleId']);
        $db->delete($testTableName);
    }

    if (!empty($_POST['tbSampleId'])) {
        $db = $db->where('tb_id', $_POST['tbSampleId']);
        $id = $db->update($tableName, $tbData);
        error_log($db->getLastError());
    }

    if ($id > 0) {
        $_SESSION['alertMsg'] = _("TB test request updated successfully");
        //Add event log
        $eventType = 'tb-add-request';
        $action = $_SESSION['userName'] . ' pdated a TB request with the Sample ID/Code  ' . $_POST['tbSampleId'];
        $resource = 'tb-add-request';

        $general->activityLog($eventType, $action, $resource);
    } else {
        $_SESSION['alertMsg'] = _("Unable to update this TB sample. Please try again later");
    }

    if (!empty($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
        header("location:/tb/results/tb-update-result.php?id=" . base64_encode($_POST['tbSampleId']));
    } else {
        header("location:/tb/results/tb-manual-results.php");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
