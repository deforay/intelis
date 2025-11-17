<?php

use Laminas\Diactoros\ServerRequest;
use App\Utilities\DateUtility;
use App\Utilities\MemoUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$genericTestQuery = "SELECT * from generic_test_results where generic_id=? ORDER BY test_id ASC";
$genericTestInfo = $db->rawQuery($genericTestQuery, [$_POST['vlSampleId']]);
// echo "<pre>";print_r($genericTestInfo);die;
foreach ($genericTestInfo as $ikey => $row) {
    $subTestLabels[] = $row['sub_test_name'];
}
/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('generic-tests');
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name']] = $row['machine_name'];
}
$testTypeForm = [];
if (!empty($_POST['testTypeForm'])) {
    $testTypeForm = json_decode(base64_decode((string) $_POST['testTypeForm']), true);
}
$disabled = "";
if (!empty($_POST['formType']) && $_POST['formType'] == 'update-form') {
    $disabled = ' disabled ';
}
// $resultInterpretation = $_POST['resultInterpretation'] ?? "";
$testResultUnits = $genericTestsService->getTestResultUnit($_POST['testType']);

$testTypeQuery = "SELECT * FROM r_test_types WHERE test_type_id= ?";
$testTypeResult = $db->rawQueryOne($testTypeQuery, [$_POST['testType']]);
$testTypeAttributes = json_decode((string) $testTypeResult['test_form_config'], true);
// echo "<pre>";print_r($testTypeAttributes);die;
$sections = ['facilitySection', 'patientSection', 'specimenSection', 'labSection', 'otherSection', 'result'];
$result = array_fill_keys($sections, []);

$testResultsAttribute = json_decode((string) $testTypeResult['test_results_config'], true);

$testMethodQuery = " SELECT rgtm.test_method_id, rgtm.test_method_name FROM r_generic_test_methods AS rgtm INNER JOIN generic_test_methods_map as gtmm ON rgtm.test_method_id=gtmm.test_method_id WHERE test_type_id= ? GROUP BY rgtm.test_method_id ";
$testMethodResult = $db->rawQuery($testMethodQuery, [$_POST['testType']]);
if (isset($testMethodResult) && !empty($testMethodResult)) {
    foreach ($testMethodResult as $row) {
        $testMethods[$row['test_method_id']] = $row['test_method_name'];
    }
}
$othersSectionFields = [];
$otherSection = [];
$s = [];
function getClassNameFromFieldType($fieldType): mixed
{
    return MemoUtility::remember(function () use ($fieldType): string {
        if ($fieldType == 'number') {
            return " forceNumeric ";
        } elseif ($fieldType == 'date') {
            return " dateTime ";
        }
        return "";
    });
}

function getDropDownField(array $testAttribute, string $testAttributeId, $value, $inputClass, $isRequired, $fieldType, $disabled, $inputWidth): string
{
    $fieldName = 'dynamicFields[' . $testAttributeId . ']';
    $isMultiple = $testAttribute['field_type'] == 'multiple';
    $title = _translate("Please select an option");
    $commonClass = "form-control";
    $selectizeCls = "";
    if ($isMultiple) {
        $fieldName .= '[]';
        $commonClass = "";
        $selectizeCls = "multipleSelectize";
        $inputClass = "";
        $title = _translate("Please select one or more options");
    }


    $field = sprintf(
        '<div class="col-lg-7">
            <select name="%s" id="%s" class="' . $commonClass . $selectizeCls . ' %s%s%s%s"
                title="%s" %s style="width:%s;">',
        $fieldName,
        $testAttributeId,
        $inputClass,
        $isRequired,
        $fieldType,
        $disabled,
        $title,
        $testAttribute['field_type'],
        $inputWidth
    );
    $field .= '<option value="">' . _translate("-- Select --") . '</option>';
    foreach (explode(',', (string) $testAttribute['dropdown_options']) as $option) {
        if ($isMultiple && is_array($value)) {

            $selected = ($value !== [] && in_array($option, $value)) ? "selected" : "";
        } else {
            $selected = (!empty($value) && $value == $option) ? "selected" : "";
        }
        $field .= '<option value="' . trim($option) . '" ' . $selected . '>' . ($option) . '</option>';
    }
    $field .= '</select></div>';
    return $field;
}

