<?php
ob_start();
#require_once('../../startup.php');  
include_once(APPLICATION_PATH . '/includes/MysqliDb.php');
include_once(APPLICATION_PATH . '/models/General.php');
$general = new General($db);


$tableName1 = "batch_details";
$tableName2 = "vl_request_form";
try {


    if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != "") {
        $data = array(
            'machine' => $_POST['platform'],
            'batch_code' => $_POST['batchCode'],
            'batch_code_key' => $_POST['batchCodeKey'],
            'test_type' => 'vl',
            'request_created_datetime' => $general->getDateTime()
        );

        $db->insert($tableName1, $data);
        $lastId = $db->getInsertId();

        if ($lastId > 0) {
            for ($j = 0; $j < count($_POST['sampleCode']); $j++) {
                $vlSampleId = $_POST['sampleCode'][$j];
                $value = array('sample_batch_id' => $lastId);
                $db = $db->where('vl_sample_id', $vlSampleId);
                $db->update($tableName2, $value);
            }
            header("location:/vl/batch/addBatchControlsPosition.php?id=" . base64_encode($lastId));
        }
    } else {
        header("location:/vl/batch/batchcode.php");
    }
} catch (Exception $exc) {
    echo ($exc->getMessage());
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
