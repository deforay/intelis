<?php

use App\Registries\AppRegistry;
use App\Services\ApiService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

header('Content-Type: application/json');

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

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

  $response  = $sampleIds = $facilityIds = [];
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

    $response = [];
    $response['result'] = $covid19RemoteResult;
    $response['symptoms'] = $symptoms;
    $response['comorbidities'] = $comorbidities;
    $response['testResults'] = $testResults;
  }


  $payload = json_encode($response);

  $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'covid19', $_SERVER['REQUEST_URI'], json_encode($data), $payload, 'json', $labId);

  $general->updateTestRequestsSyncDateTime('covid19', 'form_covid19', 'covid19_id', $sampleIds, $transactionId, $facilityIds, $labId);
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
