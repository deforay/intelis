<?php

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
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
        throw new SystemException('Lab ID is missing in the request', 400);
    }
    $dataSyncInterval = $general->getGlobalConfig('data_sync_interval') ?? 30;

    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);
    $fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

    if (!empty($fMapResult)) {
        $condition = "(lab_id =$labId OR facility_id IN ($fMapResult))";
    } else {
        $condition = "lab_id =$labId";
    }


    $sQuery = "SELECT * FROM form_tb WHERE $condition ";

    if (!empty($data['manifestCode'])) {
        $sQuery .= " AND sample_package_code like '{$data['manifestCode']}'";
    } else {
        $sQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( '" . DateUtility::getCurrentDateTime() . "', INTERVAL $dataSyncInterval DAY)";
    }

    [$rResult, $resultCount] = $db->getRequestAndCount($sQuery, returnGenerator: false);
    $response = [];
    $sampleIds = $facilityIds = [];
    if ($resultCount > 0) {
        $sampleIds = array_column($rResult, 'tb_id');
        $facilityIds = array_column($rResult, 'facility_id');
        $response['result'] = $rResult;
    }

    $payload = JsonUtility::encodeUtf8Json($response);

    $general->addApiTracking($transactionId, 'vlsm-system', $resultCount, 'requests', 'tb', $_SERVER['REQUEST_URI'], JsonUtility::encodeUtf8Json($data), $payload, 'json', $labId);

    if (!empty($facilityIds)) {
        $general->updateTestRequestsSyncDateTime('tb', $facilityIds, $labId);
    }

    if (!empty($sampleIds)) {
        $updateData = [
            'data_sync' => 1
        ];
        $db->where('tb_id', $sampleIds, 'IN');
        $db->update('form_tb', $updateData);
    }

    $db->commitTransaction();
} catch (Throwable $e) {
    $db->rollbackTransaction();

    $payload = json_encode([]);

    if ($db->getLastErrno() > 0) {
        error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastErrno());
        error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
        error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
    }
    throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);
}

echo ApiService::sendJsonResponse($payload, $request);
