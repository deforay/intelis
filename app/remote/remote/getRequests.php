<?php

use App\Models\App;
use App\Models\Facilities;
use App\Models\General;
use App\Utilities\DateUtils;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

header('Content-Type: application/json');

$general = new General();

$origData = $jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);


$payload = array();

$labId = $data['labName'] ?: $data['labId'] ?: null;

if (empty($labId)) {
  exit(0);
}


$dataSyncInterval = $general->getGlobalConfig('data_sync_interval');
$dataSyncInterval = !empty($dataSyncInterval) ? $dataSyncInterval : 30;
$app = new App();

$transactionId = $general->generateUUID();

$counter = 0;


$facilityDb = new Facilities();
$fMapResult = $facilityDb->getTestingLabFacilityMap($labId);

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

$vlQuery = "SELECT * FROM form_vl 
                    WHERE $condition ";

if (!empty($data['manifestCode'])) {
  //$vlQuery .= " AND data_sync=0 AND sample_package_code like '" . $data['manifestCode'] . "%'";
  $vlQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
  $vlQuery .= " AND data_sync=0 AND last_modified_datetime >= SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}

$vlRemoteResult = $db->rawQuery($vlQuery);

$sampleIds = $facilityIds = array();
if ($db->count > 0) {

  $payload = $vlRemoteResult;
  // foreach ($vlRemoteResult as $row) {
  //   $payload[] = array_diff_key($row, array_flip($removeKeys));
  // }

  $counter = $db->count;

  $sampleIds = array_column($vlRemoteResult, 'vl_sample_id');
  $facilityIds = array_column($vlRemoteResult, 'facility_id');

  $payload = json_encode($vlRemoteResult);
} else {
  $payload = json_encode([]);
}


$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'vl', $_SERVER['REQUEST_URI'], $origData, $payload, 'json', $labId);


$currentDateTime = DateUtils::getCurrentDateTime();
if (!empty($sampleIds)) {
  $sql = 'UPDATE form_vl SET data_sync = ?,
              form_attributes = JSON_SET(COALESCE(form_attributes, "{}"), "$.remoteRequestsSync", ?, "$.requestSyncTransactionId", ?)
              WHERE vl_sample_id IN (' . implode(",", $sampleIds) . ')';
  $db->rawQuery($sql, array(1, $currentDateTime, $transactionId));
}

if (!empty($facilityIds)) {
  $facilityIds = array_unique(array_filter($facilityIds));
  $sql = 'UPDATE facility_details 
            SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.remoteRequestsSync", ?, "$.vlRemoteRequestsSync", ?) 
            WHERE facility_id IN (' . implode(",", $facilityIds) . ')';
  $db->rawQuery($sql, array($currentDateTime, $currentDateTime));
}

// Whether any data got synced or not, we will update sync datetime for the lab
$sql = 'UPDATE facility_details 
        SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.lastRequestsSync", ?, "$.vlLastRequestsSync", ?) 
        WHERE facility_id = ?';
$db->rawQuery($sql, array($currentDateTime, $currentDateTime, $labId));

echo $payload;
