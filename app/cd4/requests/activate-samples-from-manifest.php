<?php

use App\Registries\AppRegistry;
use App\Services\CD4Service;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var CD4Service $cd4Obj */
$cd4Obj = ContainerRegistry::get(CD4Service::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$queryParams = explode(',', (string) $_POST['sampleId']);
$placeholders = implode(', ', array_fill(0, count($queryParams), '?'));

try {

    $db->beginTransaction();
    $sampleQuery = "SELECT cd4_id,
                sample_collection_date,
                sample_package_code,
                province_id,
                sample_code
                FROM form_cd4 WHERE cd4_id IN ($placeholders)";
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
            $sampleJson = $cd4Obj->getSampleCode($sampleCodeParams);
            $sampleData = json_decode((string) $sampleJson, true);

            $dataToUpdate['sample_code'] = $sampleData['sampleCode'];
            $dataToUpdate['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $dataToUpdate['sample_code_key'] = $sampleData['sampleCodeKey'];
            $dataToUpdate['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
            $dataToUpdate['data_sync'] = 0;

            $dataToUpdate['last_modified_by'] = $_SESSION['userId'];
            $dataToUpdate['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            if (!empty($_POST['sampleReceivedOn'])) {
                $dataToUpdate['sample_tested_datetime'] = null;
                $dataToUpdate['sample_received_at_lab_datetime'] = $_POST['sampleReceivedOn'];
            }
            $db->where('cd4_id', $sampleRow['cd4_id']);
            $id = $db->update('form_cd4', $dataToUpdate);

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
