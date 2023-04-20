<?php

use App\Models\General;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tblName = 's_vlsm_instance';
$general = new General();

if (isset($_POST['instance_facility_name']) && trim($_POST['instance_facility_name']) != "") {
    $instanceName = $_POST['instance_facility_name'];
} else {
    $instanceName = null;
}

if (isset($_POST['instance_facility_code']) && trim($_POST['instance_facility_code']) != "") {
    $instanceCode = $_POST['instance_facility_code'];
} else {
    $instanceCode = null;
}

if (isset($_POST['vl_last_dash_sync']) && trim($_POST['vl_last_dash_sync']) != "") {
    $vlLastSync = explode(" ", $_POST['vl_last_dash_sync']);
    $_POST['vl_last_dash_sync'] = DateUtils::isoDateFormat($vlLastSync[0]) . " " . $vlLastSync[1];
} else {
    $_POST['vl_last_dash_sync'] = null;
}

if (isset($_POST['eid_last_dash_sync']) && trim($_POST['eid_last_dash_sync']) != "") {
    $eidLastSync = explode(" ", $_POST['eid_last_dash_sync']);
    $_POST['eid_last_dash_sync'] = DateUtils::isoDateFormat($eidLastSync[0]) . " " . $eidLastSync[1];
} else {
    $_POST['eid_last_dash_sync'] = null;
}

if (isset($_POST['covid19_last_dash_sync']) && trim($_POST['covid19_last_dash_sync']) != "") {
    $covid19LastSync = explode(" ", $_POST['covid19_last_dash_sync']);
    $_POST['covid19_last_dash_sync'] = DateUtils::isoDateFormat($covid19LastSync[0]) . " " . $covid19LastSync[1];
} else {
    $_POST['covid19_last_dash_sync'] = null;
}

if (isset($_POST['last_remote_requests_sync']) && trim($_POST['last_remote_requests_sync']) != "") {
    $lastRemoteRequestSync = explode(" ", $_POST['last_remote_requests_sync']);
    $_POST['last_remote_requests_sync'] = DateUtils::isoDateFormat($lastRemoteRequestSync[0]) . " " . $lastRemoteRequestSync[1];
} else {
    $_POST['last_remote_requests_sync'] = null;
}

if (isset($_POST['last_remote_results_sync']) && trim($_POST['last_remote_results_sync']) != "") {
    $lastRemoteResultsSync = explode(" ", $_POST['last_remote_results_sync']);
    $_POST['last_remote_results_sync'] = DateUtils::isoDateFormat($lastRemoteResultsSync[0]) . " " . $lastRemoteResultsSync[1];
} else {
    $_POST['last_remote_results_sync'] = null;
}

if (isset($_POST['last_remote_reference_data_sync']) && trim($_POST['last_remote_reference_data_sync']) != "") {
    $lastRemoteReferenceSync = explode(" ", $_POST['last_remote_reference_data_sync']);
    $_POST['last_remote_reference_data_sync'] = DateUtils::isoDateFormat($lastRemoteReferenceSync[0]) . " " . $lastRemoteReferenceSync[1];
} else {
    $_POST['last_remote_reference_data_sync'] = null;
}

if (($_POST['action'] == 'edit') && !empty($_POST['id'])) {
    //update data
    $userData = array(
        'instance_facility_name' => $instanceName,
        'instance_facility_code' => $instanceCode,
        'vl_last_dash_sync' => $_POST['vl_last_dash_sync'],
        'eid_last_dash_sync' => $_POST['eid_last_dash_sync'],
        'covid19_last_dash_sync' => $_POST['covid19_last_dash_sync'],
        'last_remote_requests_sync' => $_POST['last_remote_requests_sync'],
        'last_remote_results_sync' => $_POST['last_remote_results_sync'],
        'last_remote_reference_data_sync' => $_POST['last_remote_reference_data_sync']
    );
    $condition = $db->where('vlsm_instance_id', $_POST['id']);
    $update = $db->update($tblName, $userData, $condition);
    if ($update) {
        $returnData = array(
            'status' => 'ok',
            'msg' => _("Instance data has been updated successfully."),
            'data' => $userData
        );
    } else {
        $returnData = array(
            'status' => 'error',
            'msg' => _("Some problem occurred, please try again."),
            'data' => ''
        );
    }

    echo json_encode($returnData);
}
die();