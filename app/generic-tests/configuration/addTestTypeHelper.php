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
$tableName7 = "generic_test_result_units_map";
$tableName8 = "generic_test_methods_map";
$testAttribute = [];
$_POST['testStandardName'] = trim($_POST['testStandardName']);
$i = 0;
foreach ($_POST['fdropDown'] as $val) {
    $_POST['fdropDown'][$i] = substr($val, 0, -1);
    $i++;
}

try {
    // echo '<pre>'; print_r($_POST['resultConfig']['test_result_unit']); die;
    if (!empty($_POST['testStandardName'])) {
        $cnt = count($_POST['fieldId']);
        $sortFieldOrder = $_POST['fieldOrder'];
        sort($sortFieldOrder);
        $fieldName = $fieldId = $fieldType = $dropDown = $mandatoryField = $section = $sectionOther = $fieldOrder = [];
        for ($i = 0; $i < $cnt; $i++) {
            $index = array_search($sortFieldOrder[$i], $_POST['fieldOrder']);

            if ($_POST['section'][$index] == 'otherSection') {
                $_POST['sectionOther'][$index] = trim($_POST['sectionOther'][$index]);
                $testAttribute[$_POST['section'][$index]][$_POST['sectionOther'][$index]][$_POST['fieldId'][$index]]['field_name'] = $_POST['fieldName'][$index];
                $testAttribute[$_POST['section'][$index]][$_POST['sectionOther'][$index]][$_POST['fieldId'][$index]]['field_type'] = $_POST['fieldType'][$index];
                $testAttribute[$_POST['section'][$index]][$_POST['sectionOther'][$index]][$_POST['fieldId'][$index]]['mandatory_field'] = $_POST['mandatoryField'][$index];
                $testAttribute[$_POST['section'][$index]][$_POST['sectionOther'][$index]][$_POST['fieldId'][$index]]['section'] = $_POST['section'][$index];
                $testAttribute[$_POST['section'][$index]][$_POST['sectionOther'][$index]][$_POST['fieldId'][$index]]['section_name'] = trim($_POST['sectionOther'][$index]);
                $testAttribute[$_POST['section'][$index]][$_POST['sectionOther'][$index]][$_POST['fieldId'][$index]]['field_order'] = $_POST['fieldOrder'][$index];
                if ($_POST['fieldType'][$index] == 'dropdown' || $_POST['fieldType'][$index] == 'multiple') {
                    $testAttribute[$_POST['section'][$index]][$_POST['sectionOther'][$index]][$_POST['fieldId'][$index]]['dropdown_options'] = $_POST['fdropDown'][$index];
                }
            } else {
                $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['field_name'] = $_POST['fieldName'][$index];
                $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['field_type'] = $_POST['fieldType'][$index];
                $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['mandatory_field'] = $_POST['mandatoryField'][$index];
                $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['section'] = $_POST['section'][$index];
                //$testAttr[$_POST['section'][$i]][$_POST['fieldId'][$i]]['section_other']=$_POST['sectionOther'][$i];
                $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['field_order'] = $_POST['fieldOrder'][$index];
                if ($_POST['fieldType'][$index] == 'dropdown' || $_POST['fieldType'][$index] == 'multiple') {
                    $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['dropdown_options'] = $_POST['fdropDown'][$index];
                }
            }
        }
        //print_r(json_encode($testAttribute));die;
        if (!is_numeric($_POST['testCategory'])) {
            $_POST['testCategory'] = $generic->quickInsert('r_generic_test_categories', array('test_category_name', 'test_category_status'), array($_POST['testCategory'], 'active'));
        }
        // Convert to uppercase
        $shortCode = strtoupper($_POST['testShortCode']);
        // Remove all special characters and spaces, except hyphens
        $shortCode = preg_replace('/[^A-Z0-9-]/', '', $shortCode);
        $data = array(
            'test_standard_name' => $_POST['testStandardName'],
            'test_generic_name' => $_POST['testGenericName'],
            'test_short_code' => $shortCode,
            'test_loinc_code' => !empty($_POST['testLoincCode']) ? $_POST['testLoincCode'] : null,
            'test_category' => !empty($_POST['testCategory']) ? $_POST['testCategory'] : null,
            'test_form_config' => json_encode($testAttribute),
            'test_results_config' => json_encode($_POST['resultConfig']),
            'test_status' => $_POST['status']
        );

        $id = $db->insert($tableName, $data);
        $lastId = $db->getInsertId();
        if ($lastId != 0) {
            //echo '<pre>'; print_r($_POST['sampleType']); die;
            if (!empty($_POST['sampleType'])) {
                foreach ($_POST['sampleType'] as $val) {
                    $value = array('sample_type_id' => $val, 'test_type_id' => $lastId);
                    // echo '<pre>'; print_r($value); die;
                    $db->insert($tableName2, $value);
                    error_log($db->getLastError());
                }
            }

            if (!empty($_POST['testingReason'])) {
                foreach ($_POST['testingReason'] as $val) {
                    if (!is_numeric($val)) {
                        $val = $generic->quickInsert('r_generic_test_reasons', array('test_reason_code', 'test_reason', 'test_reason_status'), array($general->generateRandomString(5), $val, 'active'));
                    }
                    $value = array('test_reason_id' => $val, 'test_type_id' => $lastId);
                    $db->insert($tableName3, $value);
                }
            }

            if (!empty($_POST['symptoms'])) {
                foreach ($_POST['symptoms'] as $val) {
                    $value = array('symptom_id' => $val, 'test_type_id' => $lastId);
                    $db->insert($tableName4, $value);
                }
            }

            if (!empty($_POST['testFailureReason'])) {
                foreach ($_POST['testFailureReason'] as $val) {
                    if (!is_numeric($val)) {
                        $val = $generic->quickInsert('r_generic_test_failure_reasons', array('test_failure_reason_code', 'test_failure_reason', 'test_failure_reason_status'), array($general->generateRandomString(5), $val, 'active'));
                    }
                    $value = array('test_failure_reason_id' => $val, 'test_type_id' => $lastId);
                    $db->insert($tableName5, $value);
                }
            }

            if (!empty($_POST['rejectionReason'])) {
                foreach ($_POST['rejectionReason'] as $val) {
                    if (!is_numeric($val)) {
                        $val = $generic->quickInsert('r_generic_sample_rejection_reasons', array('rejection_reason_code', 'rejection_reason_name', 'rejection_reason_status'), array($general->generateRandomString(5), $val, 'active'));
                    }
                    $value = array('rejection_reason_id' => $val, 'test_type_id' => $lastId);
                    $db->insert($tableName6, $value);
                }
            }

            if (!empty($_POST['resultConfig']['test_result_unit'])) {
                foreach ($_POST['resultConfig']['test_result_unit'] as $val) {
                    $value = array('unit_id' => $val, 'test_type_id' => $lastId);
                    $db->insert($tableName7, $value);
                }
            }
            if (!empty($_POST['testMethod'])) {
                foreach ($_POST['testMethod'] as $val) {
                    if (!is_numeric($val)) {
                        $val = $generic->quickInsert('r_generic_test_methods', array('test_method_name', 'test_method_status'), array($val, 'active'));
                    }
                    $value = array('test_method_id' => $val, 'test_type_id' => $lastId);
                    $db->insert($tableName8, $value);
                }
            }
        }
        $_SESSION['alertMsg'] = _translate("Test type added successfully");
    }
    //error_log($db->getLastError());
    header("Location:test-type.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
