<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_test_types";
$tableName2 = "generic_test_sample_type_map";
$tableName3 = "generic_test_reason_map";
$tableName4 = "generic_test_symptoms_map";
$tableName5 = "generic_test_failure_reason_map";
$tableName6 = "generic_sample_rejection_reason_map";
$testAttribute = [];
$testTypeId = (int) base64_decode($_POST['testTypeId']);

// echo "<pre>";print_r($_POST);die;
$_POST['testStandardName'] = trim($_POST['testStandardName']);
try {
    if (!empty($_POST['testStandardName']) && $testTypeId > 0) {
        $testAttribute['field_id'] = $_POST['fieldId'];
        $testAttribute['field_name'] = $_POST['fieldName'];
        $testAttribute['field_type'] = $_POST['fieldType'];
        $testAttribute['mandatory_field'] = $_POST['mandatoryField'];
        $testAttribute['section'] = $_POST['section'];
        $testAttribute['section_other'] = $_POST['sectionOther'];

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
        $data = array(
            'test_standard_name' => $_POST['testStandardName'],
            'test_generic_name' => $_POST['testGenericName'],
            'test_short_code' => $_POST['testShortCode'],
            'test_loinc_code' => !empty($_POST['testLoincCode']) ? $_POST['testLoincCode'] : null,
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
                    $value = array('test_reason_id' => $val, 'test_type_id' => $testTypeId);
                    $db->insert($tableName3, $value);
                }
            }

            if (isset($_POST['testFailureReason']) && !empty($_POST['testFailureReason'])) {
                $db = $db->where('test_type_id', $testTypeId);
                $db->delete($tableName5);
                foreach ($_POST['testFailureReason'] as $val) {
                    $value = array('test_failure_reason_id' => $val, 'test_type_id' => $testTypeId);
                    $db->insert($tableName5, $value);
                }
            }

            if (isset($_POST['rejectionReason']) && !empty($_POST['rejectionReason'])) {
                $db = $db->where('test_type_id', $testTypeId);
                $db->delete($tableName6);
                foreach ($_POST['rejectionReason'] as $val) {
                    $value = array('rejection_reason_id' => $val, 'test_type_id' => $testTypeId);
                    $db->insert($tableName6, $value);
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
        }
        $_SESSION['alertMsg'] = _("Test type updated successfully");
    }
    //error_log($db->getLastError());
    header("Location:test-type.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
