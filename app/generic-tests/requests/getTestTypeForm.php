<?php

use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

$testTypeForm = [];
if (!empty($_POST['testTypeForm'])) {
    $testTypeForm = json_decode(base64_decode($_POST['testTypeForm']), true);
}
$disabled = "";
if (!empty($_POST['formType']) && $_POST['formType'] == 'update-form') {
    $disabled = ' disabled ';
}
$resultInterpretation = $_POST['resultInterpretation'] ?? "";
$testResultUnits = $genericTestsService->getTestResultUnit($_POST['testType']);

$testTypeQuery = "SELECT * FROM r_test_types WHERE test_type_id= ?";
$testTypeResult = $db->rawQuery($testTypeQuery, [$_POST['testType']]);
$testAttr = json_decode($testTypeResult[0]['test_form_config'], true);


$sections = ['facilitySection', 'patientSection', 'specimenSection', 'labSection', 'otherSection', 'result'];
$result = array_fill_keys($sections, []);

$testResultsAttribute = json_decode($testTypeResult[0]['test_results_config'], true);

$othersSectionFields = [];
$otherSection = [];
$content = [];
$n = count($testAttr);

function getFieldType($fieldType)
{
    return once(function () use ($fieldType) {
        if ($fieldType == 'number') {
            return " forceNumeric ";
        } elseif ($fieldType == 'date') {
            return " dateTime ";
        }
        return "";
    });
}

function getDropDownField($testAttribute, $value, $inputClass, $isRequired, $fieldType, $disabled, $inputWidth)
{
    $fieldName = 'dynamicFields[' . $testAttribute['field_id'] . ']';
    if ($testAttribute['field_type'] == 'multiple') {
        $fieldName .= '[]';
    }
    $field = sprintf(
        '<div class="col-lg-7"><select name="%s" id="%s" class="form-control %s%s%s%s" title="Please select the option" %s style="width:%s;">',
        $fieldName,
        $testAttribute['field_id'],
        $inputClass,
        $isRequired,
        $fieldType,
        $disabled,
        $testAttribute['field_type'],
        $inputWidth
    );
    $field .= '<option value="">-- Select --</option>';
    foreach (explode(',', $testAttribute['dropdown_options']) as $option) {
        if ($testAttribute['field_type'] == 'multiple') {
            $selected = (!empty($value) && in_array(trim($option), $value)) ? "selected" : "";
        } else {
            $selected = (!empty($value) && $value == $option) ? "selected" : "";
        }
        $field .= '<option value="' . trim($option) . '" ' . $selected . '>' . ($option) . '</option>';
    }
    $field .= '</select></div>';
    return $field;
}

function getField($testAttribute, $testAttributeId, $value, $inputClass, $isRequired, $fieldType, $disabled, $inputWidth)
{
    $field = '';
    if ($testAttribute['field_type'] == 'dropdown' || $testAttribute['field_type'] == 'multiple') {
        $field .= getDropDownField($testAttribute, $value, $inputClass, $isRequired, $fieldType, $disabled, $inputWidth);
    } else {
        $field = sprintf(
            '<div class="col-lg-7"><input type="text" class="form-control %s%s%s" placeholder="%s" id="%s" name="dynamicFields[%s]" value="%s" %s style="width:%s;"></div>',
            $isRequired,
            $fieldType,
            $disabled,
            $testAttribute['field_name'],
            $testAttributeId,
            $testAttributeId,
            $value,
            $disabled,
            $inputWidth
        );
    }
    $field .= '<input type="hidden" class="form-control" name="testTypeId[]" value="' . $testAttributeId . '">';

    return $field;
}

