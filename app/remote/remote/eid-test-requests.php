<?php

require_once(dirname(__FILE__) . "/../../../startup.php");

$general = new \Vlsm\Models\General();
header('Content-Type: application/json');

$origData = $jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);


$payload = array();

$labId = $data['labName'] ?: $data['labId'] ?: null;

if (empty($labId)) {
  exit(0);
}

$transactionId = $general->generateUUID();

$dataSyncInterval = $general->getGlobalConfig('data_sync_interval');
$dataSyncInterval = (isset($dataSyncInterval) && !empty($dataSyncInterval)) ? $dataSyncInterval : 30;
$app = new \Vlsm\Models\App();

$facilityDb = new \Vlsm\Models\Facilities();
$fMapResult = $facilityDb->getTestingLabFacilityMap($labId);

if (!empty($fMapResult)) {
  $condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
} else {
  $condition = "lab_id =" . $labId;
}

$eidQuery = "SELECT * FROM form_eid 
                    WHERE $condition ";

if (!empty($data['manifestCode'])) {
  $eidQuery .= " AND data_sync=0 AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
  $eidQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}


$eidRemoteResult = $db->rawQuery($eidQuery);

$removeKeys = array(
  'sample_code',
  'sample_code_key',
  'sample_code_format',
  'sample_code_title',
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

$counter = 0;
if ($db->count > 0) {
  $payload = $eidRemoteResult;
  // foreach ($eidRemoteResult as $row) {
  //   $payload[] = array_diff_key($row, array_flip($removeKeys));
  // }

  $counter = $db->count;
  $sampleIds = array_column($eidRemoteResult, 'eid_id');
  $db->where('eid_id', $sampleIds, 'IN')
    ->update('form_eid', array('data_sync' => 1));
  $payload = json_encode($payload);
} else {
  $payload = json_encode([]);
}

$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'eid', null, $origData, $payload, 'json', $labId);

$sql = 'UPDATE facility_details SET facility_attributes = JSON_SET(facility_attributes, "$.lastRequestsSync", ?) WHERE facility_id = ?';
$db->rawQuery($sql, array($general->getCurrentDateTime(), $labId));

echo $payload;
