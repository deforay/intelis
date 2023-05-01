<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\HepatitisService;
use App\Utilities\DateUtility;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");
header('Content-Type: application/json');

$origData = $jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);


$payload = [];

$labId = $data['labName'] ?: $data['labId'] ?: null;

if (empty($labId)) {
    exit(0);
}

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$dataSyncInterval = $general->getGlobalConfig('data_sync_interval');
$dataSyncInterval = (isset($dataSyncInterval) && !empty($dataSyncInterval)) ? $dataSyncInterval : 30;
$transactionId = $general->generateUUID();

$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$fMapResult = $facilitiesService->getTestingLabFacilityMap($labId);

if (!empty($fMapResult)) {
    $condition = "(lab_id =" . $labId . " OR facility_id IN (" . $fMapResult . "))";
} else {
    $condition = "lab_id =" . $labId;
}

$hepatitisQuery = "SELECT * FROM form_hepatitis
                    WHERE $condition ";

if (!empty($data['manifestCode'])) {
    //$hepatitisQuery .= " AND data_sync=0 AND sample_package_code like '" . $data['manifestCode'] . "%'";
    $hepatitisQuery .= " AND sample_package_code like '" . $data['manifestCode'] . "%'";
} else {
    $hepatitisQuery .= " AND data_sync=0 AND last_modified_datetime > SUBDATE( NOW(), INTERVAL $dataSyncInterval DAY)";
}


$hepatitisRemoteResult = $db->rawQuery($hepatitisQuery);
$data = [];
$counter = 0;

$sampleIds = $facilityIds = [];
if ($db->count > 0) {
    $counter = $db->count;

    $sampleIds = array_column($hepatitisRemoteResult, 'hepatitis_id');
    $facilityIds = array_column($hepatitisRemoteResult, 'facility_id');

    
/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);
    $comorbidities = $hepatitisService->getComorbidityByHepatitisId($sampleIds);
    $risks = $hepatitisService->getRiskFactorsByHepatitisId($sampleIds);


    $data['result'] = $hepatitisRemoteResult ?? [];
    $data['risks'] = $risks ?? [];
    $data['comorbidities'] = $comorbidities ?? [];
}

$payload = json_encode($data);

$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'requests', 'hepatitis', $_SERVER['REQUEST_URI'], $origData, $payload, 'json', $labId);


$currentDateTime = DateUtility::getCurrentDateTime();
if (!empty($sampleIds)) {
    $sql = 'UPDATE form_hepatitis SET data_sync = ?,
                form_attributes = JSON_SET(COALESCE(form_attributes, "{}"), "$.remoteRequestsSync", ?, "$.requestSyncTransactionId", ?)
                WHERE hepatitis_id IN (' . implode(",", $sampleIds) . ')';
    $db->rawQuery($sql, array(1, $currentDateTime, $transactionId));
}

if (!empty($facilityIds)) {
    $facilityIds = array_unique(array_filter($facilityIds));
    $sql = 'UPDATE facility_details
                SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.remoteRequestsSync", ?, "$.hepatitisRemoteRequestsSync", ?) 
                WHERE facility_id IN (' . implode(",", $facilityIds) . ')';
    $db->rawQuery($sql, array($currentDateTime, $currentDateTime));
}

// Whether any data got synced or not, we will update sync datetime for the lab
$sql = 'UPDATE facility_details
          SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.lastRequestsSync", ?, "$.hepatitisLastRequestsSync", ?) 
          WHERE facility_id = ?';
$db->rawQuery($sql, array($currentDateTime, $currentDateTime, $labId));

echo $payload;
