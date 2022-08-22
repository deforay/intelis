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

$covid19Query = "SELECT * FROM form_covid19 
                    WHERE $condition ";

if (!empty($data['manifestCode'])) {
  $covid19Query .= " AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
  $covid19Query .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}


$covid19RemoteResult = $db->rawQuery($covid19Query);

$data = array();

if (!empty($covid19RemoteResult) && count($covid19RemoteResult) > 0) {

  $trackId = $app->addApiTracking(null, count($covid19RemoteResult), 'requests', 'covid19', null, $labId, 'sync-api');
  
  $sampleIds = array_column($covid19RemoteResult, 'covid19_id');

  $covid19Obj = new \Vlsm\Models\Covid19();
  $symptoms = $covid19Obj->getCovid19SymptomsByFormId($sampleIds);
  $comorbidities = $covid19Obj->getCovid19ComorbiditiesByFormId($sampleIds);
  $testResults = $covid19Obj->getCovid19TestsByFormId($sampleIds);

  $data = array();
  $data['result'] = $covid19RemoteResult;
  $data['symptoms'] = $symptoms;
  $data['comorbidities'] = $comorbidities;
  $data['testResults'] = $testResults;

  $updata = array(
    'data_sync' => 1
  );
  $db->where('covid19_id', $sampleIds, 'IN');

  if (!$db->update('form_covid19', $updata))
    error_log('update failed: ' . $db->getLastError());
}
echo json_encode($data);