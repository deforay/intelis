<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\TbService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$queryParams = explode(',', $_POST['sampleId']);
$placeholders = implode(', ', array_fill(0, count($queryParams), '?'));

$sampleQuery = "SELECT tb_id,
                sample_collection_date,
                sample_package_code,
                province_id,
                sample_code
                FROM form_tb WHERE tb_id IN ($placeholders)";
$sampleResult = $db->rawQuery($sampleQuery, $queryParams);


$status = 0;
foreach ($sampleResult as $sampleRow) {

    $provinceCode = null;
    if (!empty($sampleRow['province_id'])) {
        $provinceQuery = "SELECT * FROM geographical_divisions WHERE geo_id= " . $sampleRow['province_id'];
        $provinceResult = $db->rawQueryOne($provinceQuery);
        $provinceCode = $provinceResult['geo_code'];
    }

    if (!empty($_POST['testDate'])) {
        $testDate = explode(" ", $_POST['testDate']);
        $_POST['testDate'] = DateUtility::isoDateFormat($testDate[0]);
        $_POST['testDate'] .= " " . $testDate[1];
    } else {
        $_POST['testDate'] = null;
    }
    // ONLY IF SAMPLE CODE IS NOT ALREADY GENERATED
    if ($sampleRow['sample_code'] == null || $sampleRow['sample_code'] == '' || $sampleRow['sample_code'] == 'null') {

        $sampleCodeParams = [];
        $sampleCodeParams['sampleCollectionDate'] = DateUtility::humanReadableDateFormat($sampleRow['sample_collection_date'] ?? '');
        $sampleCodeParams['provinceCode'] = $provinceCode;

        $sampleJson = $tbService->getSampleCode($sampleCodeParams);
        $sampleData = json_decode($sampleJson, true);
        $tbData = [];
        $tbData['sample_code'] = $sampleData['sampleCode'];
        $tbData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $tbData['sample_code_key'] = $sampleData['sampleCodeKey'];
        if (!empty($_POST['testDate'])) {
            $tbData['sample_tested_datetime'] = null;
            $tbData['sample_received_at_lab_datetime'] = $_POST['testDate'];
        }
        $tbData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
        $tbData['data_sync'] = 0;
        $tbData['last_modified_by'] = $_SESSION['userId'];
        $tbData['last_modified_datetime'] = DateUtility::getCurrentDateTime();

        $db = $db->where('tb_id', $sampleRow['tb_id']);
        $id = $db->update('form_tb', $tbData);
        if ($id === true) {
            $status = $id;
        }
    }
}
echo $status;
