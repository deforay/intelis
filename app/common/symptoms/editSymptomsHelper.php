<?php

use App\Models\General;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

$general = new General();
$tableName = "r_symptoms";

/*echo "<pre>";
print_r($_POST);
die;*/
$symptomId = (int) base64_decode($_POST['symptomId']);
try {
    if (!empty($_POST['symptomName'])) {
       
        $data = array(
            'symptom_name' => trim($_POST['symptomName']),
            'symptom_code' => trim($_POST['symptomCode']),
            'symptom_status' => $_POST['status'],
            'updated_datetime' => DateUtils::getCurrentDateTime()
        );
        
        $db = $db->where('symptom_id', $symptomId);
        $db->update($tableName, $data);
        $_SESSION['alertMsg'] = _("Symptoms Details updated successfully");
    }
    error_log($db->getLastError());
    header("location:symptoms.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
