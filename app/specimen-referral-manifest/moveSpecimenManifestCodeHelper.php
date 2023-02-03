<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



$general = new \Vlsm\Models\General();
try {
    if (isset($_POST['testingLab']) && trim($_POST['testingLab']) != "" && count($_POST['sampleCode']) > 0) {
        if ($_POST['testType'] == 'vl') {
            $db = $db->where('vl_sample_id IN('.implode(",", $_POST['sampleCode']).')');
            $result = $db->get('form_vl');
        } else if ($_POST['testType'] == 'eid') {
            $db = $db->where('eid_id IN('.implode(",", $_POST['sampleCode']).')');
            $result = $db->get('form_eid');
        } else if ($_POST['testType'] == 'covid19') {
            $db = $db->where('covid19_id IN('.implode(",", $_POST['sampleCode']).')');
            $result = $db->get('form_covid19');
        } else if ($_POST['testType'] == 'hepatitis') {
            $db = $db->where('hepatitis_id IN('.implode(",", $_POST['sampleCode']).')');
            $result = $db->get('form_hepatitis');
        } else if ($_POST['testType'] == 'tb') {
            $db = $db->where('tb_id IN('.implode(",", $_POST['sampleCode']).')');
            $result = $db->get('form_tb');
        }

        $value = array(
            'lab_id'                    => $_POST['testingLab'],
            'referring_lab_id'          => $result[0]['lab_id'],
            'last_modified_datetime'    => $db->now(),
            'samples_referred_datetime' => $db->now(),
            'data_sync'                 => 0
        );
        if ($_POST['testType'] == 'vl') {
            $db = $db->where('vl_sample_id IN('.implode(",", $_POST['sampleCode']).')');
            $db->update('form_vl', $value);
        } else if ($_POST['testType'] == 'eid') {
            $db = $db->where('eid_id IN('.implode(",", $_POST['sampleCode']).')');
            $db->update('form_eid', $value);
        } else if ($_POST['testType'] == 'covid19') {
            $db = $db->where('covid19_id IN('.implode(",", $_POST['sampleCode']).')');
            $db->update('form_covid19', $value);
        } else if ($_POST['testType'] == 'hepatitis') {
            $db = $db->where('hepatitis_id IN('.implode(",", $_POST['sampleCode']).')');
            $db->update('form_hepatitis', $value);
        } else if ($_POST['testType'] == 'tb') {
            $db = $db->where('tb_id IN('.implode(",", $_POST['sampleCode']).')');
            $db->update('form_tb', $value);
        }
        $_SESSION['alertMsg'] = "Manifest details moved successfully";
    }
    header("location:specimenReferralManifestList.php?t=" . base64_encode($_POST['testType']));
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
