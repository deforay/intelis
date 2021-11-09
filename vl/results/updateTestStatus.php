<?php
ob_start();
#require_once('../../startup.php');


$general = new \Vlsm\Models\General();
$tableName = "vl_request_form";
try {
    $lock = $general->getGlobalConfig('lock_approved_vl_samples');
    $id = explode(",", $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'result_status'             => $_POST['status'],
            'result_approved_datetime'  =>  $general->getDateTime(),
            'data_sync'                 => 0
        );
        /* Check if already have reviewed and approved by */
        $db = $db->where('vl_sample_id', $id[$i]);
        $reviewd = $db->getOne($tableName, array("result_reviewed_by", "result_approved_by"));
        if (empty($reviewd['result_reviewed_by'])) {
            $status['result_reviewed_by'] = $_SESSION['userId'];
        }
        if (empty($reviewd['result_approved_by'])) {
            $status['result_approved_by'] = $_SESSION['userId'];
        }
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

        if ($status['result_status'] == 7 && $lock == 'yes') {
            $status['locked'] = 'yes';
        }
        /* Updating the high and low viral load data */
        if ($status['result_status'] == 4 || $status['result_status'] == 7) {
            $vlDb = new \Vlsm\Models\Vl();
            $status['vl_result_category'] = $vlDb->getVLResultCategory($status['result_status'], $status['result']);
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
