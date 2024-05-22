<?php

$cliMode = php_sapi_name() === 'cli';
if ($cliMode) {
    require_once(__DIR__ . "/../../../bootstrap.php");
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

try {

    $instanceUpdateOn = $db->getValue('s_vlsm_instance', 'vl_last_dash_sync');

    if (!empty($instanceUpdateOn)) {
        $db->where('last_modified_datetime', $instanceUpdateOn, ">");
    }

    $db->orderBy("last_modified_datetime", "ASC");
    $rResult = $db->get('form_vl', 5000);

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

    $vldashboardUrl = $general->getGlobalConfig('vldashboard_url');

    if (empty($vldashboardUrl)) {
        echo "VL Dashboard URL not set";
        exit(0);
    }

    $url = rtrim((string) $vldashboardUrl, "/") . "/api/vlsm";

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

    $response  = $apiService->postFile($url, 'vlFile', TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params, true);

    $deResult = json_decode($response, true);

    if (isset($deResult['status']) && trim((string) $deResult['status']) == 'success') {
        $data = array(
            'vl_last_dash_sync' => (!empty($lastUpdate) ? $lastUpdate : DateUtility::getCurrentDateTime())
        );

        $db->update('s_vlsm_instance', $data);
    }
    MiscUtility::removeDirectory(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    exit(0);
} catch (Exception $exc) {
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $exc->getTraceAsString(),
    ]);
}
