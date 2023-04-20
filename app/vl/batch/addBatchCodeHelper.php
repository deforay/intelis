<?php

use App\Models\General;

ob_start();



$general = new General();


$tableName1 = "batch_details";
$tableName2 = "form_vl";
try {
    $exist = $general->existBatchCode($_POST['batchCode']);
    if ($exist) {
        $_SESSION['alertMsg'] = "Something went wrong. Please try again later.";
        header("location:/vl/batch/batchcode.php");
    } else {
        if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != "") {
            $data = array(
                'machine' => $_POST['platform'],
                'batch_code' => $_POST['batchCode'],
                'batch_code_key' => $_POST['batchCodeKey'],
                'position_type' => $_POST['positions'],
                'test_type' => 'vl',
                'created_by' => $_SESSION['userId'],
                'request_created_datetime' => $db->now()
            );
            $db->insert($tableName1, $data);
            $lastId = $db->getInsertId();

            
            if ($lastId > 0 && trim($_POST['selectedSample']) != '') {
                $selectedSample = explode(",", $_POST['selectedSample']);
                $uniqueSampleId = array_unique($selectedSample);
                for ($j = 0; $j <= count($selectedSample); $j++) {
                    if (isset($uniqueSampleId[$j])) {
                      
                        $vlSampleId = $uniqueSampleId[$j];
                        $value = array('sample_batch_id' => $lastId);
                        $db = $db->where('vl_sample_id', $vlSampleId);
                        $db->update($tableName2, $value);
                    }

                }
                header("location:/vl/batch/addBatchControlsPosition.php?id=" . base64_encode($lastId) . "&position=" . $_POST['positions']);
            }
        } else {
            header("location:/vl/batch/batchcode.php");
        }
    }
} catch (Exception $exc) {
    echo ($exc->getMessage());
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
