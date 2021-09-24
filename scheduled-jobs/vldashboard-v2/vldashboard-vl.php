<?php

ini_set('memory_limit', -1);

require_once(__DIR__ . "/../../startup.php");




$general = new \Vlsm\Models\General($db);
$lastUpdate = null;
$output = array();

$suppressionLimit = 1000;

$suppressedArray = array(
    'target not detected',
    'tnd',
    'not detected',
    'below detection limit',
    'below detection level',
    'bdl',
    'suppressed',
    'negative',
    'negat'
);

try {

    $instanceUpdateOn = $db->getValue('s_vlsm_instance', 'vl_last_dash_sync');
    
    if (!empty($instanceUpdateOn)) {
        $db->where('last_modified_datetime', $instanceUpdateOn, ">");
    }

    $db->orderBy("last_modified_datetime", "ASC");

    $rResult = $db->get('vl_request_form', 500);

    if (empty($rResult)) {
        exit(0);
    }

    $lastUpdate = $rResult[count($rResult) - 1]['last_modified_datetime'];

    $output['timestamp'] = !empty($instanceUpdateOn) ? strtotime($instanceUpdateOn) : time();
    foreach ($rResult as $aRow) {

        if ($aRow['result'] == NULL || empty($aRow['result'])) {
            $aRow['DashVL_Abs'] = NULL;
            $aRow['DashVL_AnalysisResult'] = NULL;
        } else if (is_numeric($aRow['result']) && $aRow['result'] > 0 && $aRow['result'] == round($aRow['result'], 0)) {
            $aRow['result'] = (float)filter_var($aRow['result'], FILTER_SANITIZE_NUMBER_FLOAT);

            if ($aRow['result'] < $suppressionLimit) {
                $aRow['DashVL_AnalysisResult'] = 'Suppressed';
                $aRow['DashVL_Abs'] = $aRow['result'];
            } else if ($aRow['result'] >= $suppressionLimit) {
                $aRow['DashVL_AnalysisResult'] = 'Not Suppressed';
                $aRow['DashVL_Abs'] = $aRow['result'];
            }
        } else {

            $textResult = NULL;

            if (in_array(strtolower($aRow['result']), $suppressedArray) || in_array(strtolower($aRow['result_value_text']), $suppressedArray)) {
                $textResult = 20;
            } else {
                $textResult = (float)filter_var($aRow['result_value_text'], FILTER_SANITIZE_NUMBER_FLOAT);
            }

            if ($textResult == 'NULL' || empty($textResult)) {
                $aRow['DashVL_Abs'] = NULL;
                $aRow['DashVL_AnalysisResult'] = NULL;
            } else if ($textResult < $suppressionLimit) {
                $aRow['DashVL_AnalysisResult'] = 'Suppressed';
                $aRow['DashVL_Abs'] = $textResult;
            } else if ($textResult >= $suppressionLimit) {
                $aRow['DashVL_AnalysisResult'] = 'Not Suppressed';
                $aRow['DashVL_Abs'] = $textResult;
            }
        }
        $output['data'][] = $aRow;
    }

    $currentDate = date('d-m-y-h-i-s');
    // echo "<pre>";print_r($output);die;

    $filename = 'export-vl-result-' . $currentDate . '.json';
    $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
    fwrite($fp, json_encode($output));
    fclose($fp);


    $vldashboardUrl = $general->getGlobalConfig('vldashboard_url');
    $vldashboardUrl = rtrim($vldashboardUrl, "/");


    //$vldashboardUrl = "http://vldashboard";

    $apiUrl = $vldashboardUrl . "/api/vlsm";
    //error_log($apiUrl);
    //$apiUrl.="?key_identity=XXX&key_credential=YYY";


    $data = [];
    $data['api-version'] = 'v2';
    $data['vlFile'] = new CURLFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'application/json', $filename);

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
            'vl_last_dash_sync' => (!empty($lastUpdate) ? $lastUpdate : $general->getDateTime())
        );

        $db->update('s_vlsm_instance', $data);
    }
    $general->removeDirectory(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
