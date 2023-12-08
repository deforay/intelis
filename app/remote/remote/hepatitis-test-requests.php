<?php

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\HepatitisService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");
header('Content-Type: application/json');
try {
    $db->beginTransaction();

    /** @var ApiService $apiService */
    $apiService = ContainerRegistry::get(ApiService::class);

    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = $GLOBALS['request'];
    $data = $apiService->getJsonFromRequest($request, true);


    $payload = [];

    $labId = $data['labName'] ?? $data['labId'] ?? null;

    if (empty($labId)) {
        exit(0);
    }

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $dataSyncInterval = $general->getGlobalConfig('data_sync_interval') ?? 30;
    $transactionId = $general->generateUUID();

    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);
    $fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

    if (!empty($fMapResult)) {
        $condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
    } else {
        $condition = "lab_id =" . $labId;
    }

    $hepatitisQuery = "SELECT * FROM form_hepatitis
                    WHERE $condition ";

    if (!empty($data['manifestCode'])) {
        $hepatitisQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "'";
    } else {
        $hepatitisQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( '" . DateUtility::getCurrentDateTime() . "', INTERVAL $dataSyncInterval DAY)";
    }


    $hepatitisRemoteResult = $db->rawQuery($hepatitisQuery);
    $response = [];
    $counter = 0;

    $sampleIds = $facilityIds = [];
    if ($db->count > 0) {
        $counter = $db->count;

        $sampleIds = array_column($hepatitisRemoteResult, 'hepatitis_id');
        $facilityIds = array_column($hepatitisRemoteResult, 'facility_id');


        /** @var HepatitisService $hepatitisService */
        $hepatitisService = ContainerRegistry::get(HepatitisService::class);
        $comorbidities = $hepatitisService->getComorbidityByHepatitisId($sampleIds);
        $risks = $hepatitisService->getRiskFactorsByHepatitisId($sampleIds);


        $response['result'] = $hepatitisRemoteResult ?? [];
        $response['risks'] = $risks ?? [];
        $response['comorbidities'] = $comorbidities ?? [];
    }

    $payload = json_encode($response);

    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'hepatitis', $_SERVER['REQUEST_URI'], json_encode($data), $payload, 'json', $labId);

    $general->updateTestRequestsSyncDateTime('hepatitis', 'form_hepatitis', 'hepatitis_id', $sampleIds, $transactionId, $facilityIds, $labId);
    $db->commitTransaction();
} catch (Exception $e) {
    $db->rollbackTransaction();

    error_log($db->getLastError());
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}

echo $payload;
