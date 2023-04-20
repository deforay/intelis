<?php

use App\Models\General;





$general = new General();
$tableName = "r_hepatitis_risk_factors";
try {
    $id = explode(",", $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'riskfactor_status' => $_POST['status'],
            'updated_datetime'     =>  $db->now(),
        );
        $db = $db->where('riskfactor_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
