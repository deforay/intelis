<?php

use App\Services\Covid19Service;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;



/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$covid19Results = $covid19Service->getCovid19Results();

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$testKitInfo = $db->rawQueryOne("SELECT * from r_covid19_qc_testkits where testkit_id = ?", [base64_decode($_POST['kitId'])]);
$result = "";
if (isset($testKitInfo) && !empty($testKitInfo['labels_and_expected_results'])) {
    $json = json_decode($testKitInfo['labels_and_expected_results'], true);
    foreach ($json['label'] as $key => $row) {
        $result .= '<tr>';
        $result .= '<td>' . ($json['label'][$key]) . '<input type="hidden" value="' . $json['label'][$key] . '" id="testLabel" name="testLabel[]"/></td>';
        $result .= '<td><select class="form-control" id="testResults' . ($key + 1) . '" name="testResults[]" class="form-control" title="Please enter the test results">' . $generalObj->generateSelectOptions($covid19Results, $subResult['expected'][$key], "--Select--") . '</select>';
        $result .= '</td>';
        $result .= '</tr>';
    }
}

echo $result;
