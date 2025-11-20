<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$subTests = (isset($_POST['subTests']) && !empty($_POST['subTests'])) ? base64_decode((string) $_POST['subTests']) : null;
$subTestsArray = explode("##", (string) $subTests);

$testTypeQuery = "SELECT * FROM r_test_types WHERE test_type_id= ?";
$testTypeResult = $db->rawQueryOne($testTypeQuery, [$_POST['testTypeId']]);

$testResultsAttribute = json_decode((string) $testTypeResult['test_results_config'], true);
$options = "";
if (isset($testResultsAttribute['sub_test_name']) && count($testResultsAttribute['sub_test_name']) > 0 && $testResultsAttribute['sub_test_name'] != "") {
    foreach ($testResultsAttribute['sub_test_name'] as $key => $testName) {
        $selected = (in_array($testName, $subTestsArray) || ($key == 1)) ? "selected='selected'" : "";
        $options .= '<option value="' . $testName . '" ' . $selected . '>' . $testName . '</option>';
    }
}
echo $options;