if ($n > 0) {
    $i = 1;
    $arraySection = ['facilitySection', 'patientSection', 'specimenSection', 'labSection'];
    foreach ($testAttr as $key => $testAttributeDetails) {
        if (in_array($key, $arraySection)) {
            foreach ($testAttributeDetails as $testAttributeId => $testAttribute) {
                $isRequired = $testAttribute['mandatory_field'] === 'yes' ? 'isRequired' : '';
                $mandatory = $testAttribute['mandatory_field'] === 'yes' ? '<span class="mandatory">*</span>' : '';
                $value = $testTypeForm[$testAttributeId] ?? '';
                $fieldType = getFieldType($testAttribute['field_type']);
                if (
                    !empty($_POST['formType']) &&
                    $_POST['formType'] == 'update-form' &&
                    $testAttribute['section'] != 'labSection'
                ) {
                    $isRequired = "";
                }

                $inputClass = $testAttribute['section'] == 'facilitySection' ? " dynamicFacilitySelect2 " : " dynamicSelect2 ";
                $inputWidth = $testAttribute['section'] == 'facilitySection' ? "100% !important;" : "100%;";

                $fieldDiv = '';

                $sectionClass = $testAttribute['section'] . 'Input';
                if ($testAttribute['section'] == 'labSection') {
                    $fieldDiv .= "<div class='col-md-6 $sectionClass'>";
                    $fieldDiv .= '<label class="col-lg-5 control-label labels" for="' . $testAttribute['field_id'] . '">' . $testAttribute['field_name'] . $mandatory . '</label>';
                    $fieldDiv .= '<div class="col-lg-7">';
                } elseif ($testAttribute['section'] == 'facilitySection') {
                    $fieldDiv .= "<div class='col-xs-4 col-md-4  $sectionClass'>";
                    $fieldDiv .= "<div class='form-group'>";
                    $fieldDiv .= '<label class="control-label labels" for="' . $testAttribute['field_id'] . '">' . $testAttribute['field_name'] . $mandatory . '</label><br>';
                } else {
                    $fieldDiv .= "<div class='col-xs-3 col-md-3  $sectionClass'>";
                    $fieldDiv .= "<div class='form-group'>";
                    $fieldDiv .= '<label class="control-label labels" for="' . $testAttribute['field_id'] . '">' . $testAttribute['field_name'] . $mandatory . '</label>';
                }
                $fieldDiv .= getField($testAttribute, $testAttributeId, $value, $inputClass, $isRequired, $fieldType, $disabled, $inputWidth);
                $fieldDiv .= '</div>';

                $result[$testAttribute['section']][] = $fieldDiv;
                $i++;
            }
        } else {
            //Othersection code
            foreach ($testAttributeDetails as $testAttributeKey => $otherAttributeDetails) {
                foreach ($otherAttributeDetails as $testAttributeId => $testAttribute) {
                    $isRequired = $testAttribute['mandatory_field'] === 'yes' ? 'isRequired' : '';
                    $mandatory = $testAttribute['mandatory_field'] === 'yes' ? '<span class="mandatory">*</span>' : '';
                    $value = $testTypeForm[$testAttributeId] ?? '';
                    $fieldType = getFieldType($testAttribute['field_type']);
                    if (
                        !empty($_POST['formType']) &&
                        $_POST['formType'] == 'update-form' &&
                        $testAttribute['section'] != 'labSection'
                    ) {
                        $isRequired = "";
                    }

                    $inputClass = " dynamicSelect2 ";
                    $inputWidth = "100%;";

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
                    $fieldDiv = '<div class="col-md-6">';
                    $fieldDiv .= '<label class="col-lg-5" for="'.$testAttributeId.'">' . $testAttribute['field_name'] . $mandatory . '</label>';
                    $fieldDiv .= getField($testAttribute, $testAttributeId, $value, $inputClass, $isRequired, $fieldType, $disabled, $inputWidth);
                    $fieldDiv .= '</div>';
                    $content[trim(strtolower($testAttribute['section_name']))] .= $fieldDiv;

                    $othersSectionFields[$s[trim(strtolower($testAttribute['section_name']))]] = $title . '<div class="box-body"><div class="row">' . $content[trim(strtolower($testAttribute['section_name']))] . '</div></div>';
                    $i++;
                }
            }

            foreach ($othersSectionFields as $otherFormFields) {
                $result['otherSection'][] = "<div class='box box-primary' style='margin-top: 0px; margin-left: -1px;'>" . $otherFormFields . "</div></div>";
            }
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
                $resultSection .= '<option value="' . trim($row) . '" ' . $selected . '>' . ($row) . '</option>';
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
                    $resultSection .= '<option value="' . trim($row) . '" ' . $selected . ' data-interpretation="' . $testResultsAttribute['quantitative_result_interpretation'][$key] . '"> ' . ($row) . ' </option>';
                }
                $resultSection .= '</datalist></td></tr>';
            }
        }
    }
    $resultSection .= '<tr class="testResultUnit"><th scope="row" colspan="5" class="text-right final-result-row">Test Result Unit</th>';
    $resultSection .= '<td>
    <select class="form-control testResultUnit resultUnit" id="finalTestResultUnit" name="finalTestResultUnit" placeholder="Please Enter test result unit" title="Please Enter test result unit"><option value="">--Select--</option>';

    foreach ($testResultUnits as $unit) {
        $selected = isset($_POST['resultUnit']) && $_POST['resultUnit'] == $unit['unit_id'] ? "selected='selected'" : "";
        $resultSection .= '<option value="' . $unit['unit_id'] . '" ' . $selected . '>' . $unit['unit_name'] . '</option>';

    }

    $resultSection .= '</select></td></tr>';
    $resultSection .= '<tr><th scope="row" colspan="5" class="text-right final-result-row">Result Interpretation</th>';
    $resultSection .= '<td><input type="text" placeholder="Interpretation result" title="Please enter the result interpretation" class="form-control" id="resultInterpretation" value="' . $resultInterpretation . '" name="resultInterpretation"></input>';
    $resultSection .= '<input type="hidden" id="resultType" name="resultType" class="form-control result-text" value="' . $testResultsAttribute['result_type'] . '"></td></tr>';
    $result['result'][] = $resultSection;
}

echo json_encode($result);
