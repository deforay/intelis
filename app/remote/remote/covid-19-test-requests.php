<?php
//this file is get the data from remote db
$data = json_decode(file_get_contents('php://input'), true);
require_once(dirname(__FILE__) . "/../../../startup.php");

$labId = $data['labName'];

$general = new \Vlsm\Models\General();
$dataSyncInterval = $general->getGlobalConfig('data_sync_interval');
$dataSyncInterval = (isset($dataSyncInterval) && !empty($dataSyncInterval)) ? $dataSyncInterval : 30;
$app = new \Vlsm\Models\App();

//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
  $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
//get remote data
if (trim($sarr['sc_testing_lab_id']) == '') {
  $sarr['sc_testing_lab_id'] = "''";
}

//get facility map id
$facilityMapQuery = "SELECT facility_id FROM testing_lab_health_facilities_map where vl_lab_id=" . $labId;
$fMapResult = $db->query($facilityMapQuery);
if (count($fMapResult) > 0) {
  $fMapResult = array_map('current', $fMapResult);
  $fMapResult = implode(",", $fMapResult);
} else {
  $fMapResult = "";
}

if (isset($fMapResult) && $fMapResult != '' && $fMapResult != null) {
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

  $trackId = $app->addApiTracking(null, count($covid19RemoteResult), 'requests', 'covid19', null, $sarr['sc_testing_lab_id'], 'sync-api');
  $forms = array();
  foreach ($covid19RemoteResult as $row) {
    $forms[] = $row['covid19_id'];
  }

  $covid19Obj = new \Vlsm\Models\Covid19();
  $symptoms = $covid19Obj->getCovid19SymptomsByFormId($forms);
  $comorbidities = $covid19Obj->getCovid19ComorbiditiesByFormId($forms);
  $testResults = $covid19Obj->getCovid19TestsByFormId($forms);

  $data = array();
  $data['result'] = $covid19RemoteResult;
  $data['symptoms'] = $symptoms;
  $data['comorbidities'] = $comorbidities;
  $data['testResults'] = $testResults;

  $updata = array(
    'data_sync' => 1
  );
  $db->where('covid19_id', $forms, 'IN');

  if (!$db->update('form_covid19', $updata))
    error_log('update failed: ' . $db->getLastError());
}
echo json_encode($data);
