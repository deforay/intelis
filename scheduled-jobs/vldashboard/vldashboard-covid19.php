<?php

use App\Models\General;
use App\Utilities\DateUtils;

ini_set('memory_limit', -1);

require_once(__DIR__ . "/../../bootstrap.php");



$general = new General();
$lastUpdate = null;
$output = array();

try {
    
    $instanceUpdateOn = $db->getValue('s_vlsm_instance', 'covid19_last_dash_sync');
    
    if (!empty($instanceUpdateOn)) {
        $db->where('last_modified_datetime', $instanceUpdateOn, ">");
    }
    
    $db->orderBy("last_modified_datetime", "ASC");
    
    $rResult = $db->get('form_covid19', 5000);

    if (empty($rResult)) {
        exit(0);
    }

    $lastUpdate = $rResult[count($rResult) - 1]['last_modified_datetime'];
    $output['timestamp'] = strtotime($instanceUpdateOn);
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


    $filename = 'export-covid19-result-' . $currentDate . '.json';
    $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
    fwrite($fp, json_encode($output));
    fclose($fp);


    $vldashboardUrl = $general->getGlobalConfig('vldashboard_url');
    $vldashboardUrl = rtrim($vldashboardUrl, "/");


    //$vldashboardUrl = "http://vldashboard";

    $apiUrl = $vldashboardUrl . "/api/vlsm-covid19";
    //error_log($apiUrl);
    //$apiUrl.="?key_identity=XXX&key_credential=YYY";


    $data = [];
    $data['api-version'] = 'v2';
    $data['covid19File'] = new CURLFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'application/json', $filename);

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

    // echo "<pre>";print_r($result);die;
    $deResult = json_decode($result, true);

    if (isset($deResult['status']) && trim($deResult['status']) == 'success') {
        $data = array(
            'covid19_last_dash_sync' => (!empty($lastUpdate) ? $lastUpdate : DateUtils::getCurrentDateTime())
        );
        $db->update('s_vlsm_instance', $data);
    }
    $general->removeDirectory(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
