<?php

use App\Registries\AppRegistry;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$queryParams = explode(',', (string) $_POST['sampleId']);
$placeholders = implode(', ', array_fill(0, count($queryParams), '?'));
try {

    $db->beginTransaction();
    $sampleQuery = "SELECT covid19_id,
                sample_collection_date,
                sample_package_code,
                province_id,
                sample_code
                FROM form_covid19 WHERE covid19_id IN ($placeholders)";
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
            $sampleJson = $covid19Service->getSampleCode($sampleCodeParams);
            $sampleData = json_decode((string) $sampleJson, true);
            $covid19Data = [];
            $covid19Data['sample_code'] = $sampleData['sampleCode'];
            $covid19Data['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $covid19Data['sample_code_key'] = $sampleData['sampleCodeKey'];
            $covid19Data['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
            $covid19Data['data_sync'] = 0;
            if (!empty($_POST['sampleReceivedOn'])) {
                $covid19Data['sample_tested_datetime'] = null;
                $covid19Data['sample_received_at_lab_datetime'] = $_POST['sampleReceivedOn'];
            }
            $covid19Data['last_modified_by'] = $_SESSION['userId'];
            $covid19Data['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            $db->where('covid19_id', $sampleRow['covid19_id']);
            $id = $db->update('form_covid19', $covid19Data);
            if ($id === true) {
                $status = 1;
            }
        }
    }
    $db->commitTransaction();
} catch (Exception | SystemException $exception) {
    $db->rollbackTransaction();
    error_log("Error while generating Sample IDs : " . $exception->getMessage());
}
echo $status;
