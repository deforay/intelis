<?php

use App\Registries\AppRegistry;
use App\Services\ApiService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
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


  $dataSyncInterval = $general->getGlobalConfig('data_sync_interval');
  $dataSyncInterval = !empty($dataSyncInterval) ? $dataSyncInterval : 30;



  $transactionId = $general->generateUUID();

  $counter = 0;


  $facilitiesService = ContainerRegistry::get(FacilitiesService::class);
  $fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

  if (!empty($fMapResult)) {
    $condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
  } else {
    $condition = "lab_id =" . $labId;
  }


  $removeKeys = array(
    'sample_code',
    'sample_code_key',
    'sample_code_format',
    'sample_batch_id',
    'sample_received_at_lab_datetime',
    'eid_test_platform',
    'import_machine_name',
    'sample_tested_datetime',
    'is_sample_rejected',
    'lab_id',
    'result',
    'tested_by',
    'lab_tech_comments',
    'result_approved_by',
    'result_approved_datetime',
    'revised_by',
    'revised_on',
    'result_reviewed_by',
    'result_reviewed_datetime',
    'result_dispatched_datetime',
    'reason_for_changing',
    'result_status',
    'data_sync',
    'reason_for_sample_rejection',
    'rejection_on',
    'last_modified_by',
    'result_printed_datetime',
    'last_modified_datetime'
  );

  $vlQuery = "SELECT * FROM form_generic
                    WHERE $condition ";

  if (!empty($data['manifestCode'])) {
    $vlQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "'";
  } else {
    $vlQuery .= " AND data_sync=0 AND last_modified_datetime >= SUBDATE( '" . DateUtility::getCurrentDateTime() . "', INTERVAL $dataSyncInterval DAY)";
  }

  $genericRemoteResult = $db->rawQuery($vlQuery);

  $response = $sampleIds = $facilityIds = [];
  if ($db->count > 0) {

    $payload = $genericRemoteResult;

    $counter = $db->count;

    $sampleIds = array_column($genericRemoteResult, 'sample_id');
    $facilityIds = array_column($genericRemoteResult, 'facility_id');

    /** @var GenericTestsService $general */
    $generic = ContainerRegistry::get(GenericTestsService::class);
    $testResults = $generic->getTestsByGenericSampleIds($sampleIds);

    $response = [];
    $response['result'] = $genericRemoteResult;
    $response['testResults'] = $testResults;
  }
  $payload = json_encode($response);

  $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'generic-tests', $_SERVER['REQUEST_URI'], json_encode($data), $payload, 'json', $labId);


  $general->updateTestRequestsSyncDateTime('generic', $facilityIds, $labId);
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
