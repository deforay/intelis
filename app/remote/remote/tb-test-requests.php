<?php
require_once(dirname(__FILE__) . "/../../../startup.php");
header('Content-Type: application/json');

$origData = $jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);


$payload = array();

$labId = $data['labName'] ?: $data['labId'] ?: null;

if (empty($labId)) {
    exit(0);
}
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
$counter = 0;

if ($db->count > 0) {
    $counter = $db->count;

    $sampleIds = array_column($tbRemoteResult, 'tb_id');

    $data['result'] = $tbRemoteResult;

    $db->where('tb_id', $sampleIds, 'IN')
        ->update('form_tb', array('data_sync' => 1));
}

$payload = json_encode($data);

$general->addApiTracking('vlsm-system', $counter, 'requests', 'tb', null, $origData, $payload, 'json', $labId);

echo $payload;

