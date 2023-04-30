<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

ini_set('memory_limit', -1);

require_once(__DIR__ . "/../../bootstrap.php");




/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$lastUpdate = null;
$output = [];

try {

    $instanceUpdateOn = $db->getValue('s_vlsm_instance', 'eid_last_dash_sync');

    if (!empty($instanceUpdateOn)) {
        $db->where('last_modified_datetime', $instanceUpdateOn, ">");
    }

    $db->orderBy("last_modified_datetime", "ASC");

    $rResult = $db->get('form_eid', 5000);

    if (empty($rResult)) {
        exit(0);
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


    $filename = 'export-eid-result-' . $currentDate . '.json';
    $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
    fwrite($fp, json_encode($output));
    fclose($fp);


    $vldashboardUrl = $general->getGlobalConfig('vldashboard_url');
    $vldashboardUrl = rtrim($vldashboardUrl, "/");


    //$vldashboardUrl = "http://vldashboard";

    $apiUrl = $vldashboardUrl . "/api/vlsm-eid";
    //error_log($apiUrl);
    //$apiUrl.="?key_identity=XXX&key_credential=YYY";


    $data = [];
    $data['api-version'] = 'v2';
    $data['eidFile'] = new CURLFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'application/json', $filename);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data']
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    curl_close($ch);

    $deResult = json_decode($result, true);

    if (isset($deResult['status']) && trim($deResult['status']) == 'success') {
        $data = array(
            'eid_last_dash_sync' => (!empty($lastUpdate) ? $lastUpdate : DateUtility::getCurrentDateTime())
        );
        
        $db->update('s_vlsm_instance', $data);
    }
    $general->removeDirectory(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
