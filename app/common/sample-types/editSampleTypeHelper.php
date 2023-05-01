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
$tableName = "r_sample_types";

/*echo "<pre>";
print_r($_POST);
die;*/
$sampleTypeId = (int) base64_decode($_POST['sampleTypeId']);
$_POST['sampleTypeName'] = trim($_POST['sampleTypeName']);
try {
    if (!empty($_POST['sampleTypeName'])) {
       
        $data = array(
            'sample_type_name' => $_POST['sampleTypeName'],
            'sample_type_code' => $_POST['sampleTypeCode'],
            'sample_type_status' => $_POST['sampleTypeStatus'],
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        
        $db = $db->where('sample_type_id', $sampleTypeId);
        $db->update($tableName, $data);
        $_SESSION['alertMsg'] = _("Sample type updated successfully");
    }
    error_log($db->getLastError());
    header("location:sampleType.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
