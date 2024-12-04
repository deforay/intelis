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

/** @var ResultsService $stsResultsService */
$stsResultsService = ContainerRegistry::get(ResultsService::class);

/** @var TokensService $stsTokensService */
$stsTokensService = ContainerRegistry::get(TokensService::class);


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
        $testType = $forceSyncModule;
    }


    $resultsData = $stsResultsService->getResults($testType, $labId);


    $sampleIds = $requestsData['sampleIds'] ?? [];
    $facilityIds = $requestsData['facilityIds'] ?? [];
    $requests = $requestsData['requests'] ?? [];


    $payload['status'] = 'success';
    $payload['requests'] = $requests;
    $payload['testType'] = $testType;

    $general->addApiTracking($transactionId, 'system', $resultCount, 'requests', $testType, $_SERVER['REQUEST_URI'], JsonUtility::encodeUtf8Json($data), $payload, 'json', $labId);

    if (!empty($facilityIds)) {
        $general->updateTestRequestsSyncDateTime($testType, $facilityIds, $labId);
    }


    if (!empty($sampleIds)) {
        $updateData = [
            'data_sync' => 1
        ];
        $db->where($primaryKeyName, $sampleIds, 'IN');
        $db->update($tableName, $updateData);
    }

    $db->commitTransaction();
} catch (Throwable $e) {
    $db->rollbackTransaction();

    $payload = [
        'status' => 'failed',
        'testType' => $testType,
        'error' => _translate('Unable to process the request')
    ];

    LoggerUtility::log('error', $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
    throw new SystemException($e->getMessage(), $e->getCode(), $e);
}


echo ApiService::sendJsonResponse($payload, $request);
