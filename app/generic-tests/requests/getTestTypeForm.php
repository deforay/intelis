<?php

use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$testTypeForm = [];
if (!empty($_POST['testTypeForm'])) {
    $testTypeForm = json_decode(base64_decode($_POST['testTypeForm']), true);
}
$disabled = "";
if (!empty($_POST['formType']) && $_POST['formType'] == 'update-form') {
    $disabled = ' disabled ';
}
$resultInterpretation = "";
if (isset($_POST['resultInterpretation']) && $_POST['resultInterpretation'] != "") {
    $resultInterpretation = $_POST['resultInterpretation'];
}
$testResultUnits = $genericTestsService->getTestResultUnit($_POST['testType']);

$testTypeQuery = "SELECT * FROM r_test_types WHERE test_type_id= ?";
$testTypeResult = $db->rawQuery($testTypeQuery, [$_POST['testType']]);
$testAttr = json_decode($testTypeResult[0]['test_form_config'], true);

//print_r($testAttribute);die;

$testResultsAttribute = json_decode($testTypeResult[0]['test_results_config'], true);

$facilityForm = [];
$patientForm = [];
$specimenForm = [];
$labForm = [];
$resultForm = [];
$otherForm = [];
$others = [];
$otherSection = [];
$content = [];
$n = count($testAttr);
if ($n > 0) {
    $i = 1;
    $arraySection = ['facilitySection', 'patientSection', 'specimenSection', 'labSection'];
    foreach ($testAttr as $key => $testAttributeDetails) {
        if (in_array($key, $arraySection)) {
            foreach ($testAttributeDetails as $testAttributeId => $testAttribute) {
                $isRequired = "";
                $mandatory = "";
                $fieldType = "";

                if ($testAttribute['mandatory_field'] == 'yes') {
                    $isRequired = "isRequired";
                    $mandatory = '<span class="mandatory">*</span>';
                }

                $value = "";
                if (!empty($testTypeForm[$testAttributeId])) {
                    $value = $testTypeForm[$testAttributeId];
                }
                if ($testAttribute['field_type'] == 'number') {
                    $fieldType = " forceNumeric";
                } elseif ($testAttribute['field_type'] == 'date') {
                    $fieldType = " dateTime";
                }

                if (!empty($_POST['formType']) && $_POST['formType'] == 'update-form' && $testAttribute['section'] != 'labSection') {
                    $isRequired = "";
                }

                if ($testAttribute['section'] == 'facilitySection') {
                    $inputWidth = "285px !important;";
                    $inputClass = " dynamicFacilitySelect2 ";
                } else {
                    $inputClass = " dynamicSelect2 ";
                    $inputWidth = "100%;";
                }

                if ($testAttribute['field_type'] == 'dropdown' || $testAttribute['field_type'] == 'multiple') {
                    $dropDownName = 'dynamicFields[' . $testAttributeId . ']';
                    if ($testAttribute['field_type'] == 'multiple') {
                        $dropDownName = 'dynamicFields[' . $testAttributeId . '][]';
                    }
                    $dropDownField = '<select name="' . $dropDownName . '" id="' . $testAttributeId . '" class="form-control ' . $inputClass . $isRequired . $fieldType . $disabled . '" title="Please select the option" ' . $testAttribute['field_type'] . ' style="width:' . $inputWidth . ';">';
                    $dropDownField .= '<option value="">-- Select --</option>';
                    foreach (explode(',', $testAttribute['dropdown_options']) as $option) {
                        if ($testAttribute['field_type'] == 'multiple') {
                            $selected = (!empty($value) && in_array(trim($option), $testTypeForm[$testAttributeId])) ? "selected" : "";
                        } else {
                            $selected = (!empty($value) && $value == $option) ? "selected" : "";
                        }
                        $dropDownField .= '<option value="' . trim($option) . '" ' . $selected . '>' . ucwords($option) . '</option>';
                    }
                    $dropDownField .= '</select>';
                }

                $assign = '';

                if ($testAttribute['section'] == 'labSection') {
                    $assign .= '<div class="col-md-6 ' . $testAttribute['section'] . 'Input">';
                    $assign .= '<label class="col-lg-5 control-label labels" for="' . $testAttribute['field_id'] . '">' . $testAttribute['field_name'] . $mandatory . '</label>';
                    $assign .= '<div class="col-lg-7">';
                } elseif ($testAttribute['section'] == 'facilitySection') {
                    $assign .= '<div class="col-xs-4 col-md-4 ' . $testAttribute['section'] . 'Input">';
                    $assign .= '<div class="form-group">';
                    $assign .= '<label class="control-label labels" for="' . $testAttribute['field_id'] . '">' . $testAttribute['field_name'] . $mandatory . '</label><br>';
                } else {
                    $assign .= '<div class="col-xs-3 col-md-3 ' . $testAttribute['section'] . 'Input">';
                    $assign .= '<div class="form-group">';
                    $assign .= '<label class="control-label labels" for="' . $testAttribute['field_id'] . '">' . $testAttribute['field_name'] . $mandatory . '</label>';
                }

                if ($testAttribute['field_type'] == 'dropdown' || $testAttribute['field_type'] == 'multiple') {
                    $assign .= $dropDownField;
                } else {
                    $assign .= '<input type="text" class="form-control ' . $isRequired . $fieldType . $disabled . '" placeholder="' . $testAttribute['field_name'] . '" id="' . $testAttributeId . '" name="dynamicFields[' . $testAttributeId . ']" value="' . $value . '" ' . $disabled . ' style="width:' . $inputWidth . ';">';
                }
                $assign .= '<input type="hidden" class="form-control" name="testTypeId[]" value="' . $testAttributeId . '">';
                $assign .= '</div></div>';

                $result[$testAttribute['section']][] = $assign;
                $i++;
            }
        } else {
            //Othersection code
            foreach ($testAttributeDetails as $testAttributeKey => $otherAttributeDetails) {
                foreach ($otherAttributeDetails as $otherAttributeId => $testAttribute) {
                    $isRequired = "";
                    $mandatory = "";
                    $fieldType = "";

                    if ($testAttribute['mandatory_field'] == 'yes') {
                        $isRequired = "isRequired";
                        $mandatory = '<span class="mandatory">*</span>';
                    }

                    $value = "";
                    if (!empty($testTypeForm[$otherAttributeId])) {
                        $value = $testTypeForm[$otherAttributeId];
                    }
                    if ($testAttribute['field_type'] == 'number') {
                        $fieldType = " forceNumeric";
                    } elseif ($testAttribute['field_type'] == 'date') {
                        $fieldType = " dateTime";
                    }

                    if (!empty($_POST['formType']) && $_POST['formType'] == 'update-form' && $testAttribute['section'] != 'labSection') {
                        $isRequired = "";
                    }

                    $inputClass = " dynamicSelect2 ";
                    $inputWidth = "100%;";

                    if ($testAttribute['field_type'] == 'dropdown' || $testAttribute['field_type'] == 'multiple') {
                        $dropDownName = 'dynamicFields[' . $otherAttributeId . ']';
                        if ($testAttribute['field_type'] == 'multiple') {
                            $dropDownName = 'dynamicFields[' . $otherAttributeId . '][]';
                        }
                        $dropDownField = '<select name="' . $dropDownName . '" id="' . $otherAttributeId . '" class="form-control ' . $inputClass . $isRequired . $fieldType . $disabled . '" title="Please select the option" ' . $testAttribute['field_type'] . ' style="width:' . $inputWidth . ';">';
                        $dropDownField .= '<option value="">-- Select --</option>';
                        foreach (explode(',', $testAttribute['dropdown_options']) as $option) {
                            if ($testAttribute['field_type'] == 'multiple') {
                                $selected = (!empty($value) && in_array(trim($option), $testTypeForm[$otherAttributeId])) ? "selected" : "";
                            } else {
                                $selected = (!empty($value) && $value == $option) ? "selected" : "";
                            }
                            $dropDownField .= '<option value="' . trim($option) . '" ' . $selected . '>' . ucwords($option) . '</option>';
                        }
                        $dropDownField .= '</select>';
                    }

                    if (in_array($testAttribute['section_name'], $otherSection)) {
                        if (!isset($s[trim(strtolower($testAttribute['section_name']))])) {
                            $s[trim(strtolower($testAttribute['section_name']))] = $i;
                        }
                    } else {
                        $s[trim(strtolower($testAttribute['section_name']))] = $i;
                        $otherSection[] = $testAttribute['section_name'];
                    }
                    $title = '<div class="box-header with-border"><h3 class="box-title">' . $testAttribute['section_name'] . '</h3></div>';

                    // Grouping Other Sections via array
                    $content[trim(strtolower($testAttribute['section_name']))] .= '<div class="col-xs-3 col-md-3">';
                    $content[trim(strtolower($testAttribute['section_name']))] .= '<div class="form-group">';
                    $content[trim(strtolower($testAttribute['section_name']))] .= '<label>' . $testAttribute['field_name'] . $mandatory . '</label>';
                    if ($testAttribute['field_type'] == 'dropdown' || $testAttribute['field_type'] == 'multiple') {
                        $content[trim(strtolower($testAttribute['section_name']))] .= $dropDownField;
                    } else {
                        $content[trim(strtolower($testAttribute['section_name']))] .= '<input type="text" class="form-control ' . $isRequired . $fieldType . $disabled . '" placeholder="' . $testAttribute['field_name'] . '" id="' . $otherAttributeId . '" name="dynamicFields[' . $otherAttributeId . ']" value="' . $value . '" ' . $disabled . '>';
                    }
                    $content[trim(strtolower($testAttribute['section_name']))] .= '<input type="hidden" class="form-control" name="testTypeId[]" value="' . $otherAttributeId . '">';
                    $content[trim(strtolower($testAttribute['section_name']))] .= '</div></div>';

                    $others[$s[trim(strtolower($testAttribute['section_name']))]] = $title . '<div class="box-body"><div class="row">' . $content[trim(strtolower($testAttribute['section_name']))] . '</div></div>';
                    $i++;
                }
            }

            $key = 0;
            foreach ($others as $form) {
                $otherForm[$key] = "<div class='box box-primary' style='margin-top: 0px; margin-left: -1px;'>" . $form . "</div></div>";
                $key++;
            }
            $result['otherSection'] = $otherForm;
        }
    }
}

