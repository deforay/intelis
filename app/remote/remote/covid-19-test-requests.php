<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

header('Content-Type: application/json');

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


try {
  $db->startTransaction();
  //$jsonData = $contentEncoding = $request->getHeaderLine('Content-Encoding');

  /** @var Laminas\Diactoros\ServerRequest $request */
  $request = $GLOBALS['request'];

  // Get the content encoding header to check for gzip
  $contentEncoding = $request->getHeaderLine('Content-Encoding');

  // Read the JSON response from the input
  $jsonData = $request->getBody()->getContents();

  // If content is gzip-compressed, decompress it
  if ($contentEncoding === 'gzip') {
    $jsonData = gzdecode($jsonData);
  }
  // Check if the data is valid UTF-8, convert if not
  if (!mb_check_encoding($jsonData, 'UTF-8')) {
    $jsonData = mb_convert_encoding($jsonData, 'UTF-8', 'auto');
  }

  $data = json_decode($jsonData, true);


  $payload = [];

  $labId = $data['labName'] ?? $data['labId'] ?? null;

  if (empty($labId)) {
    exit(0);
  }

  $transactionId = $general->generateUUID();

  $dataSyncInterval = $general->getGlobalConfig('data_sync_interval') ?? 30;

  $facilitiesService = ContainerRegistry::get(FacilitiesService::class);
  $fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

  if (!empty($fMapResult)) {
    $condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
  } else {
    $condition = "lab_id =" . $labId;
  }

  $covid19Query = "SELECT * FROM form_covid19
                    WHERE $condition ";

  if (!empty($data['manifestCode'])) {
    $covid19Query .= " AND sample_package_code like '" . $data['manifestCode'] . "'";
  } else {
    $covid19Query .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( '" . DateUtility::getCurrentDateTime() . "', INTERVAL $dataSyncInterval DAY)";
  }


  $covid19RemoteResult = $db->rawQuery($covid19Query);

  $data  = $sampleIds = $facilityIds = [];
  $counter = 0;
  if ($db->count > 0) {
    $counter = $db->count;
    $sampleIds = array_column($covid19RemoteResult, 'covid19_id');
    $facilityIds = array_column($covid19RemoteResult, 'facility_id');


    /** @var Covid19Service $covid19Service */
    $covid19Service = ContainerRegistry::get(Covid19Service::class);
    $symptoms = $covid19Service->getCovid19SymptomsByFormId($sampleIds);
    $comorbidities = $covid19Service->getCovid19ComorbiditiesByFormId($sampleIds);
    $testResults = $covid19Service->getCovid19TestsByFormId($sampleIds);

    $data = [];
    $data['result'] = $covid19RemoteResult;
    $data['symptoms'] = $symptoms;
    $data['comorbidities'] = $comorbidities;
    $data['testResults'] = $testResults;
  }


  $payload = json_encode($data);

  $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'covid19', $_SERVER['REQUEST_URI'], $jsonData, $payload, 'json', $labId);

  $general->updateTestRequestsSyncDateTime('covid19', 'form_covid19', 'covid19_id', $sampleIds, $transactionId, $facilityIds, $labId);
  $db->commit();
} catch (Exception $e) {
  $db->rollback();

  error_log($db->getLastError());
  error_log($e->getMessage());
  error_log($e->getTraceAsString());
  throw new SystemException($e->getMessage(), $e->getCode(), $e);
}

echo $payload;
