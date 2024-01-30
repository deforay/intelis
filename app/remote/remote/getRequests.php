<?php

use App\Registries\AppRegistry;
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
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
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
    $condition = "lab_id = " . $labId;
  }

  // oldName => newName for existing columns
  $aliasColumns = [
    'sample_type' => 'specimen_type',
    //'patient_art_no' => 'patient_id'
  ];

  // columnName => constantValue for non-existent columns
  $constantColumns = [
    'sample_code_title' => "'auto'"
  ];

  // Start with selecting all columns
  $columnSelection = "*";

  // Add each new column with its alias for existing columns
  foreach ($aliasColumns as $oldName => $newName) {
    $columnSelection .= ", $newName AS $oldName";
  }

  // Add constant columns
  foreach ($constantColumns as $columnName => $constantValue) {
    $columnSelection .= ", $constantValue AS $columnName";
  }

  // Construct the final SQL query
  $vlQuery = "SELECT $columnSelection FROM form_vl WHERE $condition";

  // Now $vlQuery contains your complete SQL statement


  // Construct the final SQL query
  $vlQuery = "SELECT $columnSelection FROM form_vl WHERE $condition";

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
  $general->updateTestRequestsSyncDateTime('vl', $facilityIds, $labId);

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

echo $apiService->sendJsonResponse($payload);
