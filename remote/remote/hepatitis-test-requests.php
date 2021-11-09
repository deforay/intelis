<?php
//this file is get the data from remote db
$data = json_decode(file_get_contents('php://input'), true);
require_once(dirname(__FILE__) . "/../../startup.php");



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
$facilityMapQuery = "SELECT facility_id FROM vl_facility_map where vl_lab_id=" . $labId;
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

$hepatitisQuery = "SELECT * FROM form_hepatitis 
                    WHERE $condition ";

if (!empty($data['manifestCode'])) {
    $hepatitisQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
    $hepatitisQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}




$hepatitisRemoteResult = $db->rawQuery($hepatitisQuery);
$data = array();

if (!empty($hepatitisRemoteResult) && count($hepatitisRemoteResult) > 0) {
    $trackId = $app->addApiTracking('', count($hepatitisRemoteResult), 'requests', 'hepatitis', null, $sarr['sc_testing_lab_id'], 'sync-api');
    $forms = array();
    foreach ($hepatitisRemoteResult as $row) {
        $forms[] = $row['hepatitis_id'];
    }

    $hepatitisObj = new \Vlsm\Models\Hepatitis();
    $comorbidities = $hepatitisObj->getComorbidityByHepatitisId($forms);
    $risks = $hepatitisObj->getRiskFactorsByHepatitisId($forms);


    $data['result'] = $hepatitisRemoteResult;
    $data['risks'] = $risks;
    $data['comorbidities'] = $comorbidities;


    $updata = array(
        'data_sync' => 1
    );
    $db->where('hepatitis_id', $forms, 'IN');

    if (!$db->update('form_hepatitis', $updata))
        error_log('update failed: ' . $db->getLastError());
}

echo json_encode($data);