function getField(array $testAttribute, string $testAttributeId, $value, $inputClass, $sectionClass, $isRequired, $fieldType, $disabled, $inputWidth, string $mandatory): string
{
    $fieldDiv = "<div class='col-md-6 $sectionClass'>";
    $fieldDiv .= '<label class="col-lg-5 control-label labels" for="' . $testAttributeId . '">' . $testAttribute['field_name'] . $mandatory . '</label>';

    $field = '';
    if ($testAttribute['field_type'] == 'dropdown' || $testAttribute['field_type'] == 'multiple') {
        $field .= getDropDownField($testAttribute, $testAttributeId, $value, $inputClass, $isRequired, $fieldType, $disabled, $inputWidth);
    } else {
        $field = sprintf(
            '<div class="col-lg-7">
                <input type="text" class="form-control %s%s%s"
                    placeholder="%s" id="%s" name="dynamicFields[%s]"
                    value="%s" %s style="width:%s;"></div>',
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

    $fieldDiv .= $field;
    $fieldDiv .= '</div>';
    return $fieldDiv;
}
//echo '<pre>'; print_r($testTypeAttributes); die;

if (!empty($testTypeAttributes)) {
    $i = 1;
    $arraySection = ['facilitySection', 'patientSection', 'specimenSection', 'labSection'];
    foreach ($testTypeAttributes as $currentSectionName => $testAttributeDetails) {
        $recentData = null;
        if (in_array($currentSectionName, $arraySection)) {
            foreach ($testAttributeDetails as $testAttributeId => $testAttribute) {
                // To set prefill with fcode
                $recentData = $genericTestsService->fetchRelaventDataUsingTestAttributeId($testAttribute['field_code']);
                if (empty($recentData)) {
                    $recentData = null;
                }
                $isRequired = $testAttribute['mandatory_field'] === 'yes' ? 'isRequired' : '';
                $mandatory = $testAttribute['mandatory_field'] === 'yes' ? '<span class="mandatory">*</span>' : '';
                $value = $testTypeForm[$testAttributeId] ?? $recentData ?? null;

                $fieldType = getClassNameFromFieldType($testAttribute['field_type']);
                if (
                    !empty($_POST['formType']) &&
                    $_POST['formType'] == 'update-form' &&
                    $testAttribute['section'] != 'labSection'
                ) {
                    $isRequired = "";
                }

                $inputClass = $testAttribute['section'] == 'facilitySection' ? " dynamicFacilitySelect2 " : " dynamicSelect2 ";

                $inputWidth = $testAttribute['section'] == 'facilitySection' ? "100% !important;" : "100%;";

                $sectionClass = $testAttribute['section'] . 'Input';

                $result[$testAttribute['section']][] = getField($testAttribute, $testAttributeId, $value, $inputClass, $sectionClass, $isRequired, $fieldType, $disabled, $inputWidth, $mandatory);
                $i++;
            }
        } else {
            //Othersection code
            foreach ($testAttributeDetails as $otherSectionName => $otherSectionFields) {
                $counter = 0;
                $divContent = '';
                foreach ($otherSectionFields as $testAttributeId => $testAttribute) {
                    // To set prefill with fcode
                    $recentData = $genericTestsService->fetchRelaventDataUsingTestAttributeId($testAttribute['field_code']);
                    if (empty($recentData)) {
                        $recentData = null;
                    }
                    $counter++;
                    $isRequired = $testAttribute['mandatory_field'] === 'yes' ? 'isRequired' : '';
                    $mandatory = $testAttribute['mandatory_field'] === 'yes' ? '<span class="mandatory">*</span>' : '';
                    $value = $testTypeForm[$testAttributeId] ?? $recentData ?? null;
                    $fieldType = getClassNameFromFieldType($testAttribute['field_type']);
                    if (
                        !empty($_POST['formType']) &&
                        $_POST['formType'] == 'update-form' &&
                        $testAttribute['section'] != 'labSection'
                    ) {
                        $isRequired = "";
                    }

                    $inputClass = " dynamicSelect2 ";
                    $inputWidth = "100%;";

                    $sectionName = trim(strtolower((string) $testAttribute['section_name']));

                    if (!isset($s[$sectionName])) {
                        $s[$sectionName] = $i;
                    }

                    if (!in_array($testAttribute['section_name'], $otherSection)) {
                        $otherSection[] = $testAttribute['section_name'];
                    }

                    $title = '<div class="box-header with-border"><h3 class="box-title">' . $otherSectionName . '</h3></div>';

                    // Grouping Other Sections via array
                    $sectionClass = $otherSectionName . 'Input';
                    $fieldDiv = getField($testAttribute, $testAttributeId, $value, $inputClass, $sectionClass, $isRequired, $fieldType, $disabled, $inputWidth, $mandatory);
                    if ($counter % 2 == 0) {
                        $fieldDiv .= '</div><div class="row">';
                    }

                    $divContent .= $fieldDiv;
                    $i++;
                }
                $startSection = '<div class="box-body"><div class="row">';
                $endSection = '</div></div>';

                $othersSectionFields[$otherSectionName] =
                    $title .
                    $startSection .
                    $divContent .
                    $endSection;
            }

            foreach ($othersSectionFields as $otherFormFields) {
                $result['otherSection'][] = "<div class='box box-primary' style='margin-top: 0px; margin-left: -1px;'>" . $otherFormFields . "</div></div>";
            }
        }
    }
}

/* echo "<pre>";
print_r($testResultsAttribute);die; */
if (!empty($testResultsAttribute)) {
    foreach ($testResultsAttribute['result_type'] as $key => $resultType) {
        if (isset($_POST['subTests']) && !empty($_POST['subTests']) && in_array($testResultsAttribute['sub_test_name'][$key], $_POST['subTests'])) {
            $testResultsAttribute['sub_test_name'][$key] = $testResultsAttribute['sub_test_name'][$key] ?: 'default';
            $subTest = strtolower((string) $testResultsAttribute['sub_test_name'][$key]);
            $n = 1;
            $resultSection = "";
            $subTestResultSection = "";
            $finalTestResults = [];

            $resultSection .= '<div class="row"><div class="col-md-12"><h3>' . $testResultsAttribute['sub_test_name'][$key] . '</h3>
            <table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true" id="resultSubTestSection">
                <thead>
                    <tr>
                        <th scope="row" class="text-center">Test No.</th>
                        <th scope="row" class="text-center">Test Method</th>
                        <th scope="row" class="text-center">Date of Testing</th>
                        <th scope="row" class="text-center">Test Platform/Instrument</th>
                        <th scope="row" class="text-center">Test Result</th>';
            if ($resultType != 'qualitative') {
                $resultSection .= '<th scope="row" class="text-center qualitative-field testResultUnit">Test Result Unit</th>';
            }
            $resultSection .= '<th scope="row" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="testKitNameTable' . $key . '">';
            if ((isset($genericTestInfo) && !empty($genericTestInfo))) {
                // if ((isset($genericTestInfo) && !empty($genericTestInfo)) || (in_array(strtolower((string) $testResultsAttribute['sub_test_name'][$key]), $subTestLabels))) {
                $i = 1;
                foreach ($genericTestInfo as $ikey => $row) {
                    if (($row['sub_test_name'] == strtolower((string) $testResultsAttribute['sub_test_name'][$key])) || (empty($row['sub_test_name']) || empty($testResultsAttribute['sub_test_name'][$key]))) {
                        $finalTestResults[$row['sub_test_name']]['final_result'] = $row['final_result'];
                        $finalTestResults[$row['sub_test_name']]['final_result_unit'] = $row['final_result_unit'];
                        $resultSection .= '<tr>
                                <td class="text-center">' . $i . '</td>
                                <td>
                                        <select class="form-control test-name-table-input" id="testName' . $key . $i . '" name="testName[' . $subTest . '][]" title="Please enter the name of the Testkit (or) Test Method used">';
                        $resultSection .= $general->generateSelectOptions($testMethods, $row['test_name'], '-- Select --');
                        $resultSection .= '</select>
                                        <input type="text" name="testNameOther[' . $subTest . '][]" id="testNameOther' . $key . $i . '" class="form-control testNameOther1" title="Please enter the name of the Testkit (or) Test Method used" placeholder="Please enter the name of the Testkit (or) Test Method used" style="display: none;margin-top: 10px;" />
                                </td>
                                <td><input type="text" value="' . DateUtility::humanReadableDateFormat($row['sample_tested_datetime'], true) . '" name="testDate[' . $subTest . '][]" id="testDate' . $key . $i . '" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row ' . $i . '" /></td>
                                <td>
                                        <select name="testingPlatform[' . $subTest . '][]" id="testingPlatform' . $key . $i . '" class="form-control  result-optional test-name-table-input" title="Please select the Testing Platform for ' . $i . '">';
                        $resultSection .= $general->generateSelectOptions($testPlatformList, $row['testing_platform'], '-- Select --');
                        $resultSection .= '</select>
                                </td>
                                <td>';
                        if ($resultType == 'qualitative') {
                            $resultSection .= '<select class="form-control result-select" name="testResult[' . $subTest . '][]" id="testResult' . $key . $i . '" title="Enter result" title="Please enter final results"><option value="">-- Select --</option>';
                            if (!empty($testResultsAttribute[$resultType])) {
                                foreach ($testResultsAttribute[$resultType]['expectedResult'][$key] as $r) {
                                    $selected = isset($row['result']) && $row['result'] == trim((string) $r) ? "selected='selected'" : "";
                                    $resultSection .= '<option value="' . trim((string) $r) . '" ' . $selected . '>' . $r . '</option>';
                                }
                            }
                            $resultSection .= '</select>';
                        } else {
                            $resultSection .= '<input type="text" value="' . $row['result'] . '" id="testResult' . $key . $i . '" name="testResult[' . $subTest . '][]" class="form-control" placeholder="Enter result" title="Please enter final results">';
                        }

                        $resultSection .= '</td>';
                        if ($resultType != 'qualitative') {
                            $resultSection .= '<td class="testResultUnit">
                                <select class="form-control" id="testResultUnit' . $key . $i . '" name="testResultUnit[' . $subTest . '][]" placeholder=' . _translate("Enter test result unit") . ' title=' . _translate("Please enter test result unit") . '>
                                    <option value="">--Select--</option>';
                            foreach ($testResultUnits as $unit) {
                                $selected = isset($row['result_unit']) && $row['result_unit'] == $unit['unit_id'] ? "selected='selected'" : "";
                                $resultSection .= '<option value="' . $unit['unit_id'] . '" ' . $selected . '>' . $unit['unit_name'] . '</option>';
                            }
                            $resultSection .= '</select>
                            </td>';
                        }
                        $resultSection .= '<td style="vertical-align:middle;text-align: center;width:100px;">
                                    <a class="btn btn-xs btn-primary ins-row-' . $key . $i . ' test-name-table" href="javascript:void(0);" onclick="addTestRow(' . $key . ', \'' . $subTest . '\');"><em class="fa-solid fa-plus"></em></a>&nbsp;
                                    <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode, ' . $key . ',' . $i . ');"><em class="fa-solid fa-minus"></em></a>
                                </td>
                            </tr>';
                        $i++;
                    }
                }
            } else {
                $resultSection .= '<tr>
                        <td class="text-center">' . $n . '</td>
                        <td>
                                <select class="form-control test-name-table-input" id="testName' . $key . $n . '" name="testName[' . $subTest . '][]" title="Please enter the name of the Testkit (or) Test Method used">';
                $resultSection .= $general->generateSelectOptions($testMethods, $row['test_name'], '-- Select --');
                $resultSection .= '</select>
                                <input type="text" name="testNameOther[' . $subTest . '][]" id="testNameOther' . $key . $n . '" class="form-control testNameOther1" title="Please enter the name of the Testkit (or) Test Method used" placeholder="Please enter the name of the Testkit (or) Test Method used" style="display: none;margin-top: 10px;" />
                        </td>
                        <td><input type="text" name="testDate[' . $subTest . '][]" id="testDate' . $key . $n . '" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row ' . $n . '" /></td>
                        <td>
                                <select name="testingPlatform[' . $subTest . '][]" id="testingPlatform' . $key . $n . '" class="form-control  result-optional test-name-table-input" title="Please select the Testing Platform for ' . $n . '">';
                $resultSection .= $general->generateSelectOptions($testPlatformList, null, '-- Select --');
                $resultSection .= '</select>
                        </td>
                        <td>';
                if ($resultType == 'qualitative') {
                    $resultSection .= '<select class="form-control result-select" name="testResult[' . $subTest . '][]" id="testResult' . $key . $i . '" title="Enter result" title="Please enter final results"><option value="">-- Select --</option>';
                    if (!empty($testResultsAttribute[$resultType])) {
                        foreach ($testResultsAttribute[$resultType]['expectedResult'][$key] as $r) {
                            $resultSection .= '<option value="' . trim((string) $r) . '">' . $r . '</option>';
                        }
                    }
                    $resultSection .= '</select>';
                } else {
                    $resultSection .= '<input type="text" value="' . $row['result'] . '" id="testResult' . $key . $i . '" name="testResult[' . $subTest . '][]" class="form-control" placeholder="Enter result" title="Please enter final results">';
                }

                $resultSection .= '</td>';
                if ($resultType != 'qualitative') {
                    $resultSection .= '<td class="testResultUnit">
                                        <select class="form-control" id="testResultUnit' . $key . $n . '" name="testResultUnit[' . $subTest . '][]" placeholder=' . _translate("Enter test result unit") . ' title=' . _translate("Please enter test result unit") . '>
                                            <option value="">--Select--</option>';
                    foreach ($testResultUnits as $unit) {
                        $resultSection .= '<option value="' . $unit['unit_id'] . '" ' . $selected . '>' . $unit['unit_name'] . '</option>';
                    }
                    $resultSection .= '</select>
                                </td>';
                }
                $resultSection .= '<td style="vertical-align:middle;text-align: center;width:100px;">
                        <a class="btn btn-xs btn-primary ins-row-' . $key . $n . ' test-name-table" href="javascript:void(0);" onclick="addTestRow(' . $key . ', \'' . $subTest . '\');"><em class="fa-solid fa-plus"></em></a>&nbsp;
                        <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode, ' . $key . ',' . $n . ');"><em class="fa-solid fa-minus"></em></a>
                    </td>
                </tr>';
            }
            $finalInterpretationResult = $finalResult = "";
            foreach ($finalTestResults as $d) {
                if (isset($d['final_result']) && isset($d['final_result_interpretation']) && !empty($d['final_result']) && !empty($d['final_result_interpretation'])) {
                    $finalResult = $d['final_result'];
                    $finalInterpretationResult = $d['final_result_interpretation'];
                }
            }
            // print_r($testResultsAttribute[$resultType]['expectedResult'][$key]);
            // die;
            if ($resultType == 'qualitative') {
                $cs = 4;
                $subTestResultSection .= '<tr><th scope="row" colspan="4" class="text-right final-result-row">Final Result</th>';
                // $subTestResultSection .= '<td><select class="form-control result-select dynamicSelect2 editableSelect" name="finalResult[' . $subTest . ']" id="finalResult' . $key . '" onchange="updateInterpretationResult(this, \'' . strtolower($subTest) . '\');" >';
                $subTestResultSection .= '<td class="resultInputContainer"><input list="resultListQl' . $key . '" autocomplete="off" id="finalResult' . $key . '" name="finalResult[' . $subTest . ']" value="' . $finalTestResults[strtolower($subTest)]['final_result'] . '" class="form-control" placeholder="Enter final result" title="Please enter final results" onchange="updateInterpretationResult(this, \'' . strtolower($subTest) . '\');">';
                $subTestResultSection .= '<datalist id="resultListQl' . $key . '">';
                if (!empty($testResultsAttribute[$resultType])) {
                    foreach ($testResultsAttribute[$resultType]['expectedResult'][$key] as $r) {
                        $selected = (isset($finalTestResults[strtolower($subTest)]['final_result']) && $finalTestResults[strtolower($subTest)]['final_result'] == trim((string) $r)) ? "selected='selected'" : "";
                        $selectedResult = (isset($_POST['result']) && ($_POST['result'] == trim((string) $r))) ? "selected='selected'" : "";
                        $selected = isset($subTest) && ($subTest !== '' && $subTest !== '0') ? $selected ?? $selectedResult : $selectedResult ?? $selected;
                        // $subTestResultSection .= '<option value="' . trim((string) $r) . '" ' . $selected . '>' . ($r) . '</option>';
                        $subTestResultSection .= '<option value="' . trim((string) $r) . '"> ' . $r . ' </option>';
                    }
                }
                $subTestResultSection .= '</datalist></td></tr>';
            } else {
                $cs = 5;
                $subTestResultSection .= '<tr><th scope="row" colspan="4" class="text-right final-result-row">Final Result</th>';
                $subTestResultSection .= '<td class="resultInputContainer"><input type="text" autocomplete="off" list="resultList' . $key . '" id="finalResult' . $key . '" name="finalResult[' . $subTest . ']" value="' . $finalTestResults[strtolower($subTest)]['final_result'] . '" class="form-control result-text" value="' . $finalTestResults[strtolower($subTest)]['final_result'] . '" placeholder="Enter final result" title="Please enter final results" onchange="updateInterpretationResult(this, \'' . strtolower($subTest) . '\');">';
                if (!empty($testResultsAttribute['quantitative_result'])) {
                    $subTestResultSection .= '<datalist id="resultList' . $key . '">';
                    if (!empty($testResultsAttribute['quantitative_result'])) {
                        foreach ($testResultsAttribute['quantitative_result'] as $qkey => $qrow) {
                            $selected = (isset($finalTestResults[strtolower($subTest)]['final_result']) && $finalTestResults[strtolower($subTest)]['final_result'] == trim((string) $qrow)) ? "selected='selected'" : "";
                            $selectedResult = (isset($_POST['result']) && strtolower((string) $_POST['result']) === trim((string) $qrow)) ? "selected='selected'" : "";
                            $selected = isset($subTest) && ($subTest !== '' && $subTest !== '0') ? $selected ?? $selectedResult : $selectedResult ?? $selected;
                            $subTestResultSection .= '<option value="' . trim((string) $qrow) . '" ' . $selected . ' data-interpretation="' . $testResultsAttribute['quantitative_result_interpretation'][$qkey] . '"> ' . ($qrow) . ' </option>';
                        }
                        $subTestResultSection .= '</datalist></td></tr>';
                    }
                }
                $subTestResultSection .= '<tr class="testResultUnit"><th scope="row" colspan="5" class="text-right final-result-row">Test Result Unit</th>';
                $subTestResultSection .= '<td>
                <select class="form-control testResultUnit resultUnit" id="finalTestResultUnit' . $key . '" name="finalTestResultUnit[' . $subTest . ']" placeholder="Please Enter test result unit" title="Please Enter test result unit"><option value="">--Select--</option>';
                foreach ($testResultUnits as $unit) {
                    $selected = isset($finalTestResults[strtolower($subTest)]['final_result_unit']) && $finalTestResults[strtolower($subTest)]['final_result_unit'] == $unit['unit_id'] ? "selected='selected'" : "";
                    $subTestResultSection .= '<option value="' . $unit['unit_id'] . '" ' . $selected . '>' . $unit['unit_name'] . '</option>';
                }
            }
            $resultSection .= '</tbody>
                    <tfoot id="resultSection">';
            $resultSection .= $subTestResultSection;

            $resultSection .= '</select></td></tr>';
            if ($finalTestResults[strtolower($subTest)]['final_result_interpretation'] != "") {
                $resultSection .= '<tr><th scope="row" colspan="5" class="text-right final-result-row">Result Interpretation</th>';
                $resultSection .= '<td><input type="text" placeholder="Interpretation result" title="Please enter the result interpretation" class="form-control" id="resultInterpretation' . $key . '" value="' . $finalTestResults[strtolower($subTest)]['final_result_interpretation'] . '" name="resultInterpretation[' . $subTest . ']"></input>';
            }
            $resultSection .= '<input type="hidden" id="resultType" name="resultType[' . $subTest . ']" class="form-control result-text" value="' . $resultType . '">';
            $resultSection .= '</td></tr>';
            $resultSection .= '</tfoot>
                    </table>
            </div></div>';
            $result['result'][] = $resultSection;
            // print_r($result);die;
            $n++;
        }
    }
}
echo json_encode($result);
