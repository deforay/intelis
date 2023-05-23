<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$testTypeForm = [];
if (isset($_POST['testTypeForm']) && !empty($_POST['testTypeForm'])) {
    $testTypeForm = json_decode(base64_decode($_POST['testTypeForm']), true);
}
$disabled = "";
if (isset($_POST['formType']) && !empty($_POST['formType']) && $_POST['formType'] == 'update-form') {
    $disabled = ' disabled ';
}
$testTypeQuery = "SELECT * FROM r_test_types WHERE test_type_id= ?";
$testTypeResult = $db->rawQuery($testTypeQuery, [$_POST['testType']]);
$testAttribute = json_decode($testTypeResult[0]['test_form_config'], true);
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
$n = count($testAttribute['field_name']);
if ($n > 0) {
    for ($i = 0; $i < $n; $i++) {
        $isRequired = "";
        $mandatory = "";
        $fieldType = "";
        if ($testAttribute['mandatory_field'][$i] == 'yes') {
            $isRequired = "isRequired";
            $mandatory = '<span class="mandatory">*</span>';
        }
        $value = "";
        if (isset($testTypeForm[$testAttribute['field_id'][$i]]) && !empty($testTypeForm[$testAttribute['field_id'][$i]])) {
            $value = $testTypeForm[$testAttribute['field_id'][$i]];
        }
        if ($testAttribute['field_type'][$i] == 'number') {
            $fieldType = " forceNumeric";
        } elseif ($testAttribute['field_type'][$i] == 'date') {
            $fieldType = " date";
        }

        if (
            isset($_POST['formType']) &&
            !empty($_POST['formType']) &&
            $_POST['formType'] == 'update-form' &&
            $testAttribute['section'][$i] != 'lab'
        ) {
            $isRequired = "";
        }

        if($testAttribute['section'][$i] == 'facilitySection') {
            $inputWidth = "285px !important;";
            $inputClass = " dynamicFacilitySelect2 ";
        }else{
            $inputClass = " dynamicSelect2 ";
            $inputWidth = "100%;";
        }
        // print_r($testAttribute);die;
        if ($testAttribute['field_type'][$i] == 'dropdown' || $testAttribute['field_type'][$i] == 'multiple') {
            $dropDownName = 'dynamicFields[' . $testAttribute['field_id'][$i] . ']';
            if ($testAttribute['field_type'][$i] == 'multiple') {
                $dropDownName = 'dynamicFields[' . $testAttribute['field_id'][$i] . '][]';
            }
            $dropDownField = '<select name="' . $dropDownName . '" id="' . $testAttribute['field_id'][$i] . '" class="form-control ' . $inputClass . $isRequired . $fieldType . $disabled . '" title="Please select the option" ' . $testAttribute['field_type'][$i] . ' style="width:' . $inputWidth . ';">';
            $dropDownField .= '<option value="">-- Select --</option>';
            foreach (explode(',', $testAttribute['drop_down'][$i]) as $option) {
                if ($testAttribute['field_type'][$i] == 'multiple') {
                    $selected = (isset($value) && !empty($value) && in_array(trim($option), $testTypeForm[$testAttribute['field_id'][$i]])) ? "selected" : "";
                } else {
                    $selected = (isset($value) && !empty($value) && $value == $option) ? "selected" : "";
                }
                $dropDownField .= '<option value="' . trim($option) . '" ' . $selected . '>' . ucwords($option) . '</option>';
            }
            $dropDownField .= '</select>';
        }

        $assign = '';

        if ($testAttribute['section'][$i] == 'labSection') {
            $assign .= '<div class="col-md-6 ' . $testAttribute['section'][$i] . 'Input">';
            $assign .= '<label class="col-lg-5 control-label labels" for="' . $testAttribute['field_id'][$i] . '">' . $testAttribute['field_name'][$i] . $mandatory . '</label>';
            $assign .= '<div class="col-lg-7">';
        } elseif($testAttribute['section'][$i] == 'facilitySection') {
            $assign .= '<div class="col-xs-4 col-md-4 ' . $testAttribute['section'][$i] . 'Input">';
            $assign .= '<div class="form-group">';
            $assign .= '<label class="control-label labels" for="' . $testAttribute['field_id'][$i] . '">' . $testAttribute['field_name'][$i] . $mandatory . '</label><br>';
        } else{
            $assign .= '<div class="col-xs-3 col-md-3 ' . $testAttribute['section'][$i] . 'Input">';
            $assign .= '<div class="form-group">';
            $assign .= '<label class="control-label labels" for="' . $testAttribute['field_id'][$i] . '">' . $testAttribute['field_name'][$i] . $mandatory . '</label>';
        }


        if($testAttribute['field_type'][$i] == 'dropdown' || $testAttribute['field_type'][$i] == 'multiple'){
            $assign .= $dropDownField;
        }else{
            $assign .= '<input type="text" class="form-control ' . $isRequired . $fieldType . $disabled . '" placeholder="' . $testAttribute['field_name'][$i] . '" id="' . $testAttribute['field_id'][$i] . '" name="dynamicFields[' . $testAttribute['field_id'][$i] . ']" value="' . $value . '" ' . $disabled . ' style="width:'.$inputWidth.';">';
        }
        $assign .= '<input type="hidden" class="form-control" name="testTypeId[]" value="' . $testAttribute['field_id'][$i] . '">';
        $assign .= '</div></div>';

        $result[$testAttribute['section'][$i]][] = $assign;

        if ($testAttribute['section'][$i] == 'otherSection') {
            if (in_array($testAttribute['section_other'][$i], $otherSection)) {
                if (!isset($s[trim(strtolower($testAttribute['section_other'][$i]))]))
                    $s[trim(strtolower($testAttribute['section_other'][$i]))] = $i;
            } else {
                $s[trim(strtolower($testAttribute['section_other'][$i]))] = $i;
                $otherSection[] = $testAttribute['section_other'][$i];
            }
            $title = '<div class="box-header with-border"><h3 class="box-title">' . $testAttribute['section_other'][$i] . '</h3></div>';

            // Grouping Other Sections via array
            $content[trim(strtolower($testAttribute['section_other'][$i]))] .= '<div class="col-xs-3 col-md-3">';
            $content[trim(strtolower($testAttribute['section_other'][$i]))] .= '<div class="form-group">';
            $content[trim(strtolower($testAttribute['section_other'][$i]))] .= '<label>' . $testAttribute['field_name'][$i] . $mandatory . '</label>';
            if ($testAttribute['field_type'][$i] == 'dropdown' || $testAttribute['field_type'][$i] == 'multiple') {
                $content[trim(strtolower($testAttribute['section_other'][$i]))] .= $dropDownField;
            } else {
                $content[trim(strtolower($testAttribute['section_other'][$i]))] .= '<input type="text" class="form-control ' . $isRequired . $fieldType . $disabled . '" placeholder="' . $testAttribute['field_name'][$i] . '" id="' . $testAttribute['field_id'][$i] . '" name="dynamicFields[' . $testAttribute['field_id'][$i] . ']" value="' . $value . '" ' . $disabled . '><input type="hidden" class="form-control" name="testTypeId[]" value="' . $testAttribute['field_id'][$i] . '">';
            }
            $content[trim(strtolower($testAttribute['section_other'][$i]))] .= '</div></div>';

            $others[$s[trim(strtolower($testAttribute['section_other'][$i]))]] = $title . '<div class="box-body"><div class="row">' . $content[trim(strtolower($testAttribute['section_other'][$i]))] . '</div></div>';
        }
    }
    $key = 0;
    foreach ($others as $form) {
        $otherForm[$key] = "<div class='box box-primary' style='margin-top: 0px; margin-left: -1px;'>" . $form . "</div></div>";
        $key++;
    }
    $result['otherSection'] = $otherForm;
}
// echo "<pre>";
// print_r($testResultsAttribute);die;
if (isset($testResultsAttribute) && !empty($testResultsAttribute)) {
    if ($testResultsAttribute['result_type'] = 'qualitative') {
        $resultSection = '<select class="form-control result-select" name="result" id="result">';
        $resultSection .= '<option value="">-- Select --</option>';
        if (isset($testResultsAttribute['result']) && !empty($testResultsAttribute['result'])) {
            foreach ($testResultsAttribute['result'] as $row) {
                $selected = (isset($_POST['result']) && $_POST['result'] != "" && $_POST['result'] == $row) ? "selected" : "";
                $resultSection .= '<option value="' . $row . '" ' . $selected . '>' . ucwords($row) . '</option>';
            }
        }
        $resultSection .= '</select>';
    } else {
        $resultSection = '<input type="text" id="result" name="result" class="form-control result-text" placeholder="Enter final result" title="Please enter final results">';
    }
    $resultForm[] = $resultSection;
}
$result['result'] = $resultForm;

echo json_encode($result);
