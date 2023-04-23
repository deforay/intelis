<?php

use App\Services\CommonService;





$general = new CommonService();
$tableName = "r_covid19_comorbidities";
try {
    $id = explode(",", $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'comorbidity_status' => $_POST['status'],
            'updated_datetime'     =>  $db->now(),
        );
        $db = $db->where('comorbidity_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
