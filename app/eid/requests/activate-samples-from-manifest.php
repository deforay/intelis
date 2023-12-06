<?php

use App\Services\EidService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var EidService $eidObj */
$eidObj = ContainerRegistry::get(EidService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$queryParams = explode(',', (string) $_POST['sampleId']);
$placeholders = implode(', ', array_fill(0, count($queryParams), '?'));
try {

    $db->beginTransaction();
    $sampleQuery = "SELECT eid_id,
                sample_collection_date,
                sample_package_code,
                province_id,
                sample_code
                FROM form_eid WHERE eid_id IN ($placeholders)";
    $sampleResult = $db->rawQuery($sampleQuery, $queryParams);

    $_POST['sampleReceivedOn'] = DateUtility::isoDateFormat($_POST['sampleReceivedOn'] ?? '', true);

    $status = 0;
    foreach ($sampleResult as $sampleRow) {

        $provinceCode = null;
        if (!empty($sampleRow['province_id'])) {
            $provinceQuery = "SELECT * FROM geographical_divisions WHERE geo_id= ?";
            $provinceResult = $db->rawQueryOne($provinceQuery, [$sampleRow['province_id']]);
            $provinceCode = $provinceResult['geo_code'];
        }
        // ONLY IF SAMPLE ID IS NOT ALREADY GENERATED
        if (empty($sampleRow['sample_code']) || $sampleRow['sample_code'] == 'null') {

            $sampleCodeParams = [];
            $sampleCodeParams['sampleCollectionDate'] = DateUtility::humanReadableDateFormat($sampleRow['sample_collection_date'] ?? '');
            $sampleCodeParams['provinceCode'] = $provinceCode ?? null;
            $sampleCodeParams['insertOperation'] = true;
            $sampleJson = $eidObj->getSampleCode($sampleCodeParams);
            $sampleData = json_decode((string) $sampleJson, true);

            $eidData['sample_code'] = $sampleData['sampleCode'];
            $eidData['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $eidData['sample_code_key'] = $sampleData['sampleCodeKey'];
            $eidData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
            $eidData['data_sync'] = 0;
            if (!empty($_POST['sampleReceivedOn'])) {
                $eidData['sample_tested_datetime'] = null;
                $eidData['sample_received_at_lab_datetime'] = DateUtility::isoDateFormat($_POST['sampleReceivedOn'], true);
            }
            $eidData['last_modified_by'] = $_SESSION['userId'];
            $eidData['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            $db->where('eid_id', $sampleRow['eid_id']);
            $id = $db->update('form_eid', $eidData);
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
