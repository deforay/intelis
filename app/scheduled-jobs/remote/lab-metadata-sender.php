<?php

if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../../bootstrap.php");
}

//this file gets the data from the local database and updates the remote database
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$labId = $general->getSystemConfig('sc_testing_lab_id');
$version = VERSION;

// putting this into a variable to make this editable
$systemConfig = SYSTEM_CONFIG;

if (!isset($systemConfig['remoteURL']) || $systemConfig['remoteURL'] == '') {
    error_log("Please check if STS URL is set");
    exit(0);
}
try {
    // Checking if the network connection is available
    $remoteUrl = rtrim((string) $systemConfig['remoteURL'], "/");
    if ($apiService->checkConnectivity($remoteUrl . '/api/version.php?labId=' . $labId . '&version=' . $version) === false) {
        error_log("No network connectivity while trying remote sync.");
        return false;
    }

    $transactionId = $general->generateUUID();

    /* Lab Storage Sync Start */
    $url = $remoteUrl . '/remote/remote/lab-metadata-receiver.php';

    $payload = [
        "labId" => $labId,
        "labStorage" => $db->get('lab_storage'),
        "Key" => "vlsm-lab-data--",
    ];

    $jsonResponse = $apiService->post($url, $payload);
    $result = json_decode($jsonResponse, true);

    if (!empty($result)) {
        $db->where('storage_code', $result, 'IN');
        $id = $db->update('lab_storage', ['data_sync' => 1]);
    }
    /* Lab Storage Sync End */

    $instanceId = $general->getInstanceId();
    $db->where('vlsm_instance_id', $instanceId);
    $id = $db->update('s_vlsm_instance', ['last_remote_results_sync' => DateUtility::getCurrentDateTime()]);
} catch (Exception $exc) {
    error_log($db->getLastError());
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
