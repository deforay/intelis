<?php

use App\Models\General;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



$general = new General();
$packageTable = "package_details";
try {
    if (isset($_POST['packageCode']) && trim($_POST['packageCode']) != "") {
        $data = array(
            'package_code'              => $_POST['packageCode'],
            'module'                    => $_POST['module'],
            'added_by'                  => $_SESSION['userId'],
            'lab_id'                    => $_POST['testingLab'],
            'number_of_samples'         => count($_POST['sampleCode']),
            'package_status'            => 'pending',
            'request_created_datetime'  => $db->now(),
            'last_modified_datetime' => $db->now()
        );
        //var_dump($data);die;
        $db->insert($packageTable, $data);
        $lastId = $db->getInsertId();
        if ($lastId > 0) {
            for ($j = 0; $j < count($_POST['sampleCode']); $j++) {
                $value = array(
                    'sample_package_id' => $lastId,
                    'sample_package_code' => $_POST['packageCode'],
                    'lab_id'    => $_POST['testingLab'],
                    'last_modified_datetime' => $db->now(),
                    'data_sync' => 0
                );
                if ($_POST['module'] == 'vl') {
                    $db = $db->where('vl_sample_id', $_POST['sampleCode'][$j]);
                    $db->update('form_vl', $value);
                } else if ($_POST['module'] == 'eid') {
                    $db = $db->where('eid_id', $_POST['sampleCode'][$j]);
                    $db->update('form_eid', $value);
                } else if ($_POST['module'] == 'covid19') {
                    $db = $db->where('covid19_id', $_POST['sampleCode'][$j]);
                    $db->update('form_covid19', $value);
                } else if ($_POST['module'] == 'hepatitis') {
                    $db = $db->where('hepatitis_id', $_POST['sampleCode'][$j]);
                    $db->update('form_hepatitis', $value);
                } else if ($_POST['module'] == 'tb') {
                    $db = $db->where('tb_id', $_POST['sampleCode'][$j]);
                    $db->update('form_tb', $value);
                }
            }
            $_SESSION['alertMsg'] = "Manifest added successfully";
        }
    }


    //Add event log
    $eventType = 'add-manifest';
    $action = $_SESSION['userName'] . ' added Sample Manifest ' . $_POST['packageCode'];
    $resource = 'specimen-manifest';

    $general->activityLog($eventType, $action, $resource);

    header("location:specimenReferralManifestList.php?t=" . base64_encode($_POST['module']));
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
