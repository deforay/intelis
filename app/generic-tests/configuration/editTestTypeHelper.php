<?php

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GenericTestsService $generic */
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
$testTypeId = (int) base64_decode((string) $_POST['testTypeId']);

// echo "<pre>";print_r($_POST);die;
$_POST['testStandardName'] = trim((string) $_POST['testStandardName']);
$i = 0;
foreach ($_POST['fdropDown'] as $val) {
    $_POST['fdropDown'][$i] = substr((string) $val, 0, -1);
    $i++;
}
try {
    if (!empty($_POST['testStandardName']) && $testTypeId > 0) {
        $cnt = count($_POST['fieldName']);
        $sortFieldOrder = $_POST['fieldOrder'];
        sort($sortFieldOrder);
        $fieldName = $fieldId = $fieldType = $dropDown = $mandatoryField = $section = $sectionOther = $fieldOrder = [];
        for ($i = 0; $i < $cnt; $i++) {
            $index = array_search($sortFieldOrder[$i], $_POST['fieldOrder']);

            if ($_POST['section'][$index] == 'otherSection') {
                $_POST['sectionOther'][$index] = trim((string) $_POST['sectionOther'][$index]);
                $testAttribute[$_POST['section'][$index]][$_POST['sectionOther'][$index]][$_POST['fieldId'][$index]]['field_name'] = $_POST['fieldName'][$index];
                $testAttribute[$_POST['section'][$index]][$_POST['sectionOther'][$index]][$_POST['fieldId'][$index]]['field_code'] = $_POST['fieldCode'][$index];
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
                $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['field_code'] = $_POST['fieldCode'][$index];
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
        // echo "<pre> => " . $cnt;print_r($_POST); print_r($testAttribute); die;
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
            $testResultAttribute['qualitative_result'] = explode(",", (string) $_POST['qualitativeResult']);
        }
        if (!is_numeric($_POST['testCategory'])) {
            $d = [
                'test_category_name' => $_POST['testCategory'],
                'test_category_status' => 'active'
            ];
            $db->insert('r_generic_test_categories', $d);
            $_POST['testCategory'] = $db->getInsertId();
        }
        // Convert to uppercase
        $shortCode = strtoupper((string) $_POST['testShortCode']);
        // Remove all special characters and spaces, except hyphens
        $shortCode = preg_replace('/[^A-Z0-9-]/', '', $shortCode);
        $data = ['test_standard_name' => $_POST['testStandardName'], 'test_generic_name' => $_POST['testGenericName'], 'test_short_code' => $shortCode, 'test_loinc_code' => empty($_POST['testLoincCode']) ? null : $_POST['testLoincCode'], 'test_category' => empty($_POST['testCategory']) ? null : $_POST['testCategory'], 'test_form_config' => json_encode($testAttribute), 'test_results_config' => json_encode($_POST['resultConfig']), 'test_status' => $_POST['status']];
        $db->where('test_type_id', $testTypeId);
        $db->update($tableName, $data);

        if ($testTypeId != 0) {
            $db->rawQuery("SET FOREIGN_KEY_CHECKS = 0;"); // Disable foreign key checks
            if (!empty($_POST['sampleType'])) {
                $db->where('test_type_id', $testTypeId);
                $db->delete($tableName2);
                foreach ($_POST['sampleType'] as $val) {
                    $value = ['sample_type_id' => $val, 'test_type_id' => $testTypeId];
                    $db->insert($tableName2, $value);
                }
            }

            if (!empty($_POST['testingReason'])) {
                $db->where('test_type_id', $testTypeId);
                $db->delete($tableName3);
                foreach ($_POST['testingReason'] as $val) {
                    if (!is_numeric($val)) {
                        $d = [
                            'test_reason_code' => MiscUtility::generateRandomString(5),
                            'test_reason' => $val,
                            'test_reason_status' => 'active'
                        ];
                        $db->insert('r_generic_test_reasons', $d);
                        $val = $db->getInsertId();
                    }
                    $value = ['test_reason_id' => $val, 'test_type_id' => $testTypeId];
                    $db->insert($tableName3, $value);
                }
            }
            if (!empty($_POST['symptoms'])) {
                $db->where('test_type_id', $testTypeId);
                $db->delete($tableName4);
                foreach ($_POST['symptoms'] as $val) {
                    $value = ['symptom_id' => $val, 'test_type_id' => $testTypeId];
                    $db->insert($tableName4, $value);
                }
            }

            if (!empty($_POST['testFailureReason'])) {
                $db->where('test_type_id', $testTypeId);
                $db->delete($tableName5);
                foreach ($_POST['testFailureReason'] as $val) {
                    if (!is_numeric($val)) {
                        $d = [
                            'test_failure_reason_code' => MiscUtility::generateRandomString(5),
                            'test_failure_reason' => $val,
                            'test_failure_reason_status' => 'active'
                        ];
                        $db->insert('r_generic_test_failure_reasons', $d);
                        $val = $db->getInsertId();
                    }
                    $value = ['test_failure_reason_id' => $val, 'test_type_id' => $testTypeId];
                    $db->insert($tableName5, $value);
                }
            }

            if (!empty($_POST['rejectionReason'])) {
                $db->where('test_type_id', $testTypeId);
                $db->delete($tableName6);
                foreach ($_POST['rejectionReason'] as $val) {
                    if (!is_numeric($val)) {
                        $d = [
                            'rejection_reason_code' => MiscUtility::generateRandomString(5),
                            'rejection_reason_name' => $val,
                            'rejection_reason_status' => 'active'
                        ];
                        $db->insert('r_generic_sample_rejection_reasons', $d);
                        $val = $db->getInsertId();
                    }
                    $value = ['rejection_reason_id' => $val, 'test_type_id' => $testTypeId];
                    $db->insert($tableName6, $value);
                }
            }

            if (!empty($_POST['resultConfig']['test_result_unit'])) {
                $db->where('test_type_id', $testTypeId);
                $db->delete($tableName7);
                foreach ($_POST['resultConfig']['test_result_unit'] as $val) {
                    $value = ['unit_id' => $val, 'test_type_id' => $testTypeId];
                    $db->insert($tableName7, $value);
                }
            }

            if (!empty($_POST['testMethod'])) {
                $db->where('test_type_id', $testTypeId);
                $db->delete($tableName8);
                foreach ($_POST['testMethod'] as $val) {
                    if (!is_numeric($val)) {
                        $d = [
                            'test_method_name' => $val,
                            'test_method_status' => 'active'
                        ];
                        $db->insert('r_generic_test_methods', $d);
                        $val = $db->getInsertId();
                    }
                    $value = ['test_method_id' => $val, 'test_type_id' => $testTypeId];
                    $db->insert($tableName8, $value);
                }
            }
        }
        $_SESSION['alertMsg'] = _translate("Test type updated successfully");
    }

    header("Location:test-type.php");
} catch (Exception $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
