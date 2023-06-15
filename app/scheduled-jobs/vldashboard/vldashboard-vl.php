<?php

if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../../bootstrap.php");
}


use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

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

    $rResult = $db->get('form_vl', 10000);


    if (empty($rResult)) {
        die('No data found');
    }


    $lastUpdate = $rResult[count($rResult) - 1]['last_modified_datetime'];

    $output['timestamp'] = !empty($instanceUpdateOn) ? strtotime($instanceUpdateOn) : time();
    foreach ($rResult as $aRow) {

        if (!empty($aRow['remote_sample_code'])) {
            if (!empty($aRow['sample_code'])) {
                $aRow['sample_code']      = $aRow['remote_sample_code'] . '-' . $aRow['sample_code'];
            } else {
                $aRow['sample_code']      = $aRow['remote_sample_code'];
            }
        }

        $output['data'][] = $aRow;
    }

    $currentDate = date('d-m-y-h-i-s');

    $filename = 'export-vl-result-' . $currentDate . '.json';
    $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
    fwrite($fp, json_encode($output));
    fclose($fp);

    $vldashboardUrl = $general->getGlobalConfig('vldashboard_url');

    if (empty($vldashboardUrl)) {
        exit(0);
    }

    $vldashboardUrl = rtrim($vldashboardUrl, "/");

    $url = $vldashboardUrl . "/api/vlsm";

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

    $response  = $apiService->postFile($url, $filename, TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params);


    $deResult = json_decode($response->getBody(), true);

    if (isset($deResult['status']) && trim($deResult['status']) == 'success') {
        $data = array(
            'vl_last_dash_sync' => (!empty($lastUpdate) ? $lastUpdate : DateUtility::getCurrentDateTime())
        );

        $db->update('s_vlsm_instance', $data);
    }
    $general->removeDirectory(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    exit(0);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
