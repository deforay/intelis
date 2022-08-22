<?php
require_once(dirname(__FILE__) . "/../../../startup.php");

header('Content-Type: application/json');

//this file is get the data from remote db
$data = json_decode(file_get_contents('php://input'), true);

$encoding = $general->getHeader('Accept-Encoding');
$payload = array();

$labId = $data['labName'] ?: $data['labId'] ?: null;

if (empty($labId)) {
  exit(0);
}

$general = new \Vlsm\Models\General();
$dataSyncInterval = $general->getGlobalConfig('data_sync_interval');
$dataSyncInterval = !empty($dataSyncInterval) ? $dataSyncInterval : 30;
$app = new \Vlsm\Models\App();

$facilityDb = new \Vlsm\Models\Facilities();
$fMapResult = $facilityDb->getTestingLabFacilityMap($labId);

if (!empty($fMapResult)) {
  $condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
} else {
  $condition = "lab_id =" . $labId;
}

$vlQuery = "SELECT * FROM form_vl 
                    WHERE $condition ";

if (!empty($data['manifestCode'])) {
  $vlQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
  $vlQuery .= " AND data_sync=0 AND last_modified_datetime >= SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}

$vlRemoteResult = $db->rawQuery($vlQuery);

if ($db->count > 0) {
  $trackId = $app->addApiTracking(null, $db->count, 'requests', 'vl', null, $labId, 'sync-api');
  
  $sampleIds = array_column($vlRemoteResult, 'vl_sample_id');
  $db->where('vl_sample_id', $sampleIds, 'IN')
    ->update('form_vl', array('data_sync' => 1));

  $payload = json_encode($vlRemoteResult);
} else {
  $payload = json_encode([]);
}

if (!empty($encoding) && $encoding === 'gzip') {
  header("Content-Encoding: gzip");
  $payload = gzencode($payload);
}

echo $payload;
