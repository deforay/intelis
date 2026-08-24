#!/usr/bin/env php
<?php

// Smart Connect: push pending EID sample data to the configured upstream API.

$cliMode = PHP_SAPI === 'cli';
if ($cliMode) {
    require_once(__DIR__ . "/../../bootstrap.php");
}

use App\Services\ApiService;
use App\Services\SmartConnectService;
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

/** @var SmartConnectService $smartConnect */
$smartConnect = ContainerRegistry::get(SmartConnectService::class);

$transactionId = MiscUtility::generateULID();
$filename = null;

try {

    $smartConnectURL = $general->getGlobalConfig('vldashboard_url');

    if (empty($smartConnectURL)) {
        echo "Smart Connect URL not set";
        exit(0);
    }

    $baseUrl = rtrim((string) $smartConnectURL, "/");

    /// Probe once per run. Plain unauthenticated GET, no bearer token set yet.
    // 200 -> v2 paths, 404 -> v1 paths, 410 -> cutover was missed on this
    // install and needs attention (log loudly).
    $healthResult = $apiService->getHealth($baseUrl . '/api/v2/health', true);
    $healthStatus = $healthResult['httpStatusCode'] ?? null;
    $healthBody = json_decode((string) ($healthResult['body'] ?? ''), true);

    if ($healthStatus === 200 && ($healthBody['status'] ?? null) === 'success') {
        $useV2 = true;
    } elseif ($healthStatus === 404) {
        $useV2 = false;
    } elseif ($healthStatus === 410) {
        LoggerUtility::log("error", "Smart Connect: dashboard returned 410 - this install missed the v1 to v2 cutover", [
            'file' => __FILE__,
            'line' => __LINE__,
            'url' => $baseUrl,
        ]);
        exit(0);
    } else {
        LoggerUtility::log("error", "Unable to connect to Smart Connect health endpoint", [
            'file' => __FILE__,
            'line' => __LINE__,
            'url' => $baseUrl,
            'status' => $healthStatus,
        ]);
        exit(0);
    }

    $url = $baseUrl . ($useV2 ? '/api/v2/eid' : '/api/vlsm-eid');

    $instanceUpdateOn = $db->getValue('s_vlsm_instance', 'eid_last_dash_sync');

    if (!empty($instanceUpdateOn)) {
        $db->where('last_modified_datetime', $instanceUpdateOn, ">");
    }

    $db->orderBy("last_modified_datetime", "ASC");
    $rResult = $db->get('form_eid', 5000);

    if (empty($rResult)) {
        exit(0);
    }

    // Builds the upload payload/file for a given slice of rows.
    $writeBatch = function (array $rows) use ($instanceUpdateOn) {
        $output = [
            'timestamp' => empty($instanceUpdateOn) ? time() : strtotime((string) $instanceUpdateOn),
            'data' => $rows,
        ];
        $lastUpdate = max(array_column($rows, 'last_modified_datetime'));
        $filename = MiscUtility::generateRandomString(12) . time() . '.json';
        $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
        fwrite($fp, json_encode($output));
        fclose($fp);
        return [$filename, $output, $lastUpdate];
    };

    [$filename, $output, $lastUpdate] = $writeBatch($rResult);

    $params = [
        [
            'name' => 'source',
            'contents' => ($general->isSTSInstance()) ? 'STS' : 'LIS'
        ],
        [
            'name' => 'labId',
            'contents' => $general->getSystemConfig('sc_testing_lab_id') ?? null
        ]
    ];
    if (!$useV2) {
        $params[] = ['name' => 'api-version', 'contents' => 'v2'];
    }

    if ($useV2) {
        $token = $smartConnect->token();
        if (empty($token)) {
            LoggerUtility::logError('Smart Connect: no API token and enrollment failed');
            MiscUtility::deleteFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
            exit(0);
        }
        $apiService->setBearerToken($token);
    }

    $result = $apiService->postFile($url, 'eidFile', TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params, true, true);
    $status = $result['httpStatusCode'] ?? null;

    // One re-enrollment, one retry. Never a loop.
    if ($useV2 && $status === 401) {
        $smartConnect->forgetToken();
        $token = $smartConnect->enroll();
        if (!empty($token)) {
            $apiService->setBearerToken($token);
            $result = $apiService->postFile($url, 'eidFile', TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params, true, true);
            $status = $result['httpStatusCode'] ?? null;
        }
    }

    // Payload too large for the dashboard's post_max_size: halve the
    // batch and retry once with a smaller file.
    if ($status === 413 && count($rResult) > 1) {
        MiscUtility::deleteFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);

        $halved = array_slice($rResult, 0, (int) ceil(count($rResult) / 2));
        [$filename, $output, $lastUpdate] = $writeBatch($halved);
        $rResult = $halved;

        $result = $apiService->postFile($url, 'eidFile', TEMP_PATH . DIRECTORY_SEPARATOR . $filename, $params, true, true);
        $status = $result['httpStatusCode'] ?? null;
    }

    $response = $result['body'] ?? null;
    $deResult = json_decode((string) $response, true);

    $general->addApiTracking(
        $transactionId,
        'vlsm-system',
        count($rResult),
        'smart-connect-eid-sync',
        'eid',
        $url,
        $output,
        $response,
        'json'
    );

    if (isset($deResult['status']) && trim((string) $deResult['status']) === 'success') {
        $data = [
            'eid_last_dash_sync' => (empty($lastUpdate) ? DateUtility::getCurrentDateTime() : $lastUpdate)
        ];

        $db->update('s_vlsm_instance', $data);
    }

    MiscUtility::deleteFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    exit(0);
} catch (Exception $exc) {
    if (!empty($filename)) {
        MiscUtility::deleteFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    }
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => $exc->getTraceAsString(),
    ]);
}