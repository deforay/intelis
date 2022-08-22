<?php
//this file is get the data from remote db
$data = json_decode(file_get_contents('php://input'), true);
require_once(dirname(__FILE__) . "/../../../startup.php");
header('Content-Type: application/json');


$labId = $data['labName'];

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


$tbQuery = "SELECT * FROM form_tb WHERE $condition ";

if (!empty($data['manifestCode'])) {
    $tbQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
    $tbQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}

$tbRemoteResult = $db->rawQuery($tbQuery);
$data = array();

if (!empty($tbRemoteResult) && count($tbRemoteResult) > 0) {
    $trackId = $app->addApiTracking(null, count($tbRemoteResult), 'requests', 'tb', null, $labId, 'sync-api');

    $sampleIds = array_column($tbRemoteResult, 'tb_id');

    $data['result'] = $tbRemoteResult;

    $updata = array(
        'data_sync' => 1
    );
    $db->where('tb_id', $sampleIds, 'IN');

    if (!$db->update('form_tb', $updata))
        error_log('update failed: ' . $db->getLastError());
}

echo json_encode($data);
