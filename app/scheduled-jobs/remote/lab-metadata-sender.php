<?php

if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../../bootstrap.php");
}

//this file gets the data from the local database and updates the remote database
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
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

$lastUpdatedOn = $db->getValue('s_vlsm_instance', 'last_lab_metadata_sync');
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

    $payload = [
        "transactionId" => $transactionId,
        "labId" => $labId,
        "x-api-key" => $general->generateUUID(),
    ];

    $url = $remoteUrl . '/remote/remote/lab-metadata-receiver.php';

    // LAB STORAGE
    if (!empty($lastUpdatedOn)) {
        $db = $db->where(' (updated_datetime > "' . $lastUpdatedOn . '" OR updated_datetime IS NULL)');
    }
    $labStorage = $db->get('lab_storage');
    if (!empty($labStorage)) {
        $payload["labStorage"] = $labStorage;
    }

    // PATIENTS
    if (!empty($lastUpdatedOn)) {
        $db = $db->where(' (updated_datetime > "' . $lastUpdatedOn . '" OR updated_datetime IS NULL)');
    }
    $patients = $db->get('patients');

    if (!empty($patients)) {
        $payload["patients"] = $patients;
    }

    // INSTRUMENTS
    if (!empty($lastUpdatedOn)) {
        $db = $db->where(' (updated_datetime > "' . $lastUpdatedOn . '" OR updated_datetime IS NULL)');
    }
    $instruments = $db->get('instruments');
    if (!empty($instruments)) {
        $payload["instruments"] = $instruments;
    }

    // INSTRUMENT MACHINES
    if (!empty($lastUpdatedOn)) {
        $db = $db->where(' (updated_datetime > "' . $lastUpdatedOn . '" OR updated_datetime IS NULL)');
    }
    $instrumentMachines = $db->get('instrument_machines');

    if (!empty($instrumentMachines)) {
        $payload["instrumentMachines"] = $instrumentMachines;
    }

    // INSTRUMENT CONTROLS
    if (!empty($lastUpdatedOn)) {
        $db = $db->where(' (updated_datetime > "' . $lastUpdatedOn . '" OR updated_datetime IS NULL)');
    }
    $instrumentControls = $db->get('instrument_controls');

    if (!empty($instrumentControls)) {
        $payload["instrumentControls"] = $instrumentControls;
    }
    $jsonResponse = $apiService->post($url, $payload);
    $instanceId = $general->getInstanceId();
    $db->where('vlsm_instance_id', $instanceId);
    $id = $db->update('s_vlsm_instance', ['last_lab_metadata_sync' => DateUtility::getCurrentDateTime()]);
} catch (Exception $exc) {
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $exc->getTraceAsString(),
    ]);
}
