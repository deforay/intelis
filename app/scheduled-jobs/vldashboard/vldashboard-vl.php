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
use PhpOffice\PhpSpreadsheet\Calculation\DateTimeExcel\Date;

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

    // $db->orderBy("last_modified_datetime", "ASC");

    // $rResult = $db->get('form_vl', 10000);

    $sql = "SELECT *,
            CONCAT(COALESCE(NULLIF(sample_code, ''), ''), COALESCE(NULLIF(remote_sample_code, ''), '')) AS concatenated_code
            FROM form_vl
            WHERE (sample_code IS NOT NULL AND sample_code != '') OR
                (remote_sample_code IS NOT NULL AND remote_sample_code != '')
            ORDER BY last_modified_datetime ASC
            LIMIT 10000";

    $rResult = $db->rawQuery($sql);

    if (empty($rResult)) {
        die('No data found');
    }

    $lastUpdate = $rResult[count($rResult) - 1]['last_modified_datetime'];

    $output['timestamp'] = !empty($instanceUpdateOn) ? strtotime((string) $instanceUpdateOn) : time();
    foreach ($rResult as $aRow) {
        $aRow['sample_code'] = $aRow['concatenated_code'];
        $output['data'][] = $aRow;
    }

    $currentDate = DateUtility::getCurrentDateTime();

    $filename = 'export-vl-result-' . $currentDate . '.json';
    $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
    fwrite($fp, json_encode($output));
    fclose($fp);

    $vldashboardUrl = $general->getGlobalConfig('vldashboard_url');

    if (empty($vldashboardUrl)) {
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
            'contents' => ($general->getSystemConfig('sc_user_type') == 'remoteuser') ? 'STS' : 'LIS'
        ],
        [
            'name' => 'labId',
            'contents' => $general->getSystemConfig('sc_testing_lab_id') ?? null
        ]
    ];

    $response  = $apiService->postFile($url, 'vlFile', TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params);

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
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
