<?php
// tasks/remote/results-sender.php
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
use App\Services\TbService;

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

$stsBearerToken = $general->getSTSToken();
$apiService->setBearerToken($stsBearerToken);


$isSilent = false;
$syncSinceDate = null;
$forceSyncModule = null;
$sampleCode = null;

if ($cliMode) {
    foreach ($argv as $index => $arg) {
        if ($index === 0) continue;

        $arg = trim($arg);

        if ($arg === 'silent') {
            $isSilent = true;
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg) && DateUtility::isDateFormatValid($arg, 'Y-m-d')) {
            $syncSinceDate ??= DateUtility::getDateTime($arg, 'Y-m-d');
        } elseif (is_numeric($arg)) {
            $syncSinceDate ??= DateUtility::daysAgo((int)$arg);
        } elseif (in_array($arg, ['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'cd4', 'generic-tests'])) {
            $forceSyncModule = $arg;
        } else {
            echo "Invalid argument: $arg\n";
            exit(1);
        }
    }

    if ($syncSinceDate !== null) {
        echo "Syncing results since: $syncSinceDate\n";
    }

    if ($forceSyncModule !== null) {
        echo "Forcing module sync for: $forceSyncModule\n";
    }
}

// Web fallback
$forceSyncModule = $forceSyncModule ? strtolower(trim($forceSyncModule)) : null;
$sampleCode = $sampleCode ?? ($_GET['sampleCode'] ?? null);

// If module is forced, override modules config
if (!empty($forceSyncModule)) {
    unset($systemConfig['modules']);
    $systemConfig['modules'][$forceSyncModule] = true;
}


//Sending results to /v2/results.php for all test types
$url = "$remoteURL/remote/v2/results.php";

try {
    // Checking if the network connection is available
    if ($apiService->checkConnectivity("$remoteURL/api/version.php?labId=$labId&version=$version") === false) {
        LoggerUtility::log('error', "No network connectivity while trying remote sync.");
        return false;
    }

    $transactionId = MiscUtility::generateULID();
    // CUSTOM TEST RESULTS
    if (isset($systemConfig['modules']['generic-tests']) && $systemConfig['modules']['generic-tests'] === true) {
        if ($cliMode) {
            echo "Trying to send test results from Custom Tests..." . PHP_EOL;
        }

        $genericQuery = "SELECT generic.*, a.user_name as 'approved_by_name'
                    FROM `form_generic` AS generic
                    LEFT JOIN `user_details` AS a ON generic.result_approved_by = a.user_id
                    WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                    AND IFNULL(generic.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "generic-tests" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $genericQuery .= " AND generic.sample_code like '$sampleCode'";
        }

        if (null !== $syncSinceDate) {
            $genericQuery .= " AND DATE(generic.last_modified_datetime) >= '$syncSinceDate'";
        } else {
            $genericQuery .= " AND generic.data_sync = 0";
        }

        $db->reset();
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

        $payload = [
            "labId" => $labId,
            "results" => $customTestResultData,
            "testType" => "generic-tests",
            'timestamp' => DateUtility::getCurrentTimestamp(),
            "instanceId" => $general->getInstanceId(),
            "silent" => $isSilent
        ];

        $jsonResponse = $apiService->post($url, $payload, gzip: true);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_generic', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $totalResults  = count($result ?? []);
        if ($cliMode) {
            echo "Synced $totalResults test results from Custom Tests..." . PHP_EOL;
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
            AND IFNULL(vl.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "vl" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $vlQuery .= " AND sample_code like '$sampleCode'";
        }

        if (null !== $syncSinceDate) {
            $vlQuery .= " AND DATE(vl.last_modified_datetime) >= '$syncSinceDate'";
        } else {
            $vlQuery .= " AND vl.data_sync = 0";
        }

        $db->reset();
        $vlLabResult = $db->rawQuery($vlQuery);

        $payload = [
            "labId" => $labId,
            "results" => $vlLabResult,
            "testType" => "vl",
            'timestamp' => DateUtility::getCurrentTimestamp(),
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
            echo "Synced $totalResults test results from HIV Viral Load...\n";
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
                    AND IFNULL(vl.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "eid" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $eidQuery .= " AND sample_code like '$sampleCode'";
        }

        if (null !== $syncSinceDate) {
            $eidQuery .= " AND DATE(vl.last_modified_datetime) >= '$syncSinceDate'";
        } else {
            $eidQuery .= " AND vl.data_sync = 0";
        }

        $db->reset();
        $eidLabResult = $db->rawQuery($eidQuery);

        $payload = [
            "labId" => $labId,
            "results" => $eidLabResult,
            "testType" => "eid",
            'timestamp' => DateUtility::getCurrentTimestamp(),
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
            echo "Synced $totalResults test results from EID...\n";
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
                    AND IFNULL(c19.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "covid19" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $covid19Query .= " AND sample_code like '$sampleCode'";
        }

        if (null !== $syncSinceDate) {
            $covid19Query .= " AND DATE(c19.last_modified_datetime) >= '$syncSinceDate'";
        } else {
            $covid19Query .= " AND c19.data_sync = 0";
        }

        $db->reset();
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


        $payload = [
            "labId" => $labId,
            "results" => $c19ResultData,
            "testType" => "covid19",
            'timestamp' => DateUtility::getCurrentTimestamp(),
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
            echo "Synced $totalResults test results from Covid-19...\n";
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
                    AND IFNULL(hep.sample_code, '') != ''";
        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "hepatitis" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $hepQuery .= " AND sample_code like '$sampleCode'";
        }

        if (null !== $syncSinceDate) {
            $hepQuery .= " AND DATE(hep.last_modified_datetime) >= '$syncSinceDate'";
        } else {
            $hepQuery .= " AND hep.data_sync = 0";
        }

        $db->reset();
        $hepLabResult = $db->rawQuery($hepQuery);



        $payload = [
            "labId" => $labId,
            "results" => $hepLabResult,
            "testType" => "hepatitis",
            'timestamp' => DateUtility::getCurrentTimestamp(),
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
            echo "Synced $totalResults test results from Hepatitis...\n";
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $totalResults, 'send-results', 'hepatitis', $url, $payload, $jsonResponse, 'json', $labId);
    }

    // TB TEST RESULTS
    if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] === true) {
        /** @var TbService $tbService */
        $tbService = ContainerRegistry::get(TbService::class);

        if ($cliMode) {
            echo "Trying to send test results from TB...\n";
        }
        $tbQuery = "SELECT tb.*, a.user_name as 'approved_by_name'
            FROM `form_tb` AS tb
            LEFT JOIN `user_details` AS a ON tb.result_approved_by = a.user_id
            WHERE result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
            AND IFNULL(tb.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "tb" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $tbQuery .= " AND sample_code like '$sampleCode'";
        }

        if (null !== $syncSinceDate) {
            $tbQuery .= " AND DATE(tb.last_modified_datetime) >= '$syncSinceDate'";
        } else {
            $tbQuery .= " AND tb.data_sync = 0";
        }

        $db->reset();
        $tbLabResult = $db->rawQuery($tbQuery);
        $tbTestResultData = [];
        foreach ($tbLabResult as $key => $r) {
            $tbTestResultData[$r['unique_id']] = [];
            $tbTestResultData[$r['unique_id']]['form_data'] = $r;
            $tbTestResultData[$r['unique_id']]['data_from_tests'] = $tbService->getTbTestsByFormId($r['tb_id']);
        }

        $payload = [
            "labId" => $labId,
            "results" => $tbTestResultData,
            "testType" => "tb",
            'timestamp' => DateUtility::getCurrentTimestamp(),
            "instanceId" => $general->getInstanceId()
        ];
        $jsonResponse = $apiService->post($url, $payload, gzip: true);
        $result = json_decode($jsonResponse, true);

        if (!empty($result)) {
            $db->where('sample_code', $result, 'IN');
            $id = $db->update('form_tb', ['data_sync' => 1, 'result_sent_to_source' => 'sent']);
        }

        $totalResults  = count($result ?? []);
        if ($cliMode) {
            echo "Synced $totalResults test results from TB...\n";
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $totalResults, 'send-results', 'tb', $url, $payload, $jsonResponse, 'json', $labId);
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
            AND IFNULL(cd4.sample_code, '') != ''";

        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == "cd4" && !empty($sampleCode) && trim((string) $sampleCode) != "") {
            $cd4Query .= " AND sample_code like '$sampleCode'";
        }

        if (null !== $syncSinceDate) {
            $cd4Query .= " AND DATE(cd4.last_modified_datetime) >= '$syncSinceDate'";
        } else {
            $cd4Query .= " AND cd4.data_sync = 0";
        }

        $db->reset();
        $cd4LabResult = $db->rawQuery($cd4Query);


        $payload = [
            "labId" => $labId,
            "results" => $cd4LabResult,
            "testType" => "cd4",
            'timestamp' => DateUtility::getCurrentTimestamp(),
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
            echo "Synced $totalResults test results from CD4...\n";
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $totalResults, 'send-results', 'cd4', $url, $payload, $jsonResponse, 'json', $labId);
    }

    $instanceId = $general->getInstanceId();
    $db->where('vlsm_instance_id', $instanceId);
    $id = $db->update('s_vlsm_instance', ['last_remote_results_sync' => DateUtility::getCurrentDateTime()]);
} catch (Exception $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
    ]);
}
