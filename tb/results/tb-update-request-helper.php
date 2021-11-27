<?php

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new \Vlsm\Models\General();
$geoLocationDb = new \Vlsm\Models\GeoLocations();

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

    if (!empty($_POST['sampleTestedDateTime']) && trim($_POST['sampleTestedDateTime']) != "") {
        $sampleTestedDate = explode(" ", $_POST['sampleTestedDateTime']);
        $_POST['sampleTestedDateTime'] = $general->dateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
    } else {
        $_POST['sampleTestedDateTime'] = NULL;
    }
    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = $general->dateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = NULL;
    }

    if (isset($_POST['approvedOn']) && trim($_POST['approvedOn']) != "") {
        $approvedOn = explode(" ", $_POST['approvedOn']);
        $_POST['approvedOn'] = $general->dateFormat($approvedOn[0]) . " " . $approvedOn[1];
    } else {
        $_POST['approvedOn'] = NULL;
    }

    $tbData = array(
        'lab_id'                              => !empty($_POST['labId']) ? $_POST['labId'] : null,
        'is_sample_rejected'                  => !empty($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,
        'result'                              => !empty($_POST['result']) ? $_POST['result'] : null,
        'xpert_mtb_result'                    => !empty($_POST['xPertMTMResult']) ? $_POST['xPertMTMResult'] : null,
        'result_reviewed_by'                  => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime'            => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by'                  => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != "") ? $_POST['approvedBy'] : "",
        'result_approved_datetime'            => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != "") ? $_POST['approvedOn'] : null,
        'sample_tested_datetime'              => (isset($_POST['sampleTestedDateTime']) && $_POST['sampleTestedDateTime'] != "") ? $_POST['sampleTestedDateTime'] : null,
        'tested_by'                           => !empty($_POST['testedBy']) ? $_POST['testedBy'] : null,
        'rejection_on'                        => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? $general->dateFormat($_POST['rejectionDate']) : null,
        'result_status'                       => 8,
        'data_sync'                           => 0,
        'reason_for_sample_rejection'         => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
        'last_modified_by'                    => $_SESSION['userId'],
        'last_modified_datetime'              => $general->getDateTime(),
        'last_modified_by'                    => $_SESSION['userId'],
        'lab_technician'                      => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  $_SESSION['userId'],
        'result_printed_datetime'             => NULL,
        'result_dispatched_datetime'          => NULL,
        'source_of_request'                   => "web"
    );

    $id = 0;

    if (isset($_POST['tbSampleId']) && $_POST['tbSampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
        if (isset($_POST['testResult']) && count($_POST['testResult']) > 0) {
            $db = $db->where('tb_id', $_POST['tbSampleId']);
            $db->delete($testTableName);

            foreach ($_POST['testResult'] as $testKey => $testResult) {
                if (isset($testResult) && !empty($testResult)) {
                    $db->insert($testTableName, array(
                        'tb_id'             => $_POST['tbSampleId'],
                        'actual_no'         => isset($_POST['actualNo'][$testKey]) ? $_POST['actualNo'][$testKey] : null,
                        'test_result'       => $testResult,
                        'updated_datetime'  => $general->getDateTime()
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
    }

    if ($id > 0) {
        $_SESSION['alertMsg'] = "TB test request updated successfully";
        //Add event log
        $eventType = 'tb-add-request';
        $action = ucwords($_SESSION['userName']) . ' pdated a TB request data with the sample id ' . $_POST['tbSampleId'];
        $resource = 'tb-add-request';

        $general->activityLog($eventType, $action, $resource);
    } else {
        $_SESSION['alertMsg'] = "Unable to update this TB sample. Please try again later";
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
