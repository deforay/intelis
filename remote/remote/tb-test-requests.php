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
if (isset($labId) && $labId != "") {
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
}

$tbQuery = "SELECT * FROM form_tb WHERE $condition ";

if (!empty($data['manifestCode'])) {
    $tbQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
    $tbQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}

$tbRemoteResult = $db->rawQuery($tbQuery);
$data = array();

if (!empty($tbRemoteResult) && count($tbRemoteResult) > 0) {
    $trackId = $app->addApiTracking('', count($tbRemoteResult), 'requests', 'tb', null, $sarr['sc_testing_lab_id'], 'sync-api');
    $forms = array();
    foreach ($tbRemoteResult as $row) {
        $forms[] = $row['tb_id'];
    }

    $data['result'] = $tbRemoteResult;

    $updata = array(
        'data_sync' => 1
    );
    $db->where('tb_id', $forms, 'IN');

    if (!$db->update('form_tb', $updata))
        error_log('update failed: ' . $db->getLastError());
}

echo json_encode($data);