if (!empty($testResultsAttribute)) {
    $resultSection = "";
    if ($testResultsAttribute['result_type'] == 'qualitative') {
        $resultSection .= '<tr><th scope="row" colspan="4" class="text-right final-result-row">Final Result</th>';
        $resultSection .= '<td><select class="form-control result-select" name="result" id="result" onchange="updateInterpretationResult(this);">>';
        $resultSection .= '<option value="">-- Select --</option>';
        if (!empty($testResultsAttribute['result'])) {
            foreach ($testResultsAttribute['result'] as $row) {
                $selected = (isset($_POST['result']) && $_POST['result'] != "" && $_POST['result'] == trim($row)) ? "selected" : "";
                $resultSection .= '<option value="' . trim($row) . '" ' . $selected . '>' . ucwords($row) . '</option>';
            }
        }
        $resultSection .= '</select></td></tr>';
    } else {
        $resultSection .= '<tr><th scope="row" colspan="5" class="text-right final-result-row">Final Result</th>';
        $resultSection .= '<td><input type="text" list="resultList" id="result" name="result" class="form-control result-text" value="' . $_POST['result'] . '" placeholder="Enter final result" title="Please enter final results" onchange="updateInterpretationResult(this);">';
        if (!empty($testResultsAttribute['quantitative_result'])) {
            $resultSection .= '<datalist id="resultList">';
            if (!empty($testResultsAttribute['quantitative_result'])) {
                foreach ($testResultsAttribute['quantitative_result'] as $key => $row) {
                    $selected = (isset($_POST['result']) && $_POST['result'] != "" && $_POST['result'] == trim($row)) ? "selected" : "";
                    $resultSection .= '<option value="' . trim($row) . '" ' . $selected . ' data-interpretation="' . $testResultsAttribute['quantitative_result_interpretation'][$key] . '"> ' . ucwords($row) . ' </option>';
                }
                $resultSection .= '</datalist></td></tr>';
            }
        }
    }
    $resultSection .='<tr class="testResultUnit"><th scope="row" colspan="5" class="text-right final-result-row">Test Result Unit</th>';
    $resultSection .= '<td> 
    <select class="form-control testResultUnit resultUnit" id="finalTestResultUnit" name="finalTestResultUnit" placeholder="Please Enter test result unit" title="Please Enter test result unit"><option value="">--Select--</option>';

        foreach ($testResultUnits as $unit) {
        $selected = isset($_POST['resultUnit']) && $_POST['resultUnit'] == $unit['unit_id'] ? "selected='selected'" : "";
            $resultSection .= '<option value="'.$unit['unit_id'].'" '.$selected.'>'.$unit['unit_name'].'</option>';
    
        }
   
    $resultSection .= '</select></td></tr>';
    $resultSection .='<tr><th scope="row" colspan="5" class="text-right final-result-row">Result Interpretation</th>';
    $resultSection .= '<td><input type="text" placeholder="Interpretation result" title="Please enter the result interpretation" class="form-control" id="resultInterpretation" value="'.$resultInterpretation.'" name="resultInterpretation"></input>';
    $resultSection .= '<input type="hidden" id="resultType" name="resultType" class="form-control result-text" value="' . $testResultsAttribute['result_type'] . '"></td></tr>';
    $resultForm[] = $resultSection;
}
$result['result'] = $resultForm;

echo json_encode($result);
