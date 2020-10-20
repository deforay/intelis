<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../startup.php');


$general = new \Vlsm\Models\General($db);
$packageTable = "package_details";
try {
    if (isset($_POST['packageCode']) && trim($_POST['packageCode']) != "") {
        $data = array(
            'package_code'              => $_POST['packageCode'],
            'module'                    => $_POST['module'],
            'added_by'                  => $_SESSION['userId'],
            'lab_id'                    => $_POST['testingLab'],
            'package_status'            => 'pending',
            'request_created_datetime'  => $general->getDateTime()
        );
        //var_dump($data);die;
        $db->insert($packageTable, $data);
        $lastId = $db->getInsertId();
        if ($lastId > 0) {
            for ($j = 0; $j < count($_POST['sampleCode']); $j++) {
                $value = array(
                    'sample_package_id' => $lastId,
                    'sample_package_code' => $_POST['packageCode'],
                    'data_sync' => 0
                );
                if ($_POST['module'] == 'vl') {
                    $db = $db->where('vl_sample_id', $_POST['sampleCode'][$j]);
                    $db->update('vl_request_form', $value);
                } else if ($_POST['module'] == 'eid') {
                    $db = $db->where('eid_id', $_POST['sampleCode'][$j]);
                    $db->update('eid_form', $value);
                } else if ($_POST['module'] == 'C19') {
                    $db = $db->where('covid19_id', $_POST['sampleCode'][$j]);
                    $db->update('form_covid19', $value);
                }
            }
            $_SESSION['alertMsg'] = "Manifest added successfully";
        }
    }
    header("location:specimenReferralManifestList.php?t=" . base64_encode($_POST['module']));
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
