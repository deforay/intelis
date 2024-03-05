<?php

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

use App\Registries\AppRegistry;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

header('Content-Type: application/json');
try {
    $db->beginTransaction();

    /** @var ApiService $apiService */
    $apiService = ContainerRegistry::get(ApiService::class);

    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $data = $apiService->getJsonFromRequest($request, true);


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
    $response = [];
    $counter = 0;
    $sampleIds = $facilityIds = [];
    if ($db->count > 0) {
        $counter = $db->count;

        $sampleIds = array_column($tbRemoteResult, 'tb_id');
        $facilityIds = array_column($tbRemoteResult, 'facility_id');

        $response['result'] = $tbRemoteResult;
    }

    $payload = json_encode($response);

    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'tb', $_SERVER['REQUEST_URI'], json_encode($data), $payload, 'json', $labId);


    $general->updateTestRequestsSyncDateTime('tb', $facilityIds, $labId);
    $db->commitTransaction();
} catch (Throwable $e) {
    $db->rollbackTransaction();

    $payload = json_encode([]);

    if ($db->getLastErrno() > 0) {
        error_log($db->getLastErrno());
        error_log($db->getLastError());
        error_log($db->getLastQuery());
    }
    throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);
}

echo $apiService->sendJsonResponse($payload);
