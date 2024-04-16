<?php

if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../../bootstrap.php");
}

use App\Services\ApiService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
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

try {

    $vldashboardUrl = $general->getGlobalConfig('vldashboard_url');

    if (empty($vldashboardUrl)) {
        echo "VL Dashboard URL not set";
        exit(0);
    }

    $url = rtrim((string) $vldashboardUrl, "/") . "/api/vlsm-covid19";

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
    $output['timestamp'] = !empty($instanceUpdateOn) ? strtotime((string) $instanceUpdateOn) : time();
    $output['data'] = $rResult;

    $filename = $general->generateRandomString(12) . time() . '.json';
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
            'contents' => ($general->getSystemConfig('sc_user_type') == 'remoteuser') ? 'STS' : 'LIS'
        ],
        [
            'name' => 'labId',
            'contents' => $general->getSystemConfig('sc_testing_lab_id') ?? null
        ]
    ];

    $response  = $apiService->postFile($url, 'covid19File', TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params, true);
    $deResult = json_decode($response, true);

    if (isset($deResult['status']) && trim((string) $deResult['status']) == 'success') {
        $data = array(
            'covid19_last_dash_sync' => (!empty($lastUpdate) ? $lastUpdate : DateUtility::getCurrentDateTime())
        );
        $db->update('s_vlsm_instance', $data);
    }
    MiscUtility::removeDirectory(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
