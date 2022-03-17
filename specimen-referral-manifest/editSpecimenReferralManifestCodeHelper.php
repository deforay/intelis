<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../startup.php');


$general = new \Vlsm\Models\General();
$packageTable = "package_details";
try {
    if (isset($_POST['packageCode']) && trim($_POST['packageCode']) != "" && count($_POST['sampleCode']) > 0) {
        $lastId = $_POST['packageId'];
        $db->where('package_id', $lastId);
        $db->update($packageTable, array(
            'lab_id'         => $_POST['testingLab'],
            'package_status' => $_POST['packageStatus']
        ));

        if ($lastId > 0) {
            $value = array(
                'sample_package_id'   => null,
                'sample_package_code' => null
            );

            if ($_POST['module'] == 'vl') {
                $db = $db->where('sample_package_id', $lastId);
                $db->update('vl_request_form', $value);
            } else if ($_POST['module'] == 'eid') {
                $db = $db->where('sample_package_id', $lastId);
                $db->update('eid_form', $value);
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

            for ($j = 0; $j < count($_POST['sampleCode']); $j++) {
                $value = array(
                    'sample_package_id'   => $lastId,
                    'sample_package_code' => $_POST['packageCode'],
                    'lab_id'    => $_POST['testingLab'],
                    'last_modified_datetime' => $db->now(),
                    'data_sync' => 0
                );
                if ($_POST['module'] == 'vl') {
                    $db = $db->where('vl_sample_id', $_POST['sampleCode'][$j]);
                    $db->update('vl_request_form', $value);
                } else if ($_POST['module'] == 'eid') {
                    $db = $db->where('eid_id', $_POST['sampleCode'][$j]);
                    $db->update('eid_form', $value);
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
            $_SESSION['alertMsg'] = "Manifest details updated successfully";
        }
    }
    header("location:specimenReferralManifestList.php?t=" . base64_encode($_POST['module']));
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
