<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GenericTestsService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$generic = ContainerRegistry::get(GenericTestsService::class);

$tableName = "r_test_types";
$tableName2 = "generic_test_sample_type_map";
$tableName3 = "generic_test_reason_map";
$tableName4 = "generic_test_symptoms_map";
$tableName5 = "generic_test_failure_reason_map";
$tableName6 = "generic_sample_rejection_reason_map";
$tableName8 = "generic_test_methods_map";
$testAttribute = [];
$testTypeId = (int) base64_decode($_POST['testTypeId']);

// echo "<pre>";print_r($_POST);die;
$_POST['testStandardName'] = trim($_POST['testStandardName']);
$i=0;
foreach($_POST['fdropDown'] as $val)
{
    $_POST['fdropDown'][$i] = substr($val, 0, -1);
    $i++;
}
try {
    if (!empty($_POST['testStandardName']) && $testTypeId > 0) {

    
        $testAttribute['field_id'] = $_POST['fieldId'];
        $testAttribute['field_name'] = $_POST['fieldName'];
        $testAttribute['field_type'] = $_POST['fieldType'];
        $testAttribute['drop_down'] = $_POST['fdropDown'];
        $testAttribute['mandatory_field'] = $_POST['mandatoryField'];
        $testAttribute['section'] = $_POST['section'];
        $testAttribute['section_other'] = $_POST['sectionOther'];
        $testAttribute['field_order'] = $_POST['fieldOrder'];
        $cnt = count($testAttribute['field_id']);
        $fieldName=$fieldId=$fieldType=$dropDown=$mandatoryField=$section=$sectionOther=$fieldOrder=[];
        for($i=1;$i<=$cnt;$i++)
        {
            $index = array_search($i,$testAttribute['field_order']);
            array_push($fieldId,$testAttribute['field_id'][$index]);
            array_push($fieldName,$testAttribute['field_name'][$index]);
            array_push($fieldType,$testAttribute['field_type'][$index]);
            array_push($dropDown,$testAttribute['drop_down'][$index]);
            array_push($mandatoryField,$testAttribute['mandatory_field'][$index]);
            array_push($section,$testAttribute['section'][$index]);
            array_push($sectionOther,$testAttribute['section_other'][$index]);
            array_push($fieldOrder,$testAttribute['field_order'][$index]);
        }
        $testAttribute['field_name'] = $fieldName;
        $testAttribute['field_id'] = $fieldId;
        $testAttribute['field_name'] = $fieldName;
        $testAttribute['field_type'] = $fieldType;
        $testAttribute['drop_down'] = $dropDown;
        $testAttribute['mandatory_field'] = $mandatoryField;
        $testAttribute['section'] = $section;
        $testAttribute['section_other'] = $sectionOther;
        $testAttribute['field_order'] = $fieldOrder;
       // echo '<pre>'; print_r($testAttribute); die;
        //Result Type
        $testResultAttribute['result_type'] = $_POST['resultType'];
        if (isset($_POST['resultType']) && $_POST['resultType'] == 'quantitative') {
            $testResultAttribute['high_value_name'] = $_POST['highValueName'];
            $testResultAttribute['high_value'] = $_POST['highValue'];
            $testResultAttribute['low_value_name'] = $_POST['lowValueName'];
            $testResultAttribute['low_value'] = $_POST['lowValue'];
            $testResultAttribute['threshold_value_name'] = $_POST['thresholdValueName'];
            $testResultAttribute['threshold_value'] = $_POST['thresholdValue'];
        } else {
            $testResultAttribute['qualitative_result'] = explode(",", $_POST['qualitativeResult']);
        }
        if(!is_numeric($_POST['testCategory'])){
            $_POST['testCategory'] = $generic->quickInsert('r_generic_test_categories', array('test_category_name', 'test_category_status'), array($_POST['testCategory'], 'active'));
        }
        $data = array(
            'test_standard_name' => $_POST['testStandardName'],
            'test_generic_name' => $_POST['testGenericName'],
            'test_short_code' => $_POST['testShortCode'],
            'test_loinc_code' => !empty($_POST['testLoincCode']) ? $_POST['testLoincCode'] : null,
            'test_category' => !empty($_POST['testCategory']) ? $_POST['testCategory'] : null,
            'test_form_config' => json_encode($testAttribute),
            'test_results_config' => json_encode($_POST['resultConfig']),
            'test_status' => $_POST['status']
        );
        $db = $db->where('test_type_id', $testTypeId);
        $db->update($tableName, $data);

        if ($testTypeId != 0) {

            if (isset($_POST['sampleType']) && !empty($_POST['sampleType'])) {
                $db = $db->where('test_type_id', $testTypeId);
                $db->delete($tableName2);
                foreach ($_POST['sampleType'] as $val) {
                    $value = array('sample_type_id' => $val, 'test_type_id' => $testTypeId);
                    $db->insert($tableName2, $value);
                }
            }

            if (isset($_POST['testingReason']) && !empty($_POST['testingReason'])) {
                $db = $db->where('test_type_id', $testTypeId);
                $db->delete($tableName3);
                foreach ($_POST['testingReason'] as $val) {
                    if(!is_numeric($val)){
                        $val = $generic->quickInsert('r_generic_test_reasons', array('test_reason_code', 'test_reason', 'test_reason_status'), array($general->generateRandomString(5), $val, 'active'));
                    }
                    $value = array('test_reason_id' => $val, 'test_type_id' => $testTypeId);
                    $db->insert($tableName3, $value);
                }
            }
            if (isset($_POST['symptoms']) && !empty($_POST['symptoms'])) {
                $db = $db->where('test_type_id', $testTypeId);
                $db->delete($tableName4);
                foreach ($_POST['symptoms'] as $val) {
                    $value = array('symptom_id' => $val, 'test_type_id' => $testTypeId);
                    $db->insert($tableName4, $value);
                }
            }
            
            if (isset($_POST['testFailureReason']) && !empty($_POST['testFailureReason'])) {
                $db = $db->where('test_type_id', $testTypeId);
                $db->delete($tableName5);
                foreach ($_POST['testFailureReason'] as $val) {
                    if(!is_numeric($val)){
                        $val = $generic->quickInsert('r_generic_test_failure_reasons', array('test_failure_reason_code', 'test_failure_reason', 'test_failure_reason_status'), array($general->generateRandomString(5), $val, 'active'));
                    }
                    $value = array('test_failure_reason_id' => $val, 'test_type_id' => $testTypeId);
                    $db->insert($tableName5, $value);
                }
            }

            if (isset($_POST['rejectionReason']) && !empty($_POST['rejectionReason'])) {
                $db = $db->where('test_type_id', $testTypeId);
                $db->delete($tableName6);
                foreach ($_POST['rejectionReason'] as $val) {
                    if(!is_numeric($val)){
                        $val = $generic->quickInsert('r_generic_sample_rejection_reasons', array('rejection_reason_code', 'rejection_reason_name', 'rejection_reason_status'), array($general->generateRandomString(5), $val, 'active'));
                    }
                    $value = array('rejection_reason_id' => $val, 'test_type_id' => $testTypeId);
                    $db->insert($tableName6, $value);
                }
            }

            if (!empty($_POST['testMethod'])) {
                $db = $db->where('test_type_id', $testTypeId);
                $db->delete($tableName8);
                foreach ($_POST['testMethod'] as $val) {
                    if(!is_numeric($val)){
                        $val = $generic->quickInsert('r_generic_test_methods', array('test_method_name', 'test_method_status'), array($val, 'active'));
                    }
                    $value = array('test_method_id' => $val, 'test_type_id' => $testTypeId);
                    $db->insert($tableName8, $value);
                }
            }
        }
        $_SESSION['alertMsg'] = _("Test type updated successfully");
    }
    //error_log($db->getLastError());
    header("Location:test-type.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
