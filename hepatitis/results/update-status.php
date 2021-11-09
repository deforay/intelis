<?php
ob_start();
#require_once('../../startup.php');


$general = new \Vlsm\Models\General();
$tableName = "form_hepatitis";
try {
    $id = explode(",", $_POST['id']);
    // $lock = $general->getGlobalConfig('lock_approved_hepatitis_samples');
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'result_status'         => $_POST['status'],
            'data_sync'             => 0
        );
        /* Check if already have reviewed and approved by */
        $db = $db->where('hepatitis_id', $id[$i]);
        $reviewd = $db->getOne($tableName, array("result_reviewed_by", "result_approved_by"));
        if (empty($reviewd['result_reviewed_by'])) {
            $status['result_reviewed_by'] = $_SESSION['userId'];
        }
        if (empty($reviewd['result_approved_by'])) {
            $status['result_approved_by'] = $_SESSION['userId'];
        }
        if ($_POST['status'] == '4') {
            $status['result'] = null;
            $status['is_sample_rejected'] = 'yes';
            $status['reason_for_sample_rejection'] = $_POST['rejectedReason'];
        } else {
            $status['is_sample_rejected'] = 'no';
            $status['reason_for_sample_rejection'] = null;
        }
        /* if($status['result_status'] == 7 && $lock == 'yes'){
            $status['locked'] = 'yes';
        } */
        $db = $db->where('hepatitis_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
