<?php

use App\Services\ApiService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
header('Content-Type: application/json');

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

// /** @var ApiService $app */
// $app = ContainerRegistry::get(ApiService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

if (!empty($fMapResult)) {
  $condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
} else {
  $condition = "lab_id =" . $labId;
}

$eidQuery = "SELECT * FROM form_eid 
                    WHERE $condition ";

if (!empty($data['manifestCode'])) {
  //$eidQuery .= " AND data_sync=0 AND sample_package_code like '" . $data['manifestCode'] . "%'";
  $eidQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
  $eidQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}


$eidRemoteResult = $db->rawQuery($eidQuery);

$removeKeys = array(
  'sample_code',
  'sample_code_key',
  'sample_code_format',
  'sample_batch_id',
  'sample_received_at_vl_lab_datetime',
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
$sampleIds = $facilityIds = [];
$counter = 0;
if ($db->count > 0) {
  $payload = $eidRemoteResult;
  // foreach ($eidRemoteResult as $row) {
  //   $payload[] = array_diff_key($row, array_flip($removeKeys));
  // }

  $counter = $db->count;
  $sampleIds = array_column($eidRemoteResult, 'eid_id');
  $facilityIds = array_column($eidRemoteResult, 'facility_id');

  $payload = json_encode($payload);
} else {
  $payload = json_encode([]);
}

$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'eid', $_SERVER['REQUEST_URI'], $origData, $payload, 'json', $labId);


$currentDateTime = DateUtility::getCurrentDateTime();
if (!empty($sampleIds)) {
  $sql = 'UPDATE form_eid SET data_sync = ?,
              form_attributes = JSON_SET(COALESCE(form_attributes, "{}"), "$.remoteRequestsSync", ?, "$.requestSyncTransactionId", ?)
              WHERE eid_id IN (' . implode(",", $sampleIds) . ')';
  $db->rawQuery($sql, array(1, $currentDateTime, $transactionId));
}

if (!empty($facilityIds)) {
  $facilityIds = array_unique(array_filter($facilityIds));
  $sql = 'UPDATE facility_details 
            SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.remoteRequestsSync", ?, "$.eidRemoteRequestsSync", ?) 
            WHERE facility_id IN (' . implode(",", $facilityIds) . ')';
  $db->rawQuery($sql, array($currentDateTime, $currentDateTime));
}

// Whether any data got synced or not, we will update sync datetime for the lab
$sql = 'UPDATE facility_details 
        SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.lastRequestsSync", ?, "$.eidLastRequestsSync", ?) 
        WHERE facility_id = ?';
$db->rawQuery($sql, array($currentDateTime, $currentDateTime, $labId));


echo $payload;
