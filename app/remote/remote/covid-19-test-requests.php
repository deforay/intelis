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
    throw new SystemException('Lab ID is missing in the request', 400);
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
    $covid19Query .= " AND data_sync=0 AND last_modified_datetime > SUBDATE('" . DateUtility::getCurrentDateTime() . "', INTERVAL $dataSyncInterval DAY)";
  }
  $covid19RemoteResult = $db->rawQuery($covid19Query);
  $response  = $sampleIds = $facilityIds = [];
  $counter = 0;
  $response = [];
  if ($db->count > 0) {
    $counter = $db->count;
    $sampleIds = array_column($covid19RemoteResult, 'covid19_id');
    $facilityIds = array_column($covid19RemoteResult, 'facility_id');


    /** @var Covid19Service $covid19Service */
    $covid19Service = ContainerRegistry::get(Covid19Service::class);
    foreach ($covid19RemoteResult as $r) {
      $response[$r['covid19_id']] = $r;
      $response[$r['covid19_id']]['data_from_comorbidities'] = $covid19Service->getCovid19ComorbiditiesByFormId($r['covid19_id'], false, true);
      $response[$r['covid19_id']]['data_from_symptoms'] = $covid19Service->getCovid19SymptomsByFormId($r['covid19_id'], false, true);
      $response[$r['covid19_id']]['data_from_tests'] = $covid19Service->getCovid19TestsByFormId($r['covid19_id']);
    }
    /* $symptoms = $covid19Service->getCovid19SymptomsByFormId($sampleIds);
    $comorbidities = $covid19Service->getCovid19ComorbiditiesByFormId($sampleIds);
    $testResults = $covid19Service->getCovid19TestsByFormId($sampleIds);
    $response['result'] = $covid19RemoteResult;
    $response['symptoms'] = $symptoms;
    $response['comorbidities'] = $comorbidities;
    $response['testResults'] = $testResults; */
  }
  $payload = json_encode(array(
    'labId' => $labId,
    'result' => $response,
  ));

  $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'covid19', $_SERVER['REQUEST_URI'], json_encode($data), $payload, 'json', $labId);

  $general->updateTestRequestsSyncDateTime('covid19', $facilityIds, $labId);
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

echo $apiService->sendJsonResponse($payload);
