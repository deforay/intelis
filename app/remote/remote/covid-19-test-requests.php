<?php

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Services\DatabaseService;
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

  $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
  $transactionId = $apiRequestId ?? MiscUtility::generateULID();

  $dataSyncInterval = $general->getGlobalConfig('data_sync_interval') ?? 30;

  $facilitiesService = ContainerRegistry::get(FacilitiesService::class);
  $fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

  if (!empty($fMapResult)) {
    $condition = "(lab_id =$labId OR facility_id IN ($fMapResult))";
  } else {
    $condition = "lab_id =$labId";
  }

  $sQuery = "SELECT * FROM form_covid19 WHERE $condition ";

  if (!empty($data['manifestCode'])) {
    $sQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "'";
  } else {
    $sQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE('" . DateUtility::getCurrentDateTime() . "', INTERVAL $dataSyncInterval DAY)";
  }
  [$rResult, $resultCount] = $db->getQueryResultAndCount($sQuery, returnGenerator: false);
  $response  = $sampleIds = $facilityIds = [];
  $counter = 0;
  $response = [];
  if ($resultCount > 0) {
    $counter = $resultCount;
    $sampleIds = array_column($rResult, 'covid19_id');
    $facilityIds = array_column($rResult, 'facility_id');


    /** @var Covid19Service $covid19Service */
    $covid19Service = ContainerRegistry::get(Covid19Service::class);
    foreach ($rResult as $r) {
      $response[$r['covid19_id']] = $r;
      $response[$r['covid19_id']]['data_from_comorbidities'] = $covid19Service->getCovid19ComorbiditiesByFormId($r['covid19_id'], false, true);
      $response[$r['covid19_id']]['data_from_symptoms'] = $covid19Service->getCovid19SymptomsByFormId($r['covid19_id'], false, true);
      $response[$r['covid19_id']]['data_from_tests'] = $covid19Service->getCovid19TestsByFormId($r['covid19_id']);
    }
  }
  $payload = JsonUtility::encodeUtf8Json(array(
    'labId' => $labId,
    'result' => $response,
  ));

  $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'covid19', $_SERVER['REQUEST_URI'], JsonUtility::encodeUtf8Json($data), $payload, 'json', $labId);

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

echo $apiService->sendJsonResponse($payload, $request);
