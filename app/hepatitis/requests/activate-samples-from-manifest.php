<?php

use App\Registries\AppRegistry;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


$queryParams = explode(',', (string) $_POST['sampleId']);
$placeholders = implode(', ', array_fill(0, count($queryParams), '?'));
try {

    $db->beginTransaction();
    $sampleQuery = "SELECT hepatitis_id,
                sample_collection_date,
                sample_package_code,
                hepatitis_test_type,
                province_id,
                sample_code
                FROM form_hepatitis WHERE hepatitis_id IN ($placeholders)";
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
            $sampleCodeParams['prefix'] = $sampleRow['hepatitis_test_type'] ?? null;
            $sampleCodeParams['provinceCode'] = $provinceCode;
            $sampleCodeParams['insertOperation'] = true;
            $sampleJson = $hepatitisService->getSampleCode($sampleCodeParams);
            $sampleData = json_decode((string) $sampleJson, true);
            $hepatitisData = [];
            $hepatitisData['sample_code'] = $sampleData['sampleCode'];
            $hepatitisData['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $hepatitisData['sample_code_key'] = $sampleData['sampleCodeKey'];
            $hepatitisData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
            $hepatitisData['data_sync'] = 0;
            $hepatitisData['last_modified_datetime'] = DateUtility::getCurrentDateTime();
            if (!empty($_POST['sampleReceivedOn'])) {
                $hepatitisData['sample_tested_datetime'] = null;
                $hepatitisData['sample_received_at_lab_datetime'] = $_POST['sampleReceivedOn'];
            }
            $hepatitisData['last_modified_by'] = $_SESSION['userId'];
            $hepatitisData['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            $db->where('hepatitis_id', $sampleRow['hepatitis_id']);
            $id = $db->update('form_hepatitis', $hepatitisData);
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
