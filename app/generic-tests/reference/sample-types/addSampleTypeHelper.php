<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_generic_sample_types";

/*echo "<pre>";
print_r($_POST);
die;*/
$_POST['sampleTypeName'] = trim($_POST['sampleTypeName']);
try {
    if (!empty($_POST['sampleTypeName'])) {
       
        $data = array(
            'sample_type_name' => $_POST['sampleTypeName'],
            'sample_type_code' => $_POST['sampleTypeCode'],
            'sample_type_status' => $_POST['sampleTypeStatus'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        
        $id = $db->insert($tableName, $data);
        $lastId = $db->getInsertId();
        if($lastId > 0){
            $_SESSION['alertMsg'] = _("Sample type added successfully");
            $general->activityLog('Sample Type', $_SESSION['userName'] . ' added new sample type for ' . $_POST['sampleTypeName'], 'common-sample-types');
        }
        
    }
    error_log($db->getLastError());
    header("location:sampleType.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
