<?php

use App\Services\ApiService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$data = $apiService->getJsonFromRequest($request, true);

$labId = $data['labName'] ?? $data['labId'] ?? null;

if (empty($labId)) {
  LoggerUtility::log('error', 'Lab ID is missing in the VL request', [
    'line' => __LINE__,
    'file' => __FILE__
  ]);
  exit(0);
}
$payload = [];
$dataSyncInterval = $general->getGlobalConfig('data_sync_interval') ?? 30;

try {
  $db->beginTransaction();
  $transactionId = $general->generateUUID();

  $counter = 0;


  $facilitiesService = ContainerRegistry::get(FacilitiesService::class);
  $fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

  if (!empty($fMapResult)) {
    $condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
  } else {
    $condition = "lab_id =" . $labId;
  }


  $removeKeys = [
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
  ];

  $vlQuery = "SELECT * FROM form_vl
                    WHERE $condition ";

  if (!empty($data['manifestCode'])) {
    $vlQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "'";
  } else {
    $vlQuery .= " AND data_sync=0 AND last_modified_datetime >= SUBDATE( '" . DateUtility::getCurrentDateTime() . "', INTERVAL $dataSyncInterval DAY)";
  }

  $vlRemoteResult = $db->rawQuery($vlQuery);

  $sampleIds = $facilityIds = [];
  if ($db->count > 0) {

    $payload = $vlRemoteResult;
    $counter = $db->count;

    $sampleIds = array_column($vlRemoteResult, 'vl_sample_id');
    $facilityIds = array_column($vlRemoteResult, 'facility_id');

    $payload = json_encode($vlRemoteResult);
  } else {
    $payload = json_encode([]);
  }


  $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'vl', $_SERVER['REQUEST_URI'], MiscUtility::convertToUtf8AndEncode($data), $payload, 'json', $labId);


  $general->updateTestRequestsSyncDateTime('vl', 'form_vl', 'vl_sample_id', $sampleIds, $transactionId, $facilityIds, $labId);
  $db->commitTransaction();
} catch (Exception | SystemException $e) {
  $db->rollbackTransaction();

  $payload = json_encode([]);

  if ($db->getLastErrno() > 0) {
    error_log($db->getLastErrno());
    error_log($db->getLastError());
    error_log($db->getLastQuery());
  }
  throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);
}

echo $payload;
