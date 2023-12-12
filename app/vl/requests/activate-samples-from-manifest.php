<?php

use App\Registries\AppRegistry;
use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlObj */
$vlObj = ContainerRegistry::get(VlService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();

$queryParams = explode(',', (string) $_POST['sampleId']);
$placeholders = implode(', ', array_fill(0, count($queryParams), '?'));

try {

    $db->beginTransaction();
    $sampleQuery = "SELECT vl_sample_id,
                sample_collection_date,
                sample_package_code,
                province_id,
                sample_code
                FROM form_vl WHERE vl_sample_id IN ($placeholders)";
    $sampleResult = $db->rawQuery($sampleQuery, $queryParams);

    $status = 0;
    foreach ($sampleResult as $sampleRow) {

        $provinceCode = null;

        if (!empty($sampleRow['province_id'])) {
            $provinceQuery = "SELECT * FROM geographical_divisions WHERE geo_id = ?";
            $provinceResult = $db->rawQueryOne($provinceQuery, [$sampleRow['province_id']]);
            $provinceCode = $provinceResult['geo_code'];
        }

        $_POST['sampleReceivedOn'] = DateUtility::isoDateFormat($_POST['sampleReceivedOn'] ?? '', true);

        // ONLY IF SAMPLE ID IS NOT ALREADY GENERATED
        if (empty($sampleRow['sample_code']) || $sampleRow['sample_code'] == 'null') {

            $sampleCodeParams = [];
            $sampleCodeParams['sampleCollectionDate'] = DateUtility::humanReadableDateFormat($sampleRow['sample_collection_date'] ?? '');
            $sampleCodeParams['provinceCode'] = $provinceCode ?? null;
            $sampleCodeParams['insertOperation'] = true;
            $sampleJson = $vlObj->getSampleCode($sampleCodeParams);
            $sampleData = json_decode((string) $sampleJson, true);

            $vldata['sample_code'] = $sampleData['sampleCode'];
            $vldata['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $vldata['sample_code_key'] = $sampleData['sampleCodeKey'];
            $vldata['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
            $vldata['data_sync'] = 0;

            $vldata['last_modified_by'] = $_SESSION['userId'];
            $vldata['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            if (!empty($_POST['sampleReceivedOn'])) {
                $vldata['sample_tested_datetime'] = null;
                $vldata['sample_received_at_lab_datetime'] = $_POST['sampleReceivedOn'];
            }
            $db->where('vl_sample_id', $sampleRow['vl_sample_id']);
            $id = $db->update('form_vl', $vldata);

            if ($id === true) {
                $status = 1;
            }
        }
    }
    $db->commitTransaction();
} catch (Exception | SystemException $exception) {
    $db->rollbackTransaction();
    error_log("Error while generating Sample Codes : " . $exception->getMessage());
}
echo $status;
