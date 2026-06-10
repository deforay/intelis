<?php

use App\Utilities\MiscUtility;
use App\Utilities\DateUtility;
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
$_POST['testStandardName'] = trim((string) $_POST['testStandardName']);
$i = 0;
foreach ($_POST['fdropDown'] as $val) {
    $_POST['fdropDown'][$i] = substr((string) $val, 0, -1);
    $i++;
}

try {
    // echo '<pre>'; print_r($_POST); die;
    if (!empty($_POST['testStandardName'])) {
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
                $testAttribute[$_POST['section'][$index]][$_POST['sectionOther'][$index]][$_POST['fieldId'][$index]]['show_on_report'] = $_POST['showOnReport'][$index] ?? 'no';
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
                $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['show_on_report'] = $_POST['showOnReport'][$index] ?? 'no';
                $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['section'] = $_POST['section'][$index];
                //$testAttr[$_POST['section'][$i]][$_POST['fieldId'][$i]]['section_other']=$_POST['sectionOther'][$i];
                $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['field_order'] = $_POST['fieldOrder'][$index];
                if ($_POST['fieldType'][$index] == 'dropdown' || $_POST['fieldType'][$index] == 'multiple') {
                    $testAttribute[$_POST['section'][$index]][$_POST['fieldId'][$index]]['dropdown_options'] = $_POST['fdropDown'][$index];
                }
            }
        }
        // echo "<pre> => " . $cnt;
        // print_r(json_encode($testAttribute));die;
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

        // Portable per-test identity. An import carries the source UUID so the test
        // keeps the same identity across instances (a later re-import then updates
        // it). Reuse that UUID only when it is genuinely new here; otherwise -- a
        // plain "Add", a clone, or "import as new" of an already-present test -- mint
        // a fresh one so it never collides with the unique index.
        $postedUuid = !empty($_POST['testTypeUuid']) ? trim((string) $_POST['testTypeUuid']) : '';
        if ($postedUuid !== '') {
            $uuidTaken = $db->rawQueryOne("SELECT 1 AS x FROM r_test_types WHERE test_type_uuid = ? LIMIT 1", [$postedUuid]);
            if (!empty($uuidTaken)) {
                $postedUuid = '';
            }
        }
        $testTypeUuid = $postedUuid !== '' ? $postedUuid : MiscUtility::generateUUID(false);

        // Per-group Test Methods are the new source of truth (the standalone Test
        // Methods field was removed). Resolve any newly-typed method names to ids,
        // rewrite the config so the stored JSON holds ids, and collect the union to
        // rebuild generic_test_methods_map (kept for getTestMethod/PDF/interop).
        $resolvedMethodIds = [];
        if (!empty($_POST['resultConfig']['methods']) && is_array($_POST['resultConfig']['methods'])) {
            foreach ($_POST['resultConfig']['methods'] as $gKey => $methodList) {
                if (empty($methodList) || !is_array($methodList)) {
                    continue;
                }
                foreach ($methodList as $mIdx => $mVal) {
                    if ($mVal === '' || $mVal === null) {
                        continue;
                    }
                    if (!is_numeric($mVal)) {
                        $db->insert('r_generic_test_methods', [
                            'test_method_name' => $mVal,
                            'test_method_status' => 'active'
                        ]);
                        $mVal = $db->getInsertId();
                    }
                    $mVal = (int) $mVal;
                    $_POST['resultConfig']['methods'][$gKey][$mIdx] = $mVal;
                    $resolvedMethodIds[$mVal] = $mVal;
                }
            }
        }

        $data = ['test_type_uuid' => $testTypeUuid, 'test_standard_name' => $_POST['testStandardName'], 'test_generic_name' => $_POST['testGenericName'], 'test_short_code' => $shortCode, 'test_loinc_code' => empty($_POST['testLoincCode']) ? null : $_POST['testLoincCode'], 'test_category' => empty($_POST['testCategory']) ? null : $_POST['testCategory'], 'test_form_config' => json_encode($testAttribute), 'test_results_config' => json_encode($_POST['resultConfig']), 'test_status' => $_POST['status'], 'updated_datetime' => DateUtility::getCurrentDateTime()];

        $id = $db->insert($tableName, $data);
        $lastId = $db->getInsertId();
        if ($lastId != 0) {
            //echo '<pre>'; print_r($_POST['sampleType']); die;
            if (!empty($_POST['sampleType'])) {
                foreach ($_POST['sampleType'] as $val) {
                    $value = ['sample_type_id' => $val, 'test_type_id' => $lastId];
                    $db->insert($tableName2, $value);
                }
            }

            if (!empty($_POST['testingReason'])) {
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
                    $value = ['test_reason_id' => $val, 'test_type_id' => $lastId];
                    $db->insert($tableName3, $value);
                }
            }

            if (!empty($_POST['symptoms'])) {
                foreach ($_POST['symptoms'] as $val) {
                    $value = ['symptom_id' => $val, 'test_type_id' => $lastId];
                    $db->insert($tableName4, $value);
                }
            }

            if (!empty($_POST['testFailureReason'])) {
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
                    $value = ['test_failure_reason_id' => $val, 'test_type_id' => $lastId];
                    $db->insert($tableName5, $value);
                }
            }

            if (!empty($_POST['rejectionReason'])) {
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
                    $value = ['rejection_reason_id' => $val, 'test_type_id' => $lastId];
                    $db->insert($tableName6, $value);
                }
            }

            if (!empty($_POST['resultConfig']['test_result_unit'])) {
                foreach ($_POST['resultConfig']['test_result_unit'] as $val) {
                    $value = ['unit_id' => $val, 'test_type_id' => $lastId];
                    $db->insert($tableName7, $value);
                }
            }
            // Build the method map from the union of all result-group methods.
            foreach ($resolvedMethodIds as $val) {
                $value = ['test_method_id' => $val, 'test_type_id' => $lastId];
                $db->insert($tableName8, $value);
            }

            // STS metadata sync selects changed rows by updated_datetime. A config save
            // can re-point a test at pre-existing methods / units / reasons whose own
            // timestamps predate the receiver's last sync, so refresh the mapping rows
            // and the reference rows this test links to -- the whole config then travels
            // as one current snapshot.
            $nowDt = DateUtility::getCurrentDateTime();
            $mapTables = [$tableName2, $tableName3, $tableName4, $tableName5, $tableName6, $tableName7, $tableName8];
            foreach ($mapTables as $mapTable) {
                $db->rawQuery("UPDATE `$mapTable` SET updated_datetime = ? WHERE test_type_id = ?", [$nowDt, $lastId]);
            }
            $refBumps = [
                ['r_generic_test_methods', $tableName8, 'test_method_id'],
                ['r_generic_sample_types', $tableName2, 'sample_type_id'],
                ['r_generic_test_reasons', $tableName3, 'test_reason_id'],
                ['r_generic_symptoms', $tableName4, 'symptom_id'],
                ['r_generic_test_failure_reasons', $tableName5, 'test_failure_reason_id'],
                ['r_generic_sample_rejection_reasons', $tableName6, 'rejection_reason_id'],
                ['r_generic_test_result_units', $tableName7, 'unit_id'],
            ];
            foreach ($refBumps as [$refTable, $mapTable, $idCol]) {
                $db->rawQuery(
                    "UPDATE `$refTable` SET updated_datetime = ? WHERE `$idCol` IN (SELECT `$idCol` FROM `$mapTable` WHERE test_type_id = ?)",
                    [$nowDt, $lastId]
                );
            }
            $db->rawQuery(
                "UPDATE r_generic_test_categories SET updated_datetime = ? WHERE test_category_id = (SELECT test_category FROM r_test_types WHERE test_type_id = ? AND test_category IS NOT NULL)",
                [$nowDt, $lastId]
            );
        }
        $_SESSION['alertMsg'] = _translate("Test type added successfully");
    }
    //error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    header("Location:test-type.php");
} catch (Exception $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
