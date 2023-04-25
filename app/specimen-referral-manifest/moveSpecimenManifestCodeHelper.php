<?php

use App\Services\CommonService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$table = 'form_vl';
if ($_POST['testType'] == 'vl') {
    $table = 'form_vl';
} else if ($_POST['testType'] == 'eid') {
    $table = 'form_eid';
} else if ($_POST['testType'] == 'covid19') {
    $table = 'form_covid19';
} else if ($_POST['testType'] == 'hepatitis') {
    $table = 'form_hepatitis';
} else if ($_POST['testType'] == 'tb') {
    $table = 'form_tb';
}

$general = new CommonService();
try {
    if (isset($_POST['assignLab']) && trim($_POST['assignLab']) != "" && count($_POST['packageCode']) > 0) {
        $value = array(
            'lab_id'                    => $_POST['assignLab'],
            'referring_lab_id'          => $_POST['testingLab'],
            'last_modified_datetime'    => $db->now(),
            'samples_referred_datetime' => $db->now(),
            'data_sync'                 => 0
        );
        /* Update Package details table */
        $db = $db->where('package_code IN(' . implode(",", $_POST['packageCode']) . ')');
        $db->update('package_details', array("lab_id" => $_POST['assignLab']));

        /* Update test types */
        $db = $db->where('sample_package_code IN(' . implode(",", $_POST['packageCode']) . ')');
        $db->update($table, $value);

        $_SESSION['alertMsg'] = "Manifest code(s) moved successfully";
    }

    //Add event log
    $eventType = 'move-manifest';
    $action = $_SESSION['userName'] . ' moved Sample Manifest ' . $_POST['packageCode']. ' to lab '.$_POST['assignLab'] . ' from lab '.$_POST['testingLab'];
    $resource = 'specimen-manifest';

    $general->activityLog($eventType, $action, $resource);

    header("Location:specimenReferralManifestList.php?t=" . base64_encode($_POST['testType']));
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
