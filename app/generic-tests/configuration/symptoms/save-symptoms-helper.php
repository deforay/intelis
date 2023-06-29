<?php

use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

$general = new CommonService();
$tableName = "r_generic_symptoms";
$symptomId = (int) base64_decode($_POST['symptomId']);
try {
    if (!empty($_POST['symptomName'])) {
       
        $data = array(
            'symptom_name' => trim($_POST['symptomName']),
            'symptom_code' => trim($_POST['symptomCode']),
            'symptom_status' => $_POST['status'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        if(!empty($symptomId)){
            $db = $db->where('symptom_id', $symptomId);
            $lastId = $db->update($tableName, $data);
            if($lastId > 0){
                $_SESSION['alertMsg'] = _("Symptoms Details updated successfully");
                $general->activityLog('Symptoms', $_SESSION['userName'] . ' updated new symptom for ' . $_POST['symptomName'], 'generic-symptoms');
            }
        }else{
            $id = $db->insert($tableName, $data);
            $lastId = $db->getInsertId();
            if($lastId > 0){
                $_SESSION['alertMsg'] = _("Symptoms details added successfully");
                $general->activityLog('Symptoms', $_SESSION['userName'] . ' added new symptom for ' . $_POST['symptomName'], 'generic-symptoms');
            }
        }
    }
    error_log($db->getLastError());
    header("location:generic-symptoms.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
