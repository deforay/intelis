<?php

use App\Services\CommonService;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$general = new CommonService();
$tableName = "r_test_types";
$testAttribute=array();
$testResultAttribute=array();
/*echo "<pre>";
print_r($_POST);
die;*/
$_POST['testStandardName'] = trim($_POST['testStandardName']);
try {
    if (!empty($_POST['testStandardName'])) {
        $testAttribute['field_id']=$_POST['fieldId'];
        $testAttribute['field_name']=$_POST['fieldName'];
        $testAttribute['field_type']=$_POST['fieldType'];
        $testAttribute['mandatory_field']=$_POST['mandatoryField'];
        $testAttribute['section']=$_POST['section'];
        $testAttribute['section_other']=$_POST['sectionOther'];
        //Result Type
        $testResultAttribute['result_type']=$_POST['resultType'];
        if(isset($_POST['resultType']) && $_POST['resultType']=='quantitative'){
            $testResultAttribute['high_value_name']=$_POST['highValueName'];
            $testResultAttribute['high_value']=$_POST['highValue'];
            $testResultAttribute['low_value_name']=$_POST['lowValueName'];
            $testResultAttribute['low_value']=$_POST['lowValue'];
            $testResultAttribute['threshold_value_name']=$_POST['thresholdValueName'];
            $testResultAttribute['threshold_value']=$_POST['thresholdValue'];
        }else{
            $testResultAttribute['qualitative_result']=explode(",", $_POST['qualitativeResult']);
        }
        
        $data = array(
            'test_standard_name' => $_POST['testStandardName'],
            'test_generic_name' => $_POST['testGenericName'],
            'test_short_code' => $_POST['testShortCode'],
            'test_loinc_code' => !empty($_POST['testLoincCode']) ? $_POST['testLoincCode'] : null,
            'test_form_config' => json_encode($testAttribute),
            'test_results_config' => json_encode($testResultAttribute),
            'test_status' => $_POST['status']
        );
        
        $id = $db->insert($tableName, $data);
        
        $_SESSION['alertMsg'] = _("Test type added successfully");
    }
    error_log($db->getLastError());
    header("Location:testType.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
