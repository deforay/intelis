<?php
ob_start();
#require_once('../../startup.php');


$general = new \Vlsm\Models\General();
$tableName = "r_funding_sources";
try {
    $id = explode(",", $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'funding_source_status' => $_POST['status'],
            'updated_datetime'     =>  $general->getDateTime(),
        );
        $db = $db->where('funding_source_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
