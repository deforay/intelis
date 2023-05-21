<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GenericTestsService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

$sampleQuery = "SELECT sample_id, sample_collection_date, sample_package_code, province_id, sample_code FROM form_generic where sample_id IN (?)";
$sampleResult = $db->rawQuery($sampleQuery, [$_POST['sampleId']]);
$status = 0;
foreach ($sampleResult as $sampleRow) {
    $provinceCode = null;

    $testType = $genericTestsService->getDynamicFields($sampleRow['sample_id']);
    $testTypeShortCode = "LAB";
    if (isset($testType['dynamicLabel']['test_short_code']) && !empty($testType['dynamicLabel']['test_short_code'])) {
        $testTypeShortCode = $testType['dynamicLabel']['test_short_code'];
    }

    if (isset($sampleRow['province_id']) && !empty($sampleRow['province_id'])) {
        $provinceQuery = "SELECT * FROM geographical_divisions WHERE geo_id = " . $sampleRow['province_id'];
        $provinceResult = $db->rawQueryOne($provinceQuery);
        $provinceCode = $provinceResult['geo_code'];
    }
    if (isset($_POST['testDate']) && !empty($_POST['testDate'])) {
        $testDate = explode(" ", $_POST['testDate']);
        $_POST['testDate'] = DateUtility::isoDateFormat($testDate[0]);
        $_POST['testDate'] .= " " . $testDate[1];
    } else {
        $_POST['testDate'] = null;
    }
    // ONLY IF SAMPLE CODE IS NOT ALREADY GENERATED
    if ($sampleRow['sample_code'] == null || $sampleRow['sample_code'] == '' || $sampleRow['sample_code'] == 'null') {

        $sampleJson = $genericTestsService->generateGenericSampleID($provinceCode, DateUtility::humanReadableDateFormat($sampleRow['sample_collection_date']), null, '', null, null, $testTypeShortCode);
        $sampleData = json_decode($sampleJson, true);
        //$vldata['sample_code'] = $sampleData['sampleCode'];
        $vldata['sample_code'] = $sampleData['sampleCode'];
        $vldata['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $vldata['sample_code_key'] = $sampleData['sampleCodeKey'];
        $vldata['result_status'] = 6;
        $vldata['data_sync'] = 0;

        $vldata['last_modified_by'] = $_SESSION['userId'];
        $vldata['last_modified_datetime'] = DateUtility::getCurrentDateTime();

        if (!empty($_POST['testDate'])) {
            $vldata['sample_tested_datetime'] = null;
            $vldata['sample_received_at_testing_lab_datetime'] = $_POST['testDate'];
        }
        $db = $db->where('sample_id', $sampleRow['sample_id']);
        $id = $db->update('form_generic', $vldata);
        if ($id > 0) {
            $status = $id;
        }
    }
}
echo $status;
