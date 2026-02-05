<?php

$cliMode = PHP_SAPI === 'cli';
if ($cliMode) {
    require_once(__DIR__ . "/../../bootstrap.php");
}

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$lastUpdate = null;
$output = [];
$transactionId = MiscUtility::generateULID();

try {

    $smartConnectURL = $general->getGlobalConfig('vldashboard_url');

    if (empty($smartConnectURL)) {
        echo "Smart Connect URL not set";
        exit(0);
    }

    $baseUrl = rtrim((string) $smartConnectURL, "/");
    $healthUrl = $baseUrl . "/api/health";

    if ($apiService->checkConnectivity($healthUrl) !== true) {
        LoggerUtility::log("error", "Unable to connect to Smart Connect health endpoint", [
            'file' => __FILE__,
            'line' => __LINE__,
            'url' => $healthUrl,
        ]);
        exit(0);
    }

    $url = $baseUrl . "/api/vlsm-covid19";

    $instanceUpdateOn = $db->getValue('s_vlsm_instance', 'covid19_last_dash_sync');

    if (!empty($instanceUpdateOn)) {
        $db->where('last_modified_datetime', $instanceUpdateOn, ">");
    }

    $db->orderBy("last_modified_datetime", "ASC");
    $rResult = $db->get('form_covid19', 5000);

    if (empty($rResult)) {
        exit(0);
    }

    $lastUpdate = max(array_column($rResult, 'last_modified_datetime'));
    $output['timestamp'] = empty($instanceUpdateOn) ? time() : strtotime((string) $instanceUpdateOn);
    $output['data'] = $rResult;

    $filename = MiscUtility::generateRandomString(12) . time() . '.json';
    $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
    fwrite($fp, json_encode($output));
    fclose($fp);




    $params = [
        [
            'name' => 'api-version',
            'contents' => 'v2'
        ],
        [
            'name' => 'source',
            'contents' => ($general->isSTSInstance()) ? 'STS' : 'LIS'
        ],
        [
            'name' => 'labId',
            'contents' => $general->getSystemConfig('sc_testing_lab_id') ?? null
        ]
    ];

    $response = $apiService->postFile($url, 'covid19File', TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params, true);
    $deResult = json_decode((string) $response, true);

    $general->addApiTracking(
        $transactionId,
        'vlsm-system',
        count($rResult),
        'smart-connect-covid19-sync',
        'covid19',
        $url,
        $output,
        $response,
        'json'
    );

    if (isset($deResult['status']) && trim((string) $deResult['status']) === 'success') {
        $data = ['covid19_last_dash_sync' => (empty($lastUpdate) ? DateUtility::getCurrentDateTime() : $lastUpdate)];
        $db->update('s_vlsm_instance', $data);
    }
    MiscUtility::deleteFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
} catch (Exception $exc) {
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $exc->getTraceAsString(),
    ]);
}
