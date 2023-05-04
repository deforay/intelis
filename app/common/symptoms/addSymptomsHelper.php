<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_symptoms";

/*echo "<pre>";
print_r($_POST);
die;*/
$_POST['symptomName'] = trim($_POST['symptomName']);
try {
    if (!empty($_POST['symptomName'])) {
       
        $data = array(
            'symptom_name' => $_POST['symptomName'],
            'symptom_code' => $_POST['symptomCode'],
            'symptom_status' => $_POST['status'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        
        $id = $db->insert($tableName, $data);
        $lastId = $db->getInsertId();
        if($lastId > 0){
            $_SESSION['alertMsg'] = _("Symptoms details added successfully");
            $general->activityLog('Symptoms', $_SESSION['userName'] . ' added new symptom for ' . $_POST['symptomName'], 'common-symptoms');
        }
        
    }
    //error_log($db->getLastError());
    header("location:symptoms.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
