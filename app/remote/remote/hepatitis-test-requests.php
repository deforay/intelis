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
    $trackId = $app->addApiTracking(null, count($hepatitisRemoteResult), 'requests', 'hepatitis', null, $labId, 'sync-api');
    // $forms = array();
    // foreach ($hepatitisRemoteResult as $row) {
    //     $forms[] = $row['hepatitis_id'];
    // }

    $sampleIds = array_column($hepatitisRemoteResult, 'hepatitis_id');

    $hepatitisObj = new \Vlsm\Models\Hepatitis();
    $comorbidities = $hepatitisObj->getComorbidityByHepatitisId($sampleIds);
    $risks = $hepatitisObj->getRiskFactorsByHepatitisId($sampleIds);


    $data['result'] = $hepatitisRemoteResult;
    $data['risks'] = $risks;
    $data['comorbidities'] = $comorbidities;


    $updata = array(
        'data_sync' => 1
    );
    $db->where('hepatitis_id', $sampleIds, 'IN');

    if (!$db->update('form_hepatitis', $updata))
        error_log('update failed: ' . $db->getLastError());
}

echo json_encode($data);