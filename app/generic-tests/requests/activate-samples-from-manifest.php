<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();


$queryParams = explode(',', $_POST['sampleId']);
$placeholders = implode(', ', array_fill(0, count($queryParams), '?'));

$sampleQuery = "SELECT sample_id,
                sample_collection_date,
                sample_package_code,
                province_id,
                sample_code
                FROM form_generic WHERE sample_id IN ($placeholders)";
$sampleResult = $db->rawQuery($sampleQuery, $queryParams);

$status = 0;
foreach ($sampleResult as $sampleRow) {
    $provinceCode = null;

    $testType = $genericTestsService->getDynamicFields($sampleRow['sample_id']);
    $testTypeShortCode = "T";
    if (!empty($testType['dynamicLabel']['test_short_code'])) {
        $testTypeShortCode = $testType['dynamicLabel']['test_short_code'];
    }

    if (!empty($sampleRow['province_id'])) {
        $provinceQuery = "SELECT * FROM geographical_divisions WHERE geo_id = ?";
        $provinceResult = $db->rawQueryOne($provinceQuery, [$sampleRow['province_id']]);
        $provinceCode = $provinceResult['geo_code'];
    }
    if (!empty($_POST['testDate'])) {
        $testDate = explode(" ", $_POST['testDate']);
        $_POST['testDate'] = DateUtility::isoDateFormat($testDate[0]);
        $_POST['testDate'] .= " " . $testDate[1];
    } else {
        $_POST['testDate'] = null;
    }
    // ONLY IF SAMPLE ID IS NOT ALREADY GENERATED
    if ($sampleRow['sample_code'] == null || $sampleRow['sample_code'] == '' || $sampleRow['sample_code'] == 'null') {

        $sampleCodeParams = [];
        $sampleCodeParams['sampleCollectionDate'] = DateUtility::humanReadableDateFormat($sampleRow['sample_collection_date'] ?? '');
        $sampleCodeParams['provinceCode'] = $provinceCode;
        $sampleCodeParams['testType'] = $testTypeShortCode;

        $sampleJson = $genericTestsService->getSampleCode($sampleCodeParams);
        $sampleData = json_decode($sampleJson, true);
        //$vldata['sample_code'] = $sampleData['sampleCode'];
        $vldata['sample_code'] = $sampleData['sampleCode'];
        $vldata['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $vldata['sample_code_key'] = $sampleData['sampleCodeKey'];
        $vldata['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
        $vldata['data_sync'] = 0;

        $vldata['last_modified_by'] = $_SESSION['userId'];
        $vldata['last_modified_datetime'] = DateUtility::getCurrentDateTime();

        if (!empty($_POST['testDate'])) {
            $vldata['sample_tested_datetime'] = null;
            $vldata['sample_received_at_testing_lab_datetime'] = $_POST['testDate'];
        }
        $db = $db->where('sample_id', $sampleRow['sample_id']);
        $id = $db->update('form_generic', $vldata);
        if ($id === true) {
            $status = $id;
        }
    }
}
echo $status;
