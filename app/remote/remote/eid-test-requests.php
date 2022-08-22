<?php
//this file is get the data from remote db
$data = json_decode(file_get_contents('php://input'), true);
require_once(dirname(__FILE__) . "/../../../startup.php");
header('Content-Type: application/json');


$labId = $data['labName'] ?: $data['labId'] ?: null;


$general = new \Vlsm\Models\General();
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
  $eidQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
  $eidQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}


$eidRemoteResult = $db->rawQuery($eidQuery);
if (count($eidRemoteResult) > 0) {
  $sampleIds = array_column($eidRemoteResult, 'eid_id');
  $db->where('eid_id', $sampleIds, 'IN')
      ->update('form_eid', array('data_sync' => 1));
  $trackId = $app->addApiTracking(null, count($eidRemoteResult), 'requests', 'eid', null, $labId, 'sync-api');
}
echo json_encode($eidRemoteResult);
