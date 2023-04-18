<?php

use App\Models\General;

ob_start();



$general = new General();
$tableName = "r_covid19_sample_type";
try {
    $id = explode(",", $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'status' => $_POST['status'],
            'updated_datetime'     =>  $db->now(),
        );
        $db = $db->where('sample_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
