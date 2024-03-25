<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GenericTestsService;
use App\Utilities\MiscUtility;

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$subTestS = (isset($_POST['subTests']) && !empty($_POST['subTests']))?base64_decode((string) $_POST['subTests']):null;
$subTestSArray = explode("##", $subTestS);
// echo "<pre>";print_r($subTestSArray);die;
$testTypeQuery = "SELECT * FROM r_test_types WHERE test_type_id= ?";
$testTypeResult = $db->rawQueryOne($testTypeQuery, [$_POST['testTypeId']]);
$testResultsAttribute = json_decode((string) $testTypeResult['test_results_config'], true);
$options = ""; $n = count($testResultsAttribute['sub_test_name']);
if(isset($testResultsAttribute['sub_test_name']) && count($testResultsAttribute['sub_test_name']) > 0){
    foreach($testResultsAttribute['sub_test_name'] as $key => $testName){
        $selected = (in_array($testName, $subTestSArray) || ($n == 1))? "selected='selected'":"";
        $options .= '<option value="'.$testName.'" '.$selected.'>'.$testName.'</option>';
    }
}
echo $options;
