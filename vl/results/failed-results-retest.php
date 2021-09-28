<?php
#require_once('../../startup.php');
try {
    $general = new \Vlsm\Models\General($db);
    $sarr = $general->getSystemConfig();
    /* Status definition */
    $status = 6;
    if ($sarr['sc_user_type'] == 'remoteuser') {
        $status = 9;
    }

    $db = $db->where('vl_sample_id', base64_decode($_POST['vlId']));
    $id = $db->update("vl_request_form", array(
        "result_value_log"              => null,
        "result_value_absolute"         => null,
        "result_value_text"             => null,
        "result_value_absolute_decimal" => null,
        "result"                        => null,
        "result_status"                 => $status
    ));

    if ($id > 0) {
        $query = "SELECT CASE WHEN (sample_code is not null) THEN sample_code ELSE remote_sample_code END AS sampleCode, result, result_status, vl_sample_id FROM vl_request_form WHERE vl_sample_id = " . base64_decode($_POST['vlId']);
        $result = $db->rawQueryOne($query);
        if (isset($result['vl_sample_id']) && $result['vl_sample_id'] != "")
            $db->insert('failed_result_retest_tracker', array(
                'test_type_pid' => (isset($result['vl_sample_id']) && $result['vl_sample_id'] != "") ? $result['vl_sample_id'] : null,
                'test_type' => 'vl',
                'sample_code' => (isset($result['sampleCode']) && $result['sampleCode'] != "") ? $result['sampleCode'] : null,
                'result' => (isset($result['result']) && $result['result'] != "") ? $result['result'] : null,
                'result_status' => (isset($result['result_status']) && $result['result_status'] != "") ? $result['result_status'] : null,
                'updated_datetime' => $general->getDateTime(),
                'update_by' => $_SESSION['userId']
            ));
    }
    echo $id;
}
//catch exception
catch (Exception $e) {
    echo 'VL failed-results-retest.php: ' . $e->getMessage();
}
