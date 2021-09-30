<?php
#require_once('../../startup.php');
// echo "<pre>";print_r($_POST['eidId']);die;
try {
    $general = new \Vlsm\Models\General($db);
    $sarr = $general->getSystemConfig();
    /* Status definition */
    $status = 6;
    if ($sarr['sc_user_type'] == 'remoteuser') {
        $status = 9;
    }
    if ($_POST['bulkIds'] && is_array($_POST['eidId'])) {
        $db = $db->where("`eid_id` IN (" . implode(",", $_POST['eidId']) . ")");
    } else {
        $db = $db->where('eid_id', base64_decode($_POST['eidId']));
    }
    $id = $db->update("eid_form", array(
        "result"            => null,
        "sample_batch_id"   => null,
        "result_status"     => $status
    ));

    if ($id > 0) {
        $query = "SELECT sample_code, remote_sample_code, facility_id, sample_batch_id, result, result_status, eid_id FROM eid_form";
        if ($_POST['bulkIds'] && is_array($_POST['eidId'])) {
            $query .= " WHERE eid_id IN (" . implode(",", $_POST['eidId']) . ")";
        } else {
            $query .= " WHERE eid_id = " . base64_decode($_POST['eidId']);
        }
        $response = $db->rawQuery($query);
        foreach ($response as $result) {
            if (isset($result['eid_id']) && $result['eid_id'] != "") {
                $db->insert('failed_result_retest_tracker', array(
                    'test_type_pid'         => (isset($result['eid_id']) && $result['eid_id'] != "") ? $result['eid_id'] : null,
                    'test_type'             => 'eid',
                    'sample_code'           => (isset($result['sample_code']) && $result['sample_code'] != "") ? $result['sample_code'] : null,
                    'remote_sample_code'    => (isset($result['remote_sample_code']) && $result['remote_sample_code'] != "") ? $result['remote_sample_code'] : null,
                    'batch_id'              => (isset($result['sample_batch_id']) && $result['sample_batch_id'] != "") ? $result['sample_batch_id'] : null,
                    'facility_id'           => (isset($result['facility_id']) && $result['facility_id'] != "") ? $result['facility_id'] : null,
                    'result'                => (isset($result['result']) && $result['result'] != "") ? $result['result'] : null,
                    'result_status'         => (isset($result['result_status']) && $result['result_status'] != "") ? $result['result_status'] : null,
                    'updated_datetime'      => $general->getDateTime(),
                    'update_by'             => $_SESSION['userId']
                ));
            }
        }
    }
    echo $id;
}
//catch exception
catch (Exception $e) {
    echo 'EID failed-results-retest.php: ' . $e->getMessage();
}
