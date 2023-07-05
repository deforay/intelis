<?php


use App\Services\EidService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var EidService $eidObj */
$eidObj = ContainerRegistry::get(EidService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$queryParams = explode(',', $_POST['sampleId']);
$placeholders = implode(', ', array_fill(0, count($queryParams), '?'));

$sampleQuery = "SELECT eid_id,
                sample_collection_date,
                sample_package_code,
                province_id,
                sample_code
                FROM form_eid WHERE eid_id IN ($placeholders)";
$sampleResult = $db->rawQuery($sampleQuery, $queryParams);

$status = 0;
foreach ($sampleResult as $sampleRow) {

    $provinceCode = null;
    if (!empty($sampleRow['province_id'])) {
        $provinceQuery = "SELECT * FROM geographical_divisions WHERE geo_id= ?";
        $provinceResult = $db->rawQueryOne($provinceQuery, [$sampleRow['province_id']]);
        $provinceCode = $provinceResult['geo_code'];
    }
    // ONLY IF SAMPLE CODE IS NOT ALREADY GENERATED
    if ($sampleRow['sample_code'] == null || $sampleRow['sample_code'] == '' || $sampleRow['sample_code'] == 'null') {

        $sampleCodeParams = [];
        $sampleCodeParams['sampleCollectionDate'] = DateUtility::humanReadableDateFormat($sampleRow['sample_collection_date'] ?? '');
        $sampleCodeParams['provinceCode'] = $provinceCode ?? null;

        $sampleJson = $eidObj->getSampleCode($sampleCodeParams);
        $sampleData = json_decode($sampleJson, true);

        $eidData['sample_code'] = $sampleData['sampleCode'];
        $eidData['sample_code_format'] = $sampleData['sampleCodeFormat'];
        $eidData['sample_code_key'] = $sampleData['sampleCodeKey'];
        $eidData['result_status'] = SAMPLE_STATUS_RECEIVED_AT_TESTING_LAB;
        $eidData['data_sync'] = 0;
        if (!empty($_POST['testDate'])) {
            $eidData['sample_tested_datetime'] = null;
            $eidData['sample_received_at_vl_lab_datetime'] = DateUtility::isoDateFormat($_POST['testDate'], true);
        }
        $eidData['last_modified_by'] = $_SESSION['userId'];
        $eidData['last_modified_datetime'] = DateUtility::getCurrentDateTime();

        $db = $db->where('eid_id', $sampleRow['eid_id']);
        $id = $db->update('form_eid', $eidData);
        if ($id === true) {
            $status = $id;
        }
    }
}
echo $status;
