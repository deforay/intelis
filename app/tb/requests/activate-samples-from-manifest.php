<?php

use Throwable;
use App\Services\TbService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$queryParams = explode(',', (string) $_POST['sampleId']);
$placeholders = implode(', ', array_fill(0, count($queryParams), '?'));
try {

    $db->beginTransaction();
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

        $_POST['sampleReceivedOn'] = DateUtility::isoDateFormat($_POST['sampleReceivedOn'] ?? '', true);

        // ONLY IF SAMPLE ID IS NOT ALREADY GENERATED
        if (empty($sampleRow['sample_code']) || $sampleRow['sample_code'] == 'null') {

            $sampleCodeParams = [];
            $sampleCodeParams['sampleCollectionDate'] = DateUtility::humanReadableDateFormat($sampleRow['sample_collection_date'] ?? '');
            $sampleCodeParams['provinceCode'] = $provinceCode;
            $sampleCodeParams['insertOperation'] = true;
            $sampleJson = $tbService->getSampleCode($sampleCodeParams);
            $sampleData = json_decode((string) $sampleJson, true);
            $tbData = [];
            $tbData['sample_code'] = $sampleData['sampleCode'];
            $tbData['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $tbData['sample_code_key'] = $sampleData['sampleCodeKey'];
            if (!empty($_POST['sampleReceivedOn'])) {
                $tbData['sample_tested_datetime'] = null;
                $tbData['sample_received_at_lab_datetime'] = $_POST['sampleReceivedOn'];
            }
            $tbData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
            $tbData['data_sync'] = 0;
            $tbData['last_modified_by'] = $_SESSION['userId'];
            $tbData['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            $db->where('tb_id', $sampleRow['tb_id']);
            $id = $db->update('form_tb', $tbData);
            if ($id === true) {
                $status = 1;
            }
        }
    }
    $db->commitTransaction();
} catch (Throwable $exception) {
    $db->rollbackTransaction();
    error_log("Error while generating Sample Codes : " . $exception->getMessage());
}
echo $status;
