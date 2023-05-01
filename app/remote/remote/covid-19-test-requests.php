<?php

use App\Services\Covid19Service;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

header('Content-Type: application/json');

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$origData = $jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);


$payload = [];

$labId = $data['labName'] ?? $data['labId'] ?? null;

if (empty($labId)) {
  exit(0);
}


$transactionId = $general->generateUUID();

$dataSyncInterval = $general->getGlobalConfig('data_sync_interval');
$dataSyncInterval = (isset($dataSyncInterval) && !empty($dataSyncInterval)) ? $dataSyncInterval : 30;

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
  //$covid19Query .= " AND data_sync=0 AND sample_package_code like '" . $data['manifestCode'] . "%'";
  $covid19Query .= " AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
  $covid19Query .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
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

$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'covid19', $_SERVER['REQUEST_URI'], $origData, $payload, 'json', $labId);

$currentDateTime = DateUtility::getCurrentDateTime();
if (!empty($sampleIds)) {
  $sql = 'UPDATE form_covid19 SET data_sync = ?,
              form_attributes = JSON_SET(COALESCE(form_attributes, "{}"), "$.remoteRequestsSync", ?, "$.requestSyncTransactionId", ?)
              WHERE covid19_id IN (' . implode(",", $sampleIds) . ')';
  $db->rawQuery($sql, array(1, $currentDateTime, $transactionId));
}

if (!empty($facilityIds)) {
  $facilityIds = array_unique(array_filter($facilityIds));
  $sql = 'UPDATE facility_details 
            SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.remoteRequestsSync", ?, "$.covid19RemoteRequestsSync", ?) 
            WHERE facility_id IN (' . implode(",", $facilityIds) . ')';
  $db->rawQuery($sql, array($currentDateTime, $currentDateTime));
}

// Whether any data got synced or not, we will update sync datetime for the lab
$sql = 'UPDATE facility_details 
        SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.lastRequestsSync", ?, "$.covid19LastRequestsSync", ?) 
        WHERE facility_id = ?';
$db->rawQuery($sql, array($currentDateTime, $currentDateTime, $labId));

echo $payload;
