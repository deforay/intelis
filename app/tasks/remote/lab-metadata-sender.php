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

$output = MiscUtility::console();

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

$output->writeln("<info>Lab Metadata Sync</info>");
if ($forceFlag) {
    $output->writeln("<comment>Force sync enabled — ignoring last sync time</comment>");
}

if (empty($remoteURL)) {
    $output->writeln("<error>STS URL is not set</error>");
    LoggerUtility::logError("Please check if STS URL is set");
    exit(0);
}

try {
    // Checking if the network connection is available
    $output->write("Checking connectivity... ");
    if (false == CommonService::validateStsUrl($remoteURL, $labId)) {
        $output->writeln("<error>FAILED</error>");
        LoggerUtility::logError("No network connectivity while trying remote sync.");
        return false;
    }
    $output->writeln("<info>OK</info>");

    $transactionId = MiscUtility::generateULID();

    $payload = [
        "labId" => $labId,
        "x-api-key" => MiscUtility::generateUUID(),
    ];

    $url = "$remoteURL/remote/remote/lab-metadata-receiver.php";

    $lastUpdatedOnCondition = "(updated_datetime > '$lastUpdatedOn' OR updated_datetime IS NULL)";

    $metadataTables = [
        'lab_storage' => 'labStorage',
        'lab_storage_history' => 'labStorageHistory',
        'instruments' => 'instruments',
        'instrument_machines' => 'instrumentMachines',
        'instrument_controls' => 'instrumentControls',
    ];

    // +1 for users table
    $bar = MiscUtility::spinnerStart(count($metadataTables) + 1, 'Collecting table data…');

    foreach ($metadataTables as $table => $payloadKey) {
        if ($forceFlag === false && !empty($lastUpdatedOn)) {
            $db->where($lastUpdatedOnCondition);
        }
        $records = $db->get($table);
        if (!empty($records)) {
            $payload[$payloadKey] = $records;
        }
        MiscUtility::spinnerAdvance($bar);
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
    MiscUtility::spinnerAdvance($bar);
    MiscUtility::spinnerFinish($bar);

    $output->write("Uploading to STS... ");
    $jsonResponse = $apiService->post($url, $payload, gzip: true);
    $output->writeln("<info>OK</info>");

    $instanceId = $general->getInstanceId();
    $db->where('vlsm_instance_id', $instanceId);
    $id = $db->update('s_vlsm_instance', [
        'last_lab_metadata_sync' => DateUtility::getCurrentDateTime()
    ]);

    $output->writeln("<info>Sync complete.</info>");
} catch (Exception $exc) {
    $output->writeln("<error>Error: " . $exc->getMessage() . "</error>");
    LoggerUtility::log("error", __FILE__ . ":" . $exc->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'trace' => $exc->getTraceAsString(),
    ]);
}
