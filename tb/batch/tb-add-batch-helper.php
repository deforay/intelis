<?php
ob_start();
  


$general = new \Vlsm\Models\General();


$tableName1 = "batch_details";
$tableName2 = "form_tb";
try {
    $exist = $general->existBatchCode($_POST['batchCode']);
    if ($exist) {
        $_SESSION['alertMsg'] = _("Something went wrong. Please try again later.");
        header("location:tb-batches.php");
    } else {

        if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != "") {
            $data = array(
                'machine' => $_POST['platform'],
                'batch_code' => $_POST['batchCode'],
                'batch_code_key' => $_POST['batchCodeKey'],
                'test_type' => 'tb',
                'request_created_datetime' => $general->getDateTime()
            );

            $db->insert($tableName1, $data);
            $lastId = $db->getInsertId();

            if ($lastId > 0) {
                for ($j = 0; $j < count($_POST['sampleCode']); $j++) {
                    $vlSampleId = $_POST['sampleCode'][$j];
                    $value = array('sample_batch_id' => $lastId);
                    $db = $db->where('tb_id', $vlSampleId);
                    $db->update($tableName2, $value);
                }
                header("location:tb-add-batch-position.php?id=" . base64_encode($lastId));
            }
        } else {
            header("location:tb-batches.php");
        }
    }
} catch (Exception $exc) {
    echo ($exc->getMessage());
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
