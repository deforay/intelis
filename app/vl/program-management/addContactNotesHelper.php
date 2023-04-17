<?php
ob_start();



$general = new \App\Models\General();

$tableName = "vl_contact_notes";

try {
    $result = '';
    if (isset($_POST['notes']) && trim($_POST['notes']) != "") {
        $data = array(
            'contact_notes' => $_POST['notes'],
            'treament_contact_id' => $_POST['treamentId'],
            'collected_on' => \App\Utilities\DateUtils::isoDateFormat($_POST['dateVal']),
            'added_on' => \App\Utilities\DateUtils::getCurrentDateTime()
        );
        //print_r($data);die;
        $result = $db->insert($tableName, $data);
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
