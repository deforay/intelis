<?php

$cliMode = php_sapi_name() === 'cli';

$forceFlag = false;
if ($cliMode) {
    require_once __DIR__ . "/../../../bootstrap.php";

    // Parse CLI arguments
    $options = getopt('f', ['force']);
    if (isset($options['f']) || isset($options['force'])) {
        $forceFlag = true;
    }
}

// this file gets the data from the local database and updates the remote database
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
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

// only for LIS instances
if ($general->isLISInstance() === false) {
    exit(0);
}

$labId = $general->getSystemConfig('sc_testing_lab_id');
$version = VERSION;

// putting this into a variable to make this editable
$systemConfig = SYSTEM_CONFIG;

$lastUpdatedOn = $db->getValue('s_vlsm_instance', 'last_lab_metadata_sync');

$remoteURL = $general->getRemoteURL();

if (empty($remoteURL)) {
    LoggerUtility::logError("Please check if STS URL is set");
    exit(0);
}

try {
    // Checking if the network connection is available
    if ($apiService->checkConnectivity("$remoteURL/api/version.php?labId=$labId&version=$version") === false) {
        LoggerUtility::logError("No network connectivity while trying remote sync.");
        return false;
    }

    $transactionId = MiscUtility::generateULID();

    $payload = [
        "labId" => $labId,
        "x-api-key" => MiscUtility::generateUUID(),
    ];

    $url = "$remoteURL/remote/remote/lab-metadata-receiver.php";

    $lastUpdatedOnCondition = "(updated_datetime > '$lastUpdatedOn' OR updated_datetime IS NULL)";

    // LAB STORAGE
    if ($forceFlag === false && !empty($lastUpdatedOn)) {
        $db->where($lastUpdatedOnCondition);
    }
    $labStorage = $db->get('lab_storage');
    if (!empty($labStorage)) {
        $payload["labStorage"] = $labStorage;
    }

    // LAB STORAGE HISTORY
    if ($forceFlag === false && !empty($lastUpdatedOn)) {
        $db->where($lastUpdatedOnCondition);
    }
    $labStorageHistory = $db->get('lab_storage_history');
    if (!empty($labStorageHistory)) {
        $payload["labStorageHistory"] = $labStorageHistory;
    }

    // // PATIENTS
    // if ($forceFlag === false && !empty($lastUpdatedOn)) {
    //     $db->where($lastUpdatedOnCondition);
    // }
    // $patients = $db->get('patients');
    // if (!empty($patients)) {
    //     $payload["patients"] = $patients;
    // }

    // INSTRUMENTS
    if ($forceFlag === false && !empty($lastUpdatedOn)) {
        $db->where($lastUpdatedOnCondition);
    }
    $instruments = $db->get('instruments');
    if (!empty($instruments)) {
        $payload["instruments"] = $instruments;
    }

    // INSTRUMENT MACHINES
    if ($forceFlag === false && !empty($lastUpdatedOn)) {
        $db->where($lastUpdatedOnCondition);
    }
    $instrumentMachines = $db->get('instrument_machines');
    if (!empty($instrumentMachines)) {
        $payload["instrumentMachines"] = $instrumentMachines;
    }

    // INSTRUMENT CONTROLS
    if ($forceFlag === false && !empty($lastUpdatedOn)) {
        $db->where($lastUpdatedOnCondition);
    }
    $instrumentControls = $db->get('instrument_controls');
    if (!empty($instrumentControls)) {
        $payload["instrumentControls"] = $instrumentControls;
    }

    // USERS
    if ($forceFlag === false && !empty($lastUpdatedOn)) {
        $db->where($lastUpdatedOnCondition);
    }
    $db->where("login_id IS NOT NULL");
    $db->where("status like 'active'");
    $users = $db->get('user_details');

    // Add signature images to the users payload
    $signatureImagePathBase = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature";
    MiscUtility::makeDirectory($signatureImagePathBase);
    $signatureImagePathBase = realpath($signatureImagePathBase);

    if (!empty($users)) {
        foreach ($users as &$user) {
            $signatureImagePath = isset($user['user_signature']) ? $signatureImagePathBase . DIRECTORY_SEPARATOR . $user['user_signature'] : null;
            if ($signatureImagePath && MiscUtility::isImageValid($signatureImagePath)) {
                $user['signature_image_content'] = base64_encode(file_get_contents($signatureImagePath));
                $user['signature_image_filename'] = $user['user_signature'];
            } else {
                // Handle cases where the image doesn't exist
                $user['signature_image_content'] = null;
                $user['signature_image_filename'] = null;
            }

            // Unset unnecessary fields
            foreach (['login_id', 'password', 'role_id', 'status'] as $key) {
                unset($user[$key]);
            }
        }
        $payload["users"] = $users;
    }

    $jsonResponse = $apiService->post($url, $payload, gzip: true);
    $instanceId = $general->getInstanceId();
    $db->where('vlsm_instance_id', $instanceId);
    $id = $db->update('s_vlsm_instance', [
        'last_lab_metadata_sync' => DateUtility::getCurrentDateTime()
    ]);
} catch (Exception $exc) {
    LoggerUtility::log("error", __FILE__ . ":" . $exc->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'trace' => $exc->getTraceAsString(),
    ]);
}
