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
$transactionId = $general->generateUUID();

$facilityDb = new \Vlsm\Models\Facilities();
$fMapResult = $facilityDb->getTestingLabFacilityMap($labId);

if (!empty($fMapResult)) {
    $condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
} else {
    $condition = "lab_id =" . $labId;
}

$hepatitisQuery = "SELECT * FROM form_hepatitis 
                    WHERE $condition ";

if (!empty($data['manifestCode'])) {
    $hepatitisQuery .= " AND data_sync=0 AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
    $hepatitisQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}


$hepatitisRemoteResult = $db->rawQuery($hepatitisQuery);
$data = array();
$counter = 0;

if ($db->count > 0) {
    $counter = $db->count;

    $sampleIds = array_column($hepatitisRemoteResult, 'hepatitis_id');

    $hepatitisObj = new \Vlsm\Models\Hepatitis();
    $comorbidities = $hepatitisObj->getComorbidityByHepatitisId($sampleIds);
    $risks = $hepatitisObj->getRiskFactorsByHepatitisId($sampleIds);


    $data['result'] = $hepatitisRemoteResult;
    $data['risks'] = $risks;
    $data['comorbidities'] = $comorbidities;


    /* $db->where('hepatitis_id', $sampleIds, 'IN')
        ->update('form_hepatitis', array('data_sync' => 1)); */

    $sql = 'UPDATE form_hepatitis SET data_sync = ?, form_attributes = JSON_SET(form_attributes, "$.remoteRequestsSync", ?) WHERE hepatitis_id IN "('. implode(',', $sampleIds) .'")';
    $db->rawQuery($sql, array(1, $general->getCurrentDateTime()));
}

$payload = json_encode($data);

$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'hepatitis', null, $origData, $payload, 'json', $labId);

$sql = 'UPDATE facility_details SET facility_attributes = JSON_SET(facility_attributes, "$.lastRequestsSync", ?) WHERE facility_id = ?';
$db->rawQuery($sql, array($general->getCurrentDateTime(), $labId));

echo $payload;
