<?php

if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../../bootstrap.php");
}

//this file gets the data from the local database and updates the remote database

use App\Services\ApiService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
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

if (!isset($systemConfig['remoteURL']) || $systemConfig['remoteURL'] == '') {
    error_log("Please check if STS URL is set");
    exit(0);
}
try {
    // Checking if the network connection is available
    $remoteUrl = rtrim((string) $systemConfig['remoteURL'], "/");
    if ($apiService->checkConnectivity($remoteUrl . '/api/version.php?labId=' . $labId . '&version=' . $version) === false) {
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

    // GERNERIC TEST RESULTS
    if (isset($systemConfig['modules']['generic-tests']) && $systemConfig['modules']['generic-tests'] === true) {

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
        $testResults = $genericService->getTestsByGenericSampleIds($forms);
        $url = $remoteUrl . '/remote/remote/generic-test-results.php';
        $payload = [
            "labId" => $labId,
            "result" => $genericLabResult,
            "testResults" => $testResults,
            "Key" => "vlsm-lab-data--",
        ];

        $jsonResponse = $apiService->post($url, $payload);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_generic', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $general->addApiTracking($transactionId, 'vlsm-system', count($genericLabResult), 'send-results', 'generic', $url, $payload, $jsonResponse, 'json', $labId);
    }


    // VIRAL LOAD TEST RESULTS
    if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] === true) {
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

        $url = $remoteUrl . '/remote/remote/testResults.php';

        $payload = [
            "labId" => $labId,
            "result" => $vlLabResult,
            "Key" => "vlsm-lab-data--",
        ];

        $jsonResponse = $apiService->post($url, $payload);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_vl', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $general->addApiTracking($transactionId, 'vlsm-system', count($vlLabResult), 'send-results', 'vl', $url, $payload, $jsonResponse, 'json', $labId);
    }

    // EID TEST RESULTS
    if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] === true) {
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

        $url = $remoteUrl . '/remote/remote/eid-test-results.php';
        $payload = [
            "labId" => $labId,
            "result" => $eidLabResult,
            "Key" => "vlsm-lab-data--",
        ];

        $jsonResponse = $apiService->post($url, $payload);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_eid', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $general->addApiTracking($transactionId, 'vlsm-system', count($eidLabResult), 'send-results', 'eid', $url, $payload, $jsonResponse, 'json', $labId);
    }

    // COVID-19 TEST RESULTS
    if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] === true) {

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
        $symptoms = $covid19Service->getCovid19SymptomsByFormId($forms);
        $comorbidities = $covid19Service->getCovid19ComorbiditiesByFormId($forms);
        $testResults = $covid19Service->getCovid19TestsByFormId($forms);

        $url = $remoteUrl . '/remote/remote/covid-19-test-results.php';
        $payload = [
            "labId" => $labId,
            "result" => $c19LabResult,
            "testResults" => $testResults,
            "symptoms" => $symptoms,
            "comorbidities" => $comorbidities,
            "Key" => "vlsm-lab-data--",
        ];
        $jsonResponse = $apiService->post($url, $payload);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_covid19', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $general->addApiTracking($transactionId, 'vlsm-system', count($c19LabResult), 'send-results', 'covid19', $url, $payload, $jsonResponse, 'json', $labId);
    }

    // Hepatitis TEST RESULTS

    if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] === true) {

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

        $url = $remoteUrl . '/remote/remote/hepatitis-test-results.php';
        $payload = [
            "labId" => $labId,
            "result" => $hepLabResult,
            "Key" => "vlsm-lab-data--"
        ];

        $jsonResponse = $apiService->post($url, $payload);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_hepatitis', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $general->addApiTracking($transactionId, 'vlsm-system', count($hepLabResult), 'send-results', 'hepatitis', $url, $payload, $jsonResponse, 'json', $labId);
    }

    $instanceId = $general->getInstanceId();
    $db->where('vlsm_instance_id', $instanceId);
    $id = $db->update('s_vlsm_instance', ['last_remote_results_sync' => DateUtility::getCurrentDateTime()]);
} catch (Exception $exc) {
    error_log($db->getLastError());
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
