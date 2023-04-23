<?php

use App\Services\Covid19Service;
use App\Services\CommonService;



$generalObj = new CommonService();
$covid19Obj = new Covid19Service();
$covid19Results = $covid19Obj->getCovid19Results();

$testKitInfo = $db->rawQueryOne("SELECT * from r_covid19_qc_testkits where testkit_id = " . base64_decode($_POST['kitId']));
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
