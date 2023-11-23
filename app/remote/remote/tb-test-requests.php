<?php

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

header('Content-Type: application/json');
try {
    $db->startTransaction();
    //$jsonData = $contentEncoding = $request->getHeaderLine('Content-Encoding');

    /** @var ApiService $apiService */
    $apiService = ContainerRegistry::get(ApiService::class);

    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = $GLOBALS['request'];
    $data = $apiService->getJsonFromRequest($request);


    $payload = [];

    $labId = $data['labName'] ?? $data['labId'] ?? null;

    if (empty($labId)) {
        exit(0);
    }
    $dataSyncInterval = $general->getGlobalConfig('data_sync_interval') ?? 30;

    $transactionId = $general->generateUUID();

    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);
    $fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

    if (!empty($fMapResult)) {
        $condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
    } else {
        $condition = "lab_id =" . $labId;
    }


    $tbQuery = "SELECT * FROM form_tb WHERE $condition ";

    if (!empty($data['manifestCode'])) {
        $tbQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "'";
    } else {
        $tbQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( '" . DateUtility::getCurrentDateTime() . "', INTERVAL $dataSyncInterval DAY)";
    }

    $tbRemoteResult = $db->rawQuery($tbQuery);
    $data = [];
    $counter = 0;
    $sampleIds = $facilityIds = [];
    if ($db->count > 0) {
        $counter = $db->count;

        $sampleIds = array_column($tbRemoteResult, 'tb_id');
        $facilityIds = array_column($tbRemoteResult, 'facility_id');

        $data['result'] = $tbRemoteResult;
    }

    $payload = json_encode($data);

    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'tb', $_SERVER['REQUEST_URI'], $jsonData, $payload, 'json', $labId);


    $general->updateTestRequestsSyncDateTime('tb', 'form_tb', 'tb_id', $sampleIds, $transactionId, $facilityIds, $labId);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();

    error_log($db->getLastError());
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}

echo $payload;
