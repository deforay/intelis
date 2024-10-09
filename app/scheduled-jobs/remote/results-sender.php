<?php

$cliMode = php_sapi_name() === 'cli';
if ($cliMode) {
    require_once __DIR__ . "/../../../bootstrap.php";
    echo "=========================" . PHP_EOL;
    echo "Starting results sending" . PHP_EOL;
}

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

//this file gets the data from the local database and updates the remote database
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$labId = $general->getSystemConfig('sc_testing_lab_id');
$version = VERSION;

// putting this into a variable to make this editable
$systemConfig = SYSTEM_CONFIG;

$remoteURL = $general->getRemoteURL();

if (empty($remoteURL)) {
    LoggerUtility::log('error', "Please check if STS URL is set");
    exit(0);
}
try {
    // Checking if the network connection is available
    if ($apiService->checkConnectivity("$remoteURL/api/version.php?labId=$labId&version=$version") === false) {
        LoggerUtility::log('error', "No network connectivity while trying remote sync.");
        return false;
    }

    $transactionId = MiscUtility::generateULID();

    $forceSyncModule = !empty($_GET['forceSyncModule']) ? $_GET['forceSyncModule'] : null;
    $sampleCode = !empty($_GET['sampleCode']) ? $_GET['sampleCode'] : null;

    // if only one module is getting synced, lets only sync that one module
    if (!empty($forceSyncModule)) {
        unset($systemConfig['modules']);
        $systemConfig['modules'][$forceSyncModule] = true;
    }

    // GERNERIC TEST RESULTS
    if (isset($systemConfig['modules']['generic-tests']) && $systemConfig['modules']['generic-tests'] === true) {
        if ($cliMode) {
            echo "Trying to send test results from Custom Tests...\n";
        }

        $genericQuery = "SELECT generic.*, a.user_name as 'approved_by_name'
                    FROM `form_generic` AS generic
                    LEFT JOIN `user_details` AS a ON generic.result_approved_by = a.user_id
                    WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                    AND sample_code !=''
                    AND sample_code is not null
                    AND generic.data_sync=0";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "generic-tests" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $genericQuery .= " AND sample_code like '$sampleCode'";
        }
        $genericLabResult = $db->rawQuery($genericQuery);

        $forms = array_column($genericLabResult, 'sample_id');


        /** @var GenericTestsService $genericService */
        $genericService = ContainerRegistry::get(GenericTestsService::class);

        $customTestResultData = [];
        foreach ($genericLabResult as $r) {
            $customTestResultData[$r['unique_id']] = [];
            $customTestResultData[$r['unique_id']]['form_data'] = $r;
            $customTestResultData[$r['unique_id']]['data_from_tests'] = $genericService->getTestsByGenericSampleIds($r['sample_id']);
        }

        $url = "$remoteURL/remote/remote/generic-test-results.php";

        $payload = [
            "labId" => $labId,
            "results" => $customTestResultData,
            'time' => time(),
            "instanceId" => $general->getInstanceId()
        ];
        $jsonResponse = $apiService->post($url, $payload, gzip: true);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_generic', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $totalResults  = count($result ?? []);
        if ($cliMode) {
            echo "Sent $totalResults test results from Custom Tests...\n";
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $totalResults, 'send-results', 'generic-tests', $url, $payload, $jsonResponse, 'json', $labId);
    }


    // VIRAL LOAD TEST RESULTS
    if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] === true) {
        if ($cliMode) {
            echo "Trying to send test results from HIV Viral Load...\n";
        }
        $vlQuery = "SELECT vl.*, a.user_name as 'approved_by_name'
            FROM `form_vl` AS vl
            LEFT JOIN `user_details` AS a ON vl.result_approved_by = a.user_id
            WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
            AND (facility_id != '' AND facility_id is not null)
            AND (sample_code !='' AND sample_code is not null)
            AND vl.data_sync = 0";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "vl" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $vlQuery .= " AND sample_code like '$sampleCode'";
        }

        $vlLabResult = $db->rawQuery($vlQuery);

        $url = "$remoteURL/remote/remote/testResults.php";

        $payload = [
            "labId" => $labId,
            "result" => $vlLabResult,
            'time' => time(),
            "instanceId" => $general->getInstanceId()
        ];

        $jsonResponse = $apiService->post($url, $payload, gzip: true);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_vl', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $totalResults  = count($result ?? []);
        if ($cliMode) {
            echo "Sent $totalResults test results from HIV Viral Load...\n";
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $totalResults, 'send-results', 'vl', $url, $payload, $jsonResponse, 'json', $labId);
    }

    // EID TEST RESULTS
    if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] === true) {
        if ($cliMode) {
            echo "Trying to send test results from EID...\n";
        }
        $eidQuery = "SELECT vl.*, a.user_name as 'approved_by_name'
                    FROM `form_eid` AS vl
                    LEFT JOIN `user_details` AS a ON vl.result_approved_by = a.user_id
                    WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                    AND sample_code !=''
                    AND sample_code is not null
                    AND vl.data_sync=0";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "eid" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $eidQuery .= " AND sample_code like '$sampleCode'";
        }
        $eidLabResult = $db->rawQuery($eidQuery);

        $url = "$remoteURL/remote/remote/eid-test-results.php";
        $payload = [
            "labId" => $labId,
            "result" => $eidLabResult,
            'time' => time(),
            "instanceId" => $general->getInstanceId()
        ];

        $jsonResponse = $apiService->post($url, $payload, gzip: true);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_eid', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }
        $totalResults  = count($result ?? []);
        if ($cliMode) {
            echo "Sent $totalResults test results from EID...\n";
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $totalResults, 'send-results', 'eid', $url, $payload, $jsonResponse, 'json', $labId);
    }

    // COVID-19 TEST RESULTS
    if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] === true) {
        if ($cliMode) {
            echo "Trying to send test results from Covid-19...\n";
        }
        $covid19Query = "SELECT c19.*, a.user_name as 'approved_by_name'
                    FROM `form_covid19` AS c19
                    LEFT JOIN `user_details` AS a ON c19.result_approved_by = a.user_id
                    WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                    AND sample_code !=''
                    AND sample_code is not null
                    AND c19.data_sync=0";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "covid19" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $covid19Query .= " AND sample_code like '$sampleCode'";
        }
        $c19LabResult = $db->rawQuery($covid19Query);

        $forms = array_column($c19LabResult, 'covid19_id');

        /** @var Covid19Service $covid19Service */
        $covid19Service = ContainerRegistry::get(Covid19Service::class);

        $c19ResultData = [];
        foreach ($c19LabResult as $r) {
            $c19ResultData[$r['unique_id']] = [];
            $c19ResultData[$r['unique_id']]['form_data'] = $r;
            // $c19ResultData[$r['unique_id']]['data_from_comorbidities'] = $covid19Service->getCovid19ComorbiditiesByFormId($r['covid19_id'], false, true);
            // $c19ResultData[$r['unique_id']]['data_from_symptoms'] = $covid19Service->getCovid19SymptomsByFormId($r['covid19_id'], false, true);
            $c19ResultData[$r['unique_id']]['data_from_tests'] = $covid19Service->getCovid19TestsByFormId($r['covid19_id']);
        }

        $url = "$remoteURL/remote/remote/covid-19-test-results.php";
        $payload = [
            "labId" => $labId,
            "results" => $c19ResultData,
            'time' => time(),
            "instanceId" => $general->getInstanceId()
        ];
        $jsonResponse = $apiService->post($url, $payload, gzip: true);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_covid19', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $totalResults  = count($result ?? []);
        if ($cliMode) {
            echo "Sent $totalResults test results from Covid-19...\n";
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $totalResults, 'send-results', 'covid19', $url, $payload, $jsonResponse, 'json', $labId);
    }

    // Hepatitis TEST RESULTS

    if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] === true) {
        if ($cliMode) {
            echo "Trying to send test results from Hepatitis...\n";
        }
        $hepQuery = "SELECT hep.*, a.user_name as 'approved_by_name'
                    FROM `form_hepatitis` AS hep
                    LEFT JOIN `user_details` AS a ON hep.result_approved_by = a.user_id
                    WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                    AND sample_code != ''
                    AND sample_code is not null
                    AND hep.data_sync=0";
        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "hepatitis" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $hepQuery .= " AND sample_code like '$sampleCode'";
        }
        $hepLabResult = $db->rawQuery($hepQuery);

        $url = "$remoteURL/remote/remote/hepatitis-test-results.php";
        $payload = [
            "labId" => $labId,
            "result" => $hepLabResult,
            'time' => time(),
            "instanceId" => $general->getInstanceId()
        ];

        $jsonResponse = $apiService->post($url, $payload, gzip: true);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_hepatitis', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $totalResults  = count($result ?? []);
        if ($cliMode) {
            echo "Sent $totalResults test results from Hepatitis...\n";
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $totalResults, 'send-results', 'hepatitis', $url, $payload, $jsonResponse, 'json', $labId);
    }

    // CD4 TEST RESULTS
    if (isset($systemConfig['modules']['cd4']) && $systemConfig['modules']['cd4'] === true) {
        if ($cliMode) {
            echo "Trying to send test results from CD4...\n";
        }
        $cd4Query = "SELECT cd4.*, a.user_name as 'approved_by_name'
            FROM `form_cd4` AS cd4
            LEFT JOIN `user_details` AS a ON cd4.result_approved_by = a.user_id
            WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
            AND (facility_id != '' AND facility_id is not null)
            AND (sample_code !='' AND sample_code is not null)
            AND cd4.data_sync = 0";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "cd4" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $cd4Query .= " AND sample_code like '$sampleCode'";
        }

        $cd4LabResult = $db->rawQuery($cd4Query);

        $url = "$remoteURL/remote/remote/cd4-test-results.php";

        $payload = [
            "labId" => $labId,
            "result" => $cd4LabResult,
            'time' => time(),
            "instanceId" => $general->getInstanceId()
        ];

        $jsonResponse = $apiService->post($url, $payload, gzip: true);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_cd4', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }
        $totalResults  = count($result ?? []);
        if ($cliMode) {
            echo "Sent $totalResults test results from CD4...\n";
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $totalResults, 'send-results', 'cd4', $url, $payload, $jsonResponse, 'json', $labId);
    }

    $instanceId = $general->getInstanceId();
    $db->where('vlsm_instance_id', $instanceId);
    $id = $db->update('s_vlsm_instance', ['last_remote_results_sync' => DateUtility::getCurrentDateTime()]);
} catch (Exception $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastQuery());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
