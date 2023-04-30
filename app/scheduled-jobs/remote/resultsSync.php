<?php

if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../../bootstrap.php");
}

//this file gets the data from the local database and updates the remote database

use App\Services\ApiService;
use App\Services\Covid19Service;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$app = new ApiService();

$labId = $general->getSystemConfig('sc_testing_lab_id');
$version = VERSION;

// putting this into a variable to make this editable
$systemConfig = SYSTEM_CONFIG;

if (!isset($systemConfig['remoteURL']) || $systemConfig['remoteURL'] == '') {
    error_log("Please check if Remote URL is set");
    exit(0);
}
try {
    // Checking if the network connection is available
    $remoteUrl = rtrim($systemConfig['remoteURL'], "/");
    $headers = @get_headers($remoteUrl . '/api/version.php?labId=' . $labId . '&version=' . $version);
    if (strpos($headers[0], '200') === false) {
        error_log("No network connectivity while trying remote sync.");
        return false;
    }

    $transactionId = $general->generateUUID();

    $forceSyncModule = !empty($_GET['forceSyncModule']) ? $_GET['forceSyncModule'] : null;
    $sampleCode = !empty($_GET['sampleCode']) ? $_GET['sampleCode'] : null;

    // if only one module is getting synced, lets only sync that one module
    if (!empty($forceSyncModule)) {
        unset($systemConfig['modules']);
        $systemConfig['modules'][$forceSyncModule] = true;
    }

    // VIRAL LOAD TEST RESULTS
    if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] === true) {
        $vlQuery = "SELECT vl.*, a.user_name as 'approved_by_name' 
            FROM `form_vl` AS vl 
            LEFT JOIN `user_details` AS a ON vl.result_approved_by = a.user_id 
            WHERE result_status NOT IN (9) 
            AND (facility_id != '' AND facility_id is not null) 
            AND (sample_code !='' AND sample_code is not null) 
            AND vl.data_sync = 0";
        // AND `last_modified_datetime` > SUBDATE( NOW(), INTERVAL ". $arr['data_sync_interval']." HOUR)";
        // echo $vlQuery;die;

        if (!empty($forceSyncModule) && trim($forceSyncModule) == "vl" && !empty($sampleCode) && trim($sampleCode) != "") {
            $vlQuery .= " AND sample_code like '$sampleCode'";
        }

        $vlLabResult = $db->rawQuery($vlQuery);

        $url = $remoteUrl . '/remote/remote/testResults.php';

        $data = array(
            "labId" => $labId,
            "result" => $vlLabResult,
            "Key" => "vlsm-lab-data--",
        );

        //open connection
        $ch = curl_init($url);
        $json_data = json_encode($data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data)
            )
        );
        // execute post
        $curl_response = curl_exec($ch);
        //close connection
        $result = json_decode($curl_response, true);

        if (!empty($result) && count($result) > 0) {
            $db = $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_vl', array('data_sync' => 1, 'result_sent_to_source' => 'sent'));
        }

        $general->addApiTracking($transactionId, 'vlsm-system', count($vlLabResult), 'send-results', 'vl', $url, $json_data, $result, 'json', $labId);
    }


    // EID TEST RESULTS
    if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] === true) {
        $eidQuery = "SELECT vl.*, a.user_name as 'approved_by_name' 
                    FROM `form_eid` AS vl 
                    LEFT JOIN `user_details` AS a ON vl.result_approved_by = a.user_id 
                    WHERE result_status NOT IN (9) 
                    AND sample_code !='' 
                    AND sample_code is not null 
                    AND vl.data_sync=0"; // AND `last_modified_datetime` > SUBDATE( NOW(), INTERVAL ". $arr['data_sync_interval']." HOUR)";

        if (!empty($forceSyncModule) && trim($forceSyncModule) == "eid" && !empty($sampleCode) && trim($sampleCode) != "") {
            $eidQuery .= " AND sample_code like '$sampleCode'";
        }
        $eidLabResult = $db->rawQuery($eidQuery);

        $url = $remoteUrl . '/remote/remote/eid-test-results.php';
        $data = array(
            "labId" => $labId,
            "result" => $eidLabResult,
            "Key" => "vlsm-lab-data--",
        );
        //open connection
        $ch = curl_init($url);
        $json_data = json_encode($data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data)
            )
        );
        // execute post
        $curl_response = curl_exec($ch);
        //close connection
        curl_close($ch);
        $result = json_decode($curl_response, true);

        if (!empty($result) && count($result) > 0) {
            $db = $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_eid', array('data_sync' => 1, 'result_sent_to_source' => 'sent'));
        }

        $general->addApiTracking($transactionId, 'vlsm-system', count($eidLabResult), 'send-results', 'eid', $url, $json_data, $result, 'json', $labId);
    }



    // COVID-19 TEST RESULTS
    if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] === true) {

        $covid19Query = "SELECT c19.*, a.user_name as 'approved_by_name' 
                    FROM `form_covid19` AS c19 
                    LEFT JOIN `user_details` AS a ON c19.result_approved_by = a.user_id 
                    WHERE result_status NOT IN (9) 
                    AND sample_code !='' 
                    AND sample_code is not null 
                    AND c19.data_sync=0"; // AND `last_modified_datetime` > SUBDATE( NOW(), INTERVAL ". $arr['data_sync_interval']." HOUR)";

        if (!empty($forceSyncModule) && trim($forceSyncModule) == "covid19" && !empty($sampleCode) && trim($sampleCode) != "") {
            $covid19Query .= " AND sample_code like '$sampleCode'";
        }
        $c19LabResult = $db->rawQuery($covid19Query);

        $forms = array_column($c19LabResult, 'covid19_id');

        
/** @var Covid19Service $covid19Service */
$covid19Service = \App\Registries\ContainerRegistry::get(Covid19Service::class);
        $symptoms = $covid19Service->getCovid19SymptomsByFormId($forms);
        $comorbidities = $covid19Service->getCovid19ComorbiditiesByFormId($forms);
        $testResults = $covid19Service->getCovid19TestsByFormId($forms);

        $url = $remoteUrl . '/remote/remote/covid-19-test-results.php';
        $data = array(
            "labId" => $labId,
            "result" => $c19LabResult,
            "testResults" => $testResults,
            "symptoms" => $symptoms,
            "comorbidities" => $comorbidities,
            "Key" => "vlsm-lab-data--",
        );
        //open connection
        $ch = curl_init($url);
        $json_data = json_encode($data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data)
            )
        );
        // execute post
        $curl_response = curl_exec($ch);
        //close connection
        curl_close($ch);
        $result = json_decode($curl_response, true);

        if (!empty($result) && count($result) > 0) {
            $db = $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_covid19',  array('data_sync' => 1, 'result_sent_to_source' => 'sent'));
        }

        $general->addApiTracking($transactionId, 'vlsm-system', count($c19LabResult), 'send-results', 'covid19', $url, $json_data, $result, 'json', $labId);
    }

    // Hepatitis TEST RESULTS

    if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] === true) {

        $hepQuery = "SELECT hep.*, a.user_name as 'approved_by_name' 
                    FROM `form_hepatitis` AS hep 
                    LEFT JOIN `user_details` AS a ON hep.result_approved_by = a.user_id 
                    WHERE result_status NOT IN (9) 
                    AND sample_code !='' 
                    AND sample_code is not null 
                    AND hep.data_sync=0"; // AND `last_modified_datetime` > SUBDATE( NOW(), INTERVAL ". $arr['data_sync_interval']." HOUR)";
        if (!empty($forceSyncModule) && trim($forceSyncModule) == "hepatitis" && !empty($sampleCode) && trim($sampleCode) != "") {
            $hepQuery .= " AND sample_code like '$sampleCode'";
        }
        $hepLabResult = $db->rawQuery($hepQuery);

        // $forms = array_column($hepLabResult, 'hepatitis_id');

        // $hepatitisObj = new \App\Services\Hepatitis();
        // $risks = $hepatitisObj->getRiskFactorsByHepatitisId($forms);
        // $comorbidities = $hepatitisObj->getComorbidityByHepatitisId($forms);

        $url = $remoteUrl . '/remote/remote/hepatitis-test-results.php';
        $data = array(
            "labId" => $labId,
            "result" => $hepLabResult,
            "Key" => "vlsm-lab-data--",
        );

        //open connection
        $ch = curl_init($url);
        $json_data = json_encode($data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_data)
            )
        );
        // execute post
        $curl_response = curl_exec($ch);
        //close connection
        curl_close($ch);
        $result = json_decode($curl_response, true);

        if (!empty($result) && count($result) > 0) {
            $db = $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_hepatitis',  array('data_sync' => 1, 'result_sent_to_source' => 'sent'));
        }

        $general->addApiTracking($transactionId, 'vlsm-system', count($hepLabResult), 'send-results', 'hepatitis', $url, $json_data, $result, 'json', $labId);
    }
    /* Get instance id for update last_remote_results_sync */
    $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

    /* Update last_remote_results_sync in s_vlsm_instance */
    $db = $db->where('vlsm_instance_id', $instanceResult['vlsm_instance_id']);
    $id = $db->update('s_vlsm_instance', array('last_remote_results_sync' => DateUtility::getCurrentDateTime()));
} catch (Exception $exc) {
    error_log($db->getLastError());
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
