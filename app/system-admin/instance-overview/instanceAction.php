<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;




$tblName = 's_vlsm_instance';
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_POST['instance_facility_name']) && trim((string) $_POST['instance_facility_name']) != "") {
    $instanceName = $_POST['instance_facility_name'];
} else {
    $instanceName = null;
}

if (isset($_POST['instance_facility_code']) && trim((string) $_POST['instance_facility_code']) != "") {
    $instanceCode = $_POST['instance_facility_code'];
} else {
    $instanceCode = null;
}

if (isset($_POST['vl_last_dash_sync']) && trim((string) $_POST['vl_last_dash_sync']) != "") {
    $vlLastSync = explode(" ", (string) $_POST['vl_last_dash_sync']);
    $_POST['vl_last_dash_sync'] = DateUtility::isoDateFormat($vlLastSync[0]) . " " . $vlLastSync[1];
} else {
    $_POST['vl_last_dash_sync'] = null;
}

if (isset($_POST['eid_last_dash_sync']) && trim((string) $_POST['eid_last_dash_sync']) != "") {
    $eidLastSync = explode(" ", (string) $_POST['eid_last_dash_sync']);
    $_POST['eid_last_dash_sync'] = DateUtility::isoDateFormat($eidLastSync[0]) . " " . $eidLastSync[1];
} else {
    $_POST['eid_last_dash_sync'] = null;
}

if (isset($_POST['covid19_last_dash_sync']) && trim((string) $_POST['covid19_last_dash_sync']) != "") {
    $covid19LastSync = explode(" ", (string) $_POST['covid19_last_dash_sync']);
    $_POST['covid19_last_dash_sync'] = DateUtility::isoDateFormat($covid19LastSync[0]) . " " . $covid19LastSync[1];
} else {
    $_POST['covid19_last_dash_sync'] = null;
}

if (isset($_POST['last_remote_requests_sync']) && trim((string) $_POST['last_remote_requests_sync']) != "") {
    $lastRemoteRequestSync = explode(" ", (string) $_POST['last_remote_requests_sync']);
    $_POST['last_remote_requests_sync'] = DateUtility::isoDateFormat($lastRemoteRequestSync[0]) . " " . $lastRemoteRequestSync[1];
} else {
    $_POST['last_remote_requests_sync'] = null;
}

if (isset($_POST['last_remote_results_sync']) && trim((string) $_POST['last_remote_results_sync']) != "") {
    $lastRemoteResultsSync = explode(" ", (string) $_POST['last_remote_results_sync']);
    $_POST['last_remote_results_sync'] = DateUtility::isoDateFormat($lastRemoteResultsSync[0]) . " " . $lastRemoteResultsSync[1];
} else {
    $_POST['last_remote_results_sync'] = null;
}

if (isset($_POST['last_remote_reference_data_sync']) && trim((string) $_POST['last_remote_reference_data_sync']) != "") {
    $lastRemoteReferenceSync = explode(" ", (string) $_POST['last_remote_reference_data_sync']);
    $_POST['last_remote_reference_data_sync'] = DateUtility::isoDateFormat($lastRemoteReferenceSync[0]) . " " . $lastRemoteReferenceSync[1];
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
            'msg' => _translate("Instance data has been updated successfully."),
            'data' => $userData
        );
    } else {
        $returnData = array(
            'status' => 'error',
            'msg' => _translate("Some problem occurred, please try again."),
            'data' => ''
        );
    }

    echo json_encode($returnData);
}
