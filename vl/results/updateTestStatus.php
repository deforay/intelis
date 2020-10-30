<?php
ob_start();
#require_once('../../startup.php');


$general = new \Vlsm\Models\General($db);
$tableName = "vl_request_form";
try {
    $lock = $general->getGlobalConfig('lock_approved_vl_samples');
    $id = explode(",", $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'result_status'         => $_POST['status'],
            'result_approved_by'    => $_SESSION['userId'],
            'data_sync'             => 0
        );
        
        if ($_POST['status'] == '4') {
            $status['result_value_log'] = '';
            $status['result_value_absolute'] = '';
            $status['result_value_text'] = '';
            $status['result_value_absolute_decimal'] = '';
            $status['result'] = '';
            $status['is_sample_rejected'] = 'yes';
            $status['reason_for_sample_rejection'] = $_POST['rejectedReason'];
        } else {
            $status['is_sample_rejected'] = 'no';
        }

        if($status['result_status'] == 7 && $lock == 'yes'){
            $status['locked'] = 'yes';
        }
        // echo "<pre>";print_r($status);die;
        $db = $db->where('vl_sample_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
