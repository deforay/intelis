<?php
ob_start();



$general = new \Vlsm\Models\General();
$tableName = "form_vl";
try {

    $id = explode(",", $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'result_status'             => $_POST['status'],
            'result_approved_datetime'  =>  $general->getDateTime(),
            'last_modified_datetime'     =>  $general->getDateTime(),
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

        /* Updating the high and low viral load data */
        if ($status['result_status'] == 4 || $status['result_status'] == 7) {
            $vlDb = new \Vlsm\Models\Vl();
            $status['vl_result_category'] = $vlDb->getVLResultCategory($status['result_status'], $status['result']);
        }
        // echo "<pre>";print_r($status);die;
        $db = $db->where('vl_sample_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];


        //Add event log
        $eventType = 'update-sample-status';
        $action = $_SESSION['userName'] . ' updated VL samples status';
        $resource = 'vl-results';
        $general->activityLog($eventType, $action, $resource);
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
