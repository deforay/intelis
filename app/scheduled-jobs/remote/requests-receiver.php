<?php
//this file gets the requests from the remote server and updates the local database


$cliMode = php_sapi_name() === 'cli';
if ($cliMode) {
    require_once __DIR__ . "/../../../bootstrap.php";
}

use JsonMachine\Items;
use App\Services\ApiService;
use GuzzleHttp\Promise\Utils;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;


ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

$db->rawQuery("SET SESSION wait_timeout=28800"); // 8 hours

function spinner(int $loopIndex, int $count, string $label = 'Processed', array $spinnerChars = ['.   ', '..  ', '... ', '....']): void
{
    static $lastSpinnerChar = '';
    $shouldUpdateSpinner = ($loopIndex % 10 === 0);

    if ($shouldUpdateSpinner) {
        $lastSpinnerChar = $spinnerChars[intdiv($loopIndex, 10) % count($spinnerChars)];
    }

    echo "\r$lastSpinnerChar $label: $count";
    ob_flush();
    flush();
}

function clearSpinner(): void
{
    echo "\r" . str_repeat(' ', 40) . "\r";
}

$forceSyncModule = $manifestCode = null;
$syncSinceDate = null;
$isSilent = false;
if ($cliMode) {
    require_once __DIR__ . "/../../../bootstrap.php";
    echo PHP_EOL;
    echo "=========================" . PHP_EOL;
    echo "Starting test requests sync" . PHP_EOL;

    $args = array_slice($_SERVER['argv'], 1);

    // Use getopt if present
    $options = getopt("t:m:");

    if (isset($options['t'])) {
        $forceSyncModule = $options['t'];
    }
    if (isset($options['m'])) {
        $manifestCode = $options['m'];
    }

    // Scan all args to find a valid date or number-of-days
    foreach ($args as $arg) {
        if (str_contains(strtolower($arg), 'silent')) {
            $isSilent = true;
            continue;
        }

        // Skip if it's already parsed as -t or -m
        if (in_array($arg, [$forceSyncModule, $manifestCode], true)) {
            continue;
        }

        $arg = trim($arg);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg) && DateUtility::isDateFormatValid($arg, 'Y-m-d')) {
            $syncSinceDate = DateUtility::getDateTime($arg, 'Y-m-d');
            break;
        } elseif (is_numeric($arg)) {
            $syncSinceDate = DateUtility::daysAgo((int) $arg);
            break;
        }
    }
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$postParams = $request->getParsedBody();

if (!empty($postParams)) {
    $_POST = _sanitizeInput($postParams);

    $manifestCode = $_POST['manifestCode'] ?? null;
    $forceSyncModule = $_POST['testType'] ?? $_POST['forceSyncModule'] ?? null;
    $syncSinceDate = $_POST['syncSinceDate'] ?? null;
    $isSilent = $_POST['silent'] ?? false;
}

if ($syncSinceDate !== null) {
    echo "Filtering requests from: $syncSinceDate" . PHP_EOL;
}
$transactionId = MiscUtility::generateULID();

$labId = $general->getSystemConfig('sc_testing_lab_id');
$version = VERSION;

$systemConfig = SYSTEM_CONFIG;

$remoteURL = $general->getRemoteURL();

if (empty($remoteURL)) {
    LoggerUtility::logError("Please check if STS URL is set");
    exit(0);
}

if ($apiService->checkConnectivity("$remoteURL/api/version.php?labId=$labId&version=$version") === false) {
    LoggerUtility::logError("No internet connectivity while trying remote sync.", [
        'line' => __LINE__,
        'file' => __FILE__,
        'remoteURL' => $remoteURL
    ]);
    if ($cliMode) {
        echo "No internet connectivity while trying remote sync." . PHP_EOL;
    }
    exit(0);
}

//get remote data
if (empty($labId)) {
    if ($cliMode) {
        echo "No Lab ID set in System Config";
    }
    LoggerUtility::logError("No Lab ID set in System Config", [
        'line' => __LINE__,
        'file' => __FILE__,
        'remoteURL' => $remoteURL
    ]);
    exit(0);
}

// if only one module is getting synced, lets only sync that one module
if (!empty($forceSyncModule)) {
    unset($systemConfig['modules']);
    $systemConfig['modules'][$forceSyncModule] = true;
}

$stsBearerToken = $general->getSTSToken();

$apiService->setBearerToken($stsBearerToken);



$promises = [];

// Record the start time of the entire process
$startTime = microtime(true);

$responsePayload = [];
foreach ($systemConfig['modules'] as $module => $status) {
    $basePayload = [
        'labId' => $labId,
        'transactionId' => $transactionId
    ];
    if ($status === true) {
        $basePayload['testType'] = $module;
        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == $module && !empty($manifestCode) && trim((string) $manifestCode) != "") {
            $basePayload['manifestCode'] = $manifestCode;
        }
        if (!empty($syncSinceDate)) {
            $basePayload['syncSinceDate'] = $syncSinceDate;
        }
        $promises[$module] = $apiService->post(
            "$remoteURL/remote/v2/requests.php",
            $basePayload,
            gzip: true,
            async: true
        )->then(function ($response) use (&$responsePayload, $module, $cliMode) {
            $responsePayload[$module] = $response->getBody()->getContents();
            if ($cliMode) {
                echo "Received server response for $module" . PHP_EOL;
            }
        })->otherwise(function ($reason) use ($module, $cliMode) {
            if ($cliMode) {
                echo _sanitizeOutput("STS Request sync for $module failed: $reason") . PHP_EOL;
            }
            LoggerUtility::logError(__FILE__ . ":" . __LINE__ . ":" . "STS Request sync for $module failed: " . $reason);
        });
    }
}

// Wait for all promises to settle
Utils::settle($promises)->wait();

// Record the end time of the entire process
$endTime = microtime(true);
if ($cliMode) {
    // Print the total execution time
    echo "Total download time for STS Requests: " . ($endTime - $startTime) . " seconds" . PHP_EOL;
}

/*
****************************************************************
* HIV VL TEST REQUESTS
****************************************************************
*/
try {
    $request = [];
    if (!empty($responsePayload['vl']) && $responsePayload['vl'] != '[]' && JsonUtility::isJSON($responsePayload['vl'])) {

        $primaryKeyName = TestsService::getTestPrimaryKeyColumn('vl');
        $tableName = TestsService::getTestTableName('vl');

        if ($cliMode) {
            echo PHP_EOL;
            echo "=========================" . PHP_EOL;
            echo "Processing for HIV VL" . PHP_EOL;
        }

        $options = [
            'pointer' => '/requests',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($responsePayload['vl'], $options);

        $removeKeys = [
            $primaryKeyName,
            'sample_batch_id',
            'result_value_log',
            'result_value_absolute',
            'result_value_absolute_decimal',
            'result_value_text',
            'result',
            'sample_tested_datetime',
            'sample_received_at_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'request_created_datetime',
            'last_modified_by',
            'data_sync'
        ];

        $localDbFieldArray = $general->getTableFieldsAsArray($tableName, $removeKeys);

        $loopIndex = 0;
        $successCounter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();

                // Overwrite the values in $localDbFieldArray with the values in $originalLISRecord
                // basically we are making sure that we only use columns that are present in the $localDbFieldArray
                // which is from local db and not using the ones in the $originalLISRecord
                $request = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $remoteData);

                $localRecord = $testRequestsService->findMatchingLocalRecord($request, $tableName, $primaryKeyName);
                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                if (!empty($localRecord)) {

                    $removeKeysForUpdate = [
                        'sample_code',
                        'sample_code_key',
                        'sample_code_format',
                        'sample_batch_id',
                        'lab_id',
                        'vl_test_platform',
                        'sample_received_at_hub_datetime',
                        'sample_received_at_lab_datetime',
                        'sample_tested_datetime',
                        'result_dispatched_datetime',
                        'is_sample_rejected',
                        'reason_for_sample_rejection',
                        'rejection_on',
                        'result_value_absolute',
                        'result_value_absolute_decimal',
                        'result_value_text',
                        'result',
                        'result_value_log',
                        'result_value_hiv_detection',
                        'reason_for_failure',
                        'result_reviewed_by',
                        'result_reviewed_datetime',
                        'vl_focal_person',
                        'vl_focal_person_phone_number',
                        'tested_by',
                        'result_approved_by',
                        'result_approved_datetime',
                        'lab_tech_comments',
                        'reason_for_result_changes',
                        'revised_by',
                        'revised_on',
                        'last_modified_by',
                        'last_modified_datetime',
                        'manual_result_entry',
                        'result_status',
                        'data_sync',
                        'result_printed_datetime',
                        'result_printed_on_sts_datetime',
                        'vl_result_category'
                    ];

                    $request = MiscUtility::excludeKeys($request, $removeKeysForUpdate);

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] ??= 'no';
                    if (MiscUtility::isArrayEqual($request, $localRecord, ['last_modified_datetime', 'form_attributes'])) {
                        $id = true;
                    } else {
                        if ($isSilent) {
                            unset($request['last_modified_datetime']);
                        }
                        $db->where($primaryKeyName, $localRecord[$primaryKeyName]);
                        $id = $db->update($tableName, $request);
                    }
                } else {
                    $request['source_of_request'] = 'vlsts';
                    if (!empty($request['sample_collection_date'])) {

                        $request['source_of_request'] = "vlsts";
                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] ??= 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;
                        $id = $db->insert('form_vl', $request);
                    }
                }
                if ($id === true) {
                    $successCounter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                    'error_id' => MiscUtility::generateErrorId(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'local_unique_id' => $localRecord['unique_id'] ?? null,
                    'received_unique_id' => $request['unique_id'] ?? null,
                    'local_sample_code' => $localRecord['sample_code'] ?? null,
                    'received_sample_code' => $request['sample_code'] ?? null,
                    'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                    'received_remote_sample_code' => $request['remote_sample_code'] ?? null,
                    'local_facility_id' => $localRecord['facility_id'] ?? null,
                    'received_facility_id' => $request['facility_id'] ?? null,
                    'local_lab_id' => $localRecord['lab_id'] ?? null,
                    'received_lab_id' => $request['lab_id'] ?? null,
                    'local_result' => $localRecord['result'] ?? null,
                    'received_result' => $request['result'] ?? null,
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }
            if ($cliMode) {
                spinner($loopIndex, $successCounter);
            }
            $loopIndex++;
        }
        if ($cliMode) {
            clearSpinner();
            echo PHP_EOL;
            echo "Synced $successCounter VL record(s)" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $successCounter, 'receive-requests', 'vl', $url, $payload, $responsePayload['vl'], 'json', $labId);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
}

/*
****************************************************************
*  EID TEST REQUESTS
****************************************************************
*/

try {
    $request = [];
    if (!empty($responsePayload['eid']) && $responsePayload['eid'] != '[]' && JsonUtility::isJSON($responsePayload['eid'])) {
        $primaryKeyName = TestsService::getTestPrimaryKeyColumn('eid');
        $tableName = TestsService::getTestTableName('eid');

        if ($cliMode) {
            echo PHP_EOL;
            echo "=========================" . PHP_EOL;
            echo "Processing for EID" . PHP_EOL;
        }

        $options = [
            'pointer' => '/requests',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($responsePayload['eid'], $options);

        $removeKeys = [
            $primaryKeyName,
            'sample_batch_id',
            'result',
            'sample_tested_datetime',
            'sample_received_at_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'data_sync'
        ];

        $localDbFieldArray = $general->getTableFieldsAsArray($tableName, $removeKeys);

        $loopIndex = 0;
        $successCounter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {

                // Overwrite the values in $localDbFieldArray with the values in $originalLISRecord
                // basically we are making sure that we only use columns that are present in the $localDbFieldArray
                // which is from local db and not using the ones in the $originalLISRecord
                $request = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $remoteData);

                $columns = array_diff(array_keys($request), [$primaryKeyName]);
                $columnsForSelect = implode(', ', $columns);
                $query = "SELECT $primaryKeyName, {$columnsForSelect} FROM $tableName";

                $localRecord = $testRequestsService->findMatchingLocalRecord($request,  $tableName, $primaryKeyName);
                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                if (!empty($localRecord)) {

                    $removeKeysForUpdate = [
                        'sample_code',
                        'sample_code_key',
                        'sample_code_format',
                        'sample_batch_id',
                        'sample_received_at_lab_datetime',
                        'eid_test_platform',
                        'import_machine_name',
                        'sample_tested_datetime',
                        'is_sample_rejected',
                        'lab_id',
                        'result',
                        'tested_by',
                        'lab_tech_comments',
                        'result_approved_by',
                        'result_approved_datetime',
                        'revised_by',
                        'revised_on',
                        'result_reviewed_by',
                        'result_reviewed_datetime',
                        'result_dispatched_datetime',
                        'reason_for_changing',
                        'result_status',
                        'data_sync',
                        'reason_for_sample_rejection',
                        'rejection_on',
                        'last_modified_by',
                        'result_printed_datetime',
                        'result_printed_on_sts_datetime',
                        'last_modified_datetime'
                    ];

                    $request = MiscUtility::excludeKeys($request, $removeKeysForUpdate);

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] ??= 'no';
                    if (MiscUtility::isArrayEqual($request, $localRecord, ['last_modified_datetime', 'form_attributes'])) {
                        $id = true;
                    } else {

                        if ($isSilent) {
                            unset($request['last_modified_datetime']);
                        }
                        $db->where($primaryKeyName, $localRecord[$primaryKeyName]);
                        $id = $db->update($tableName, $request);
                    }
                } else {
                    if (!empty($request['sample_collection_date'])) {

                        $request['source_of_request'] = "vlsts";
                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] ??= 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;

                        $id = $db->insert($tableName, $request);
                    }
                }
                if ($id === true) {
                    $successCounter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getMessage(), [
                    'error_id' => MiscUtility::generateErrorId(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'local_unique_id' => $localRecord['unique_id'] ?? null,
                    'received_unique_id' => $request['unique_id'] ?? null,
                    'local_sample_code' => $localRecord['sample_code'] ?? null,
                    'received_sample_code' => $request['sample_code'] ?? null,
                    'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                    'received_remote_sample_code' => $request['remote_sample_code'] ?? null,
                    'local_facility_id' => $localRecord['facility_id'] ?? null,
                    'received_facility_id' => $request['facility_id'] ?? null,
                    'local_lab_id' => $localRecord['lab_id'] ?? null,
                    'received_lab_id' => $request['lab_id'] ?? null,
                    'local_result' => $localRecord['result'] ?? null,
                    'received_result' => $request['result'] ?? null,
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }

            if ($cliMode) {
                spinner($loopIndex, $successCounter);
            }
            $loopIndex++;
        }
        if ($cliMode) {
            clearSpinner();
            echo PHP_EOL;
            echo "Synced $successCounter EID record(s)" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $successCounter, 'receive-requests', 'eid', $url, $payload, $responsePayload['eid'], 'json', $labId);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
}
/*
****************************************************************
*  COVID-19 TEST REQUESTS
****************************************************************
*/

try {
    $request = [];
    if (!empty($responsePayload['covid19']) && $responsePayload['covid19'] != '[]' && JsonUtility::isJSON($responsePayload['covid19'])) {
        $primaryKeyName = TestsService::getTestPrimaryKeyColumn('covid19');
        $tableName = TestsService::getTestTableName('covid19');

        if ($cliMode) {
            echo PHP_EOL;
            echo "=========================" . PHP_EOL;
            echo "Processing for Covid-19" . PHP_EOL;
        }

        $options = [
            'pointer' => '/requests',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($responsePayload['covid19'], $options);

        $removeKeys = [
            $primaryKeyName,
            'sample_batch_id',
            'result',
            'sample_tested_datetime',
            'sample_received_at_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'last_modified_by',
            'request_created_datetime',
            'data_sync'
        ];

        $localDbFieldArray = $general->getTableFieldsAsArray($tableName, $removeKeys);

        $loopIndex = 0;
        $successCounter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();

                // Overwrite the values in $localDbFieldArray with the values in $originalLISRecord
                // basically we are making sure that we only use columns that are present in the $localDbFieldArray
                // which is from local db and not using the ones in the $originalLISRecord
                $request = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $remoteData);

                $columns = array_diff(array_keys($request), [$primaryKeyName]);
                $columnsForSelect = implode(', ', $columns);
                $query = "SELECT $primaryKeyName, {$columnsForSelect} FROM $tableName";

                $localRecord = $testRequestsService->findMatchingLocalRecord($request,  $tableName, $primaryKeyName);
                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                if (!empty($localRecord)) {

                    $removeKeysForUpdate = [
                        'sample_code',
                        'sample_code_key',
                        'sample_code_format',
                        'sample_batch_id',
                        'sample_received_at_lab_datetime',
                        'lab_id',
                        'sample_condition',
                        'lab_technician',
                        'testing_point',
                        'is_sample_rejected',
                        'result',
                        'result_sent_to_source',
                        'other_diseases',
                        'tested_by',
                        'result_approved_by',
                        'result_approved_datetime',
                        'is_result_authorised',
                        'authorized_by',
                        'authorized_on',
                        'revised_by',
                        'revised_on',
                        'result_reviewed_by',
                        'result_reviewed_datetime',
                        'reason_for_changing',
                        'rejection_on',
                        'result_status',
                        'data_sync',
                        'reason_for_sample_rejection',
                        'last_modified_by',
                        'result_printed_datetime',
                        'result_printed_on_sts_datetime',
                        'result_dispatched_datetime',
                        'last_modified_datetime',
                        'data_from_comorbidities',
                        'data_from_symptoms',
                        'data_from_tests'
                    ];

                    $request = MiscUtility::excludeKeys($request, $removeKeysForUpdate);

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] ??= 'no';

                    $covid19Id = $localRecord[$primaryKeyName];
                    if (MiscUtility::isArrayEqual($request, $localRecord, ['last_modified_datetime', 'form_attributes'])) {
                        $id = true;
                    } else {
                        if ($isSilent) {
                            unset($request['last_modified_datetime']);
                        }
                        $db->where($primaryKeyName, $localRecord[$primaryKeyName]);
                        $id = $db->update($tableName, $request);
                    }
                } else {
                    if (!empty($request['sample_collection_date'])) {
                        $request['source_of_request'] = "vlsts";
                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] ??= 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;
                        $id = $db->insert($tableName, $request);
                        $covid19Id = $db->getInsertId();
                    }
                }
                // Symptoms
                if (isset($remoteData['data_from_symptoms']) && !empty($remoteData['data_from_symptoms'])) {
                    $db->where($primaryKeyName, $covid19Id);
                    $db->delete("covid19_patient_symptoms");
                    foreach ($remoteData['data_from_symptoms'] as $symId => $value) {
                        $symptomData = [];
                        $symptomData["covid19_id"] = $covid19Id;
                        $symptomData["symptom_id"] = $value['symptom_id'];
                        $symptomData["symptom_detected"] = $value['symptom_detected'];
                        $symptomData["symptom_details"] = $value['symptom_details'];
                        $db->insert("covid19_patient_symptoms", $symptomData);
                    }
                }
                // comorbidities
                if (isset($remoteData['data_from_comorbidities']) && !empty($remoteData['data_from_comorbidities'])) {
                    $db->where($primaryKeyName, $covid19Id);
                    $db->delete("covid19_patient_comorbidities");
                    foreach ($remoteData['data_from_comorbidities'] as $comoId => $comorbidityData) {
                        $comData = [];
                        $comData["covid19_id"] = $covid19Id;
                        $comData["comorbidity_id"] = $comorbidityData['comorbidity_id'];
                        $comData["comorbidity_detected"] = $comorbidityData['comorbidity_detected'];
                        $db->insert("covid19_patient_comorbidities", $comData);
                    }
                }
                // sub tests
                if (isset($remoteData['data_from_tests']) && !empty($remoteData['data_from_tests'])) {
                    $db->where($primaryKeyName, $covid19Id);
                    $db->delete("covid19_tests");
                    foreach ($remoteData['data_from_tests'] as $covid19Id => $cdata) {
                        $covid19TestData = [
                            "covid19_id" => $covid19Id,
                            "facility_id" => $cdata['facility_id'],
                            "test_name" => $cdata['test_name'],
                            "tested_by" => $cdata['tested_by'],
                            "sample_tested_datetime" => $cdata['sample_tested_datetime'],
                            "testing_platform" => $cdata['testing_platform'],
                            "instrument_id" => $cdata['instrument_id'],
                            "kit_lot_no" => $cdata['kit_lot_no'],
                            "kit_expiry_date" => $cdata['kit_expiry_date'],
                            "result" => $cdata['result'],
                            "updated_datetime" => $cdata['updated_datetime']
                        ];
                        $db->insert("covid19_tests", $covid19TestData);
                    }
                }
                if ($id === true) {
                    $successCounter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getMessage(), [
                    'error_id' => MiscUtility::generateErrorId(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'local_unique_id' => $localRecord['unique_id'] ?? null,
                    'received_unique_id' => $request['unique_id'] ?? null,
                    'local_sample_code' => $localRecord['sample_code'] ?? null,
                    'received_sample_code' => $request['sample_code'] ?? null,
                    'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                    'received_remote_sample_code' => $request['remote_sample_code'] ?? null,
                    'local_facility_id' => $localRecord['facility_id'] ?? null,
                    'received_facility_id' => $request['facility_id'] ?? null,
                    'local_lab_id' => $localRecord['lab_id'] ?? null,
                    'received_lab_id' => $request['lab_id'] ?? null,
                    'local_result' => $localRecord['result'] ?? null,
                    'received_result' => $request['result'] ?? null,
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }

            if ($cliMode) {
                spinner($loopIndex, $successCounter);
            }
            $loopIndex++;
        }
        if ($cliMode) {

            clearSpinner();
            echo PHP_EOL;
            echo "Synced $successCounter Covid-19 record(s)" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $successCounter, 'receive-requests', 'covid19', $url, $payload, $responsePayload['covid19'], 'json', $labId);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
}

/*
****************************************************************
* Hepatitis TEST REQUESTS
****************************************************************
*/

try {
    $request = [];
    if (!empty($responsePayload['hepatitis']) && $responsePayload['hepatitis'] != '[]' && JsonUtility::isJSON($responsePayload['hepatitis'])) {
        $primaryKeyName = TestsService::getTestPrimaryKeyColumn('hepatitis');
        $tableName = TestsService::getTestTableName('hepatitis');

        if ($cliMode) {
            echo PHP_EOL;
            echo "=========================" . PHP_EOL;
            echo "Processing for Hepatitis" . PHP_EOL;
        }

        $options = [
            'pointer' => '/requests',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($responsePayload['hepatitis'], $options);


        $removeKeys = [
            $primaryKeyName,
            'sample_batch_id',
            'result',
            'hcv_vl_count',
            'hbv_vl_count',
            'sample_tested_datetime',
            'sample_received_at_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'last_modified_by',
            'request_created_datetime',
            'data_sync'
        ];

        $localDbFieldArray = $general->getTableFieldsAsArray($tableName, $removeKeys);

        $loopIndex = 0;
        $successCounter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();

                // Overwrite the values in $localDbFieldArray with the values in $originalLISRecord
                // basically we are making sure that we only use columns that are present in the $localDbFieldArray
                // which is from local db and not using the ones in the $originalLISRecord
                $request = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $remoteData);

                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                $columns = array_diff(array_keys($request), [$primaryKeyName]);
                $columnsForSelect = implode(', ', $columns);
                $query = "SELECT $primaryKeyName, {$columnsForSelect} FROM $tableName";

                $localRecord = $testRequestsService->findMatchingLocalRecord($request,  $tableName, $primaryKeyName);
                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();


                if (!empty($localRecord)) {

                    $removeKeysForUpdate = [
                        'sample_code',
                        'sample_code_key',
                        'sample_code_format',
                        'sample_batch_id',
                        'sample_received_at_lab_datetime',
                        'lab_id',
                        'sample_condition',
                        'sample_tested_datetime',
                        'vl_testing_site',
                        'is_sample_rejected',
                        'result',
                        'hcv_vl_count',
                        'hbv_vl_count',
                        'hepatitis_test_platform',
                        'import_machine_name',
                        'is_result_authorised',
                        'result_reviewed_by',
                        'result_reviewed_datetime',
                        'authorized_by',
                        'authorized_on',
                        'revised_by',
                        'revised_on',
                        'result_status',
                        'result_sent_to_source',
                        'data_sync',
                        'last_modified_by',
                        'last_modified_datetime',
                        'result_printed_datetime',
                        'result_printed_on_sts_datetime',
                        'result_dispatched_datetime',
                        'reason_for_vl_test',
                        'data_from_comorbidities',
                        'data_from_risks'
                    ];

                    $request = MiscUtility::excludeKeys($request, $removeKeysForUpdate);

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] ??= 'no';

                    $hepatitisId = $localRecord[$primaryKeyName];
                    if (MiscUtility::isArrayEqual($request, $localRecord, ['last_modified_datetime', 'form_attributes'])) {
                        $id = true;
                    } else {
                        if ($isSilent) {
                            unset($request['last_modified_datetime']);
                        }
                        $db->where($primaryKeyName, $localRecord[$primaryKeyName]);
                        $id = $db->update($tableName, $request);
                    }
                } else {
                    if (!empty($request['sample_collection_date'])) {
                        $request['source_of_request'] = "vlsts";
                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] ??= 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;

                        $id = $db->insert($tableName, $request);
                        $hepatitisId = $db->getInsertId();
                    }
                }

                foreach ($remoteData['data_from_risks'] as $dataRiskId => $risks) {
                    $db->where($primaryKeyName, $hepatitisId);
                    $db->delete("hepatitis_risk_factors");


                    foreach ($risks as  $riskId => $riskValue) {
                        $riskFactorsData = [];
                        $riskFactorsData["hepatitis_id"] = $hepatitisId;
                        $riskFactorsData["riskfactors_id"] = $riskId;
                        $riskFactorsData["riskfactors_detected"] = $riskValue;
                        $db->insert("hepatitis_risk_factors", $riskFactorsData);
                    }
                }
                foreach ($remoteData['data_from_comorbidities'] as $dataComorbitityId => $comorbidities) {
                    $db->where($primaryKeyName, $hepatitisId);
                    $db->delete("hepatitis_patient_comorbidities");

                    foreach ($comorbidities as $comoId => $comoValue) {
                        $comorbidityData = [];
                        $comorbidityData["hepatitis_id"] = $hepatitisId;
                        $comorbidityData["comorbidity_id"] = $comoId;
                        $comorbidityData["comorbidity_detected"] = $comoValue;
                        $db->insert('hepatitis_patient_comorbidities', $comorbidityData);
                    }
                }
                if ($id === true) {
                    $successCounter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                    'error_id' => MiscUtility::generateErrorId(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'local_unique_id' => $localRecord['unique_id'] ?? null,
                    'received_unique_id' => $request['unique_id'] ?? null,
                    'local_sample_code' => $localRecord['sample_code'] ?? null,
                    'received_sample_code' => $request['sample_code'] ?? null,
                    'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                    'received_remote_sample_code' => $request['remote_sample_code'] ?? null,
                    'local_facility_id' => $localRecord['facility_id'] ?? null,
                    'received_facility_id' => $request['facility_id'] ?? null,
                    'local_lab_id' => $localRecord['lab_id'] ?? null,
                    'received_lab_id' => $request['lab_id'] ?? null,
                    'local_result' => $localRecord['result'] ?? null,
                    'received_result' => $request['result'] ?? null,
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }

            if ($cliMode) {
                spinner($loopIndex, $successCounter);
            }
            $loopIndex++;
        }
        if ($cliMode) {
            clearSpinner();
            echo PHP_EOL;
            echo "Synced $successCounter Hepatitis record(s)" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $successCounter, 'receive-requests', 'hepatitis', $url, $payload, $responsePayload['hepatitis'], 'json', $labId);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
}
/*
****************************************************************
* TB TEST REQUESTS
****************************************************************
*/

try {
    $request = [];
    if (!empty($responsePayload['tb']) && $responsePayload['tb'] != '[]' && JsonUtility::isJSON($responsePayload['tb'])) {
        $primaryKeyName = TestsService::getTestPrimaryKeyColumn('tb');
        $tableName = TestsService::getTestTableName('tb');


        if ($cliMode) {
            echo PHP_EOL;
            echo "=========================" . PHP_EOL;
            echo "Processing for TB" . PHP_EOL;
        }

        $options = [
            'pointer' => '/requests',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($responsePayload['tb'], $options);


        $removeKeys = [
            $primaryKeyName,
            'sample_batch_id',
            'result',
            'xpert_mtb_result',
            'sample_tested_datetime',
            'sample_received_at_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'last_modified_by',
            'request_created_datetime',
            'data_sync'
        ];

        $localDbFieldArray = $general->getTableFieldsAsArray($tableName, $removeKeys);

        $loopIndex = 0;
        $successCounter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();

                // Overwrite the values in $localDbFieldArray with the values in $originalLISRecord
                // basically we are making sure that we only use columns that are present in the $localDbFieldArray
                // which is from local db and not using the ones in the $originalLISRecord
                $request = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $remoteData);

                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                $columns = array_diff(array_keys($request), [$primaryKeyName]);
                $columnsForSelect = implode(', ', $columns);
                $query = "SELECT $primaryKeyName, {$columnsForSelect} FROM $tableName";

                $localRecord = $testRequestsService->findMatchingLocalRecord($request,  $tableName, $primaryKeyName);
                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();


                if (!empty($localRecord)) {

                    $removeKeysForUpdate = [
                        'sample_code',
                        'sample_code_key',
                        'sample_code_format',
                        'sample_batch_id',
                        'specimen_quality',
                        'lab_id',
                        'reason_for_tb_test',
                        'tests_requested',
                        'specimen_type',
                        'sample_collection_date',
                        'sample_received_at_lab_datetime',
                        'is_sample_rejected',
                        'result',
                        'xpert_mtb_result',
                        'result_sent_to_source',
                        'result_dispatched_datetime',
                        'result_reviewed_by',
                        'result_reviewed_datetime',
                        'result_approved_by',
                        'result_approved_datetime',
                        'sample_tested_datetime',
                        'tested_by',
                        'rejection_on',
                        'result_status',
                        'data_sync',
                        'reason_for_sample_rejection',
                        'sample_registered_at_lab',
                        'last_modified_by',
                        'last_modified_datetime',
                        'last_modified_by',
                        'lab_technician',
                        'result_printed_datetime',
                        'result_printed_on_sts_datetime',
                    ];

                    $request = MiscUtility::excludeKeys($request, $removeKeysForUpdate);

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] ??= 'no';

                    $tbId = $localRecord[$primaryKeyName];
                    if (MiscUtility::isArrayEqual($request, $localRecord, ['last_modified_datetime', 'form_attributes'])) {
                        $id = true;
                    } else {
                        if ($isSilent) {
                            unset($request['last_modified_datetime']);
                        }
                        $db->where($primaryKeyName, $localRecord[$primaryKeyName]);
                        $id = $db->update($tableName, $request);
                    }
                } else {
                    if (!empty($request['sample_collection_date'])) {

                        $request['source_of_request'] = "vlsts";
                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] ??= 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;

                        $id = $db->insert($tableName, $request);
                        $tbId = $db->getInsertId();
                    }
                }
                if ($id === true) {
                    $successCounter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                    'error_id' => MiscUtility::generateErrorId(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'local_unique_id' => $localRecord['unique_id'] ?? null,
                    'received_unique_id' => $request['unique_id'] ?? null,
                    'local_sample_code' => $localRecord['sample_code'] ?? null,
                    'received_sample_code' => $request['sample_code'] ?? null,
                    'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                    'received_remote_sample_code' => $request['remote_sample_code'] ?? null,
                    'local_facility_id' => $localRecord['facility_id'] ?? null,
                    'received_facility_id' => $request['facility_id'] ?? null,
                    'local_lab_id' => $localRecord['lab_id'] ?? null,
                    'received_lab_id' => $request['lab_id'] ?? null,
                    'local_result' => $localRecord['result'] ?? null,
                    'received_result' => $request['result'] ?? null,
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }

            if ($cliMode) {
                spinner($loopIndex, $successCounter);
            }
            $loopIndex++;
        }
        if ($cliMode) {
            clearSpinner();
            echo PHP_EOL;
            echo "Synced $successCounter TB record(s)" . PHP_EOL;
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $successCounter, 'receive-requests', 'tb', $url, $payload, $responsePayload['tb'], 'json', $labId);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
}


/*
****************************************************************
* CD4 TEST REQUESTS
****************************************************************
*/
try {
    $request = [];
    if (!empty($responsePayload['cd4']) && $responsePayload['cd4'] != '[]' && JsonUtility::isJSON($responsePayload['cd4'])) {
        $primaryKeyName = TestsService::getTestPrimaryKeyColumn('cd4');
        $tableName = TestsService::getTestTableName('cd4');

        if ($cliMode) {
            echo PHP_EOL;
            echo "=========================" . PHP_EOL;
            echo "Processing for CD4" . PHP_EOL;
        }

        $options = [
            'pointer' => '/requests',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($responsePayload['cd4'], $options);

        $removeKeys = [
            $primaryKeyName,
            'sample_batch_id',
            'cd4_result',
            'sample_tested_datetime',
            'sample_received_at_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'data_sync'
        ];

        $localDbFieldArray = $general->getTableFieldsAsArray($tableName, $removeKeys);

        $loopIndex = 0;
        $successCounter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();

                // Overwrite the values in $localDbFieldArray with the values in $originalLISRecord
                // basically we are making sure that we only use columns that are present in the $localDbFieldArray
                // which is from local db and not using the ones in the $originalLISRecord
                $request = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $remoteData);

                $localRecord = $testRequestsService->findMatchingLocalRecord($request,  $tableName, $primaryKeyName);
                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                if (!empty($localRecord)) {

                    $removeKeysForUpdate = [
                        'sample_code',
                        'sample_code_key',
                        'sample_code_format',
                        'sample_batch_id',
                        'lab_id',
                        'cd4_test_platform',
                        'sample_received_at_hub_datetime',
                        'sample_received_at_lab_datetime',
                        'sample_tested_datetime',
                        'result_dispatched_datetime',
                        'is_sample_rejected',
                        'reason_for_sample_rejection',
                        'rejection_on',
                        'cd4_result',
                        'result_reviewed_by',
                        'result_reviewed_datetime',
                        'cd4_focal_person',
                        'cd4_focal_person_phone_number',
                        'tested_by',
                        'result_approved_by',
                        'result_approved_datetime',
                        'lab_tech_comments',
                        'reason_for_result_changes',
                        'revised_by',
                        'revised_on',
                        'last_modified_by',
                        'last_modified_datetime',
                        'manual_result_entry',
                        'result_status',
                        'data_sync',
                        'result_printed_datetime',
                        'result_printed_on_sts_datetime',
                    ];

                    $request = MiscUtility::excludeKeys($request, $removeKeysForUpdate);

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] ??= 'no';
                    if (MiscUtility::isArrayEqual($request, $localRecord, ['last_modified_datetime', 'form_attributes'])) {
                        $id = true;
                    } else {
                        if ($isSilent) {
                            unset($request['last_modified_datetime']);
                        }
                        $db->where($primaryKeyName, $localRecord[$primaryKeyName]);
                        $id = $db->update($tableName, $request);
                    }
                } else {
                    $request['source_of_request'] = 'vlsts';
                    if (!empty($request['sample_collection_date'])) {

                        $request['source_of_request'] = "vlsts";
                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] ??= 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;
                        $id = $db->insert($tableName, $request);
                    }
                }
                if ($id === true) {
                    $successCounter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                    'error_id' => MiscUtility::generateErrorId(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'local_unique_id' => $localRecord['unique_id'] ?? null,
                    'received_unique_id' => $request['unique_id'] ?? null,
                    'local_sample_code' => $localRecord['sample_code'] ?? null,
                    'received_sample_code' => $request['sample_code'] ?? null,
                    'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                    'received_remote_sample_code' => $request['remote_sample_code'] ?? null,
                    'local_facility_id' => $localRecord['facility_id'] ?? null,
                    'received_facility_id' => $request['facility_id'] ?? null,
                    'local_lab_id' => $localRecord['lab_id'] ?? null,
                    'received_lab_id' => $request['lab_id'] ?? null,
                    'local_result' => $localRecord['result'] ?? null,
                    'received_result' => $request['result'] ?? null,
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }

            if ($cliMode) {
                spinner($loopIndex, $successCounter);
            }
            $loopIndex++;
        }
        if ($cliMode) {
            clearSpinner();
            echo PHP_EOL;
            echo "Synced $successCounter CD4 record(s)" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $successCounter, 'receive-requests', 'cd4', $url, $payload, $responsePayload['cd4'], 'json', $labId);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
}
/*
****************************************************************
* GENERIC TEST REQUESTS
****************************************************************
*/
try {
    $request = [];
    if (!empty($responsePayload['generic-tests']) && $responsePayload['generic-tests'] != '[]' && JsonUtility::isJSON($responsePayload['generic-tests'])) {
        $primaryKeyName = TestsService::getTestPrimaryKeyColumn('generic-tests');
        $tableName = TestsService::getTestTableName('generic-tests');

        if ($cliMode) {
            echo PHP_EOL;
            echo "=========================" . PHP_EOL;
            echo "Processing for Custom Tests" . PHP_EOL;
        }

        $options = [
            'pointer' => '/requests',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($responsePayload['generic-tests'], $options);

        $removeKeys = [
            $primaryKeyName,
            'sample_batch_id',
            'result',
            'sample_tested_datetime',
            'sample_received_at_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'data_sync'
        ];

        $localDbFieldArray = $general->getTableFieldsAsArray($tableName, $removeKeys);

        $loopIndex = 0;
        $successCounter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();

                // Overwrite the values in $localDbFieldArray with the values in $originalLISRecord
                // basically we are making sure that we only use columns that are present in the $localDbFieldArray
                // which is from local db and not using the ones in the $originalLISRecord
                $request = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $remoteData);

                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                $localRecord = $testRequestsService->findMatchingLocalRecord($request,  $tableName, $primaryKeyName);
                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                if (!empty($localRecord)) {

                    $removeKeysForUpdate = [
                        'sample_code',
                        'sample_code_key',
                        'sample_code_format',
                        'sample_batch_id',
                        'lab_id',
                        'vl_test_platform',
                        'sample_received_at_hub_datetime',
                        'sample_received_at_lab_datetime',
                        'sample_tested_datetime',
                        'result_dispatched_datetime',
                        'is_sample_rejected',
                        'reason_for_sample_rejection',
                        'rejection_on',
                        'result',
                        'result_reviewed_by',
                        'result_reviewed_datetime',
                        'tested_by',
                        'result_approved_by',
                        'result_approved_datetime',
                        'lab_tech_comments',
                        'reason_for_test_result_changes',
                        'revised_by',
                        'revised_on',
                        'last_modified_by',
                        'last_modified_datetime',
                        'manual_result_entry',
                        'result_status',
                        'data_sync',
                        'result_printed_datetime',
                        'result_printed_on_sts_datetime',
                        'data_from_tests'
                    ];

                    $request = MiscUtility::excludeKeys($request, $removeKeysForUpdate);

                    $testTypeForm = JsonUtility::jsonToSetString(
                        $localRecord['test_type_form'],
                        'test_type_form',
                        $request['test_type_form'],
                    );
                    $request['test_type_form'] = !empty($testTypeForm) ? $db->func($testTypeForm) : null;

                    $formAttributes = JsonUtility::jsonToSetString(
                        $localRecord['form_attributes'],
                        'form_attributes',
                        $request['form_attributes'],
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] ??= 'no';

                    $genericId = $localRecord[$primaryKeyName];
                    if (MiscUtility::isArrayEqual($request, $localRecord, ['last_modified_datetime', 'form_attributes'])) {
                        $id = true;
                    } else {
                        if ($isSilent) {
                            unset($request['last_modified_datetime']);
                        }
                        $db->where($primaryKeyName, $localRecord[$primaryKeyName]);
                        $id = $db->update($tableName, $request);
                    }
                } else {
                    $request['source_of_request'] = 'vlsts';
                    if (!empty($request['sample_collection_date'])) {

                        $testTypeForm = JsonUtility::jsonToSetString(
                            $request['test_type_form'],
                            'test_type_form'
                        );
                        $request['test_type_form'] = !empty($testTypeForm) ? $db->func($testTypeForm) : null;

                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] ??= 'no';

                        $request['source_of_request'] = "vlsts";
                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;
                        $id = $db->insert($tableName, $request);
                        $genericId = $db->getInsertId();
                    }
                }
                if (isset($remoteData['data_from_tests']) && !empty($remoteData['data_from_tests'])) {
                    $db->where('generic_id', $genericId);
                    $db->delete("generic_test_results");
                    foreach ($remoteData['data_from_tests'] as $genericTestData) {
                        $db->insert("generic_test_results", [
                            "generic_id" => $genericId,
                            "facility_id" => $genericTestData['facility_id'],
                            "sub_test_name" => $genericTestData['sub_test_name'],
                            "final_result_unit" => $genericTestData['final_result_unit'],
                            "result_type" => $genericTestData['result_type'],
                            "test_name" => $genericTestData['test_name'],
                            "tested_by" => $genericTestData['tested_by'],
                            "sample_tested_datetime" => $genericTestData['sample_tested_datetime'],
                            "testing_platform" => $genericTestData['testing_platform'],
                            "kit_lot_no" => $genericTestData['kit_lot_no'],
                            "kit_expiry_date" => $genericTestData['kit_expiry_date'],
                            "result" => $genericTestData['result'],
                            "final_result" => $genericTestData['final_result'],
                            "result_unit" => $genericTestData['result_unit'],
                            "final_result_interpretation" => $genericTestData['final_result_interpretation'],
                            "updated_datetime" => $genericTestData['updated_datetime']
                        ]);
                    }
                }
                if ($id === true) {
                    $successCounter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                    'error_id' => MiscUtility::generateErrorId(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'local_unique_id' => $localRecord['unique_id'] ?? null,
                    'received_unique_id' => $request['unique_id'] ?? null,
                    'local_sample_code' => $localRecord['sample_code'] ?? null,
                    'received_sample_code' => $request['sample_code'] ?? null,
                    'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                    'received_remote_sample_code' => $request['remote_sample_code'] ?? null,
                    'local_facility_id' => $localRecord['facility_id'] ?? null,
                    'received_facility_id' => $request['facility_id'] ?? null,
                    'local_lab_id' => $localRecord['lab_id'] ?? null,
                    'received_lab_id' => $request['lab_id'] ?? null,
                    'local_result' => $localRecord['result'] ?? null,
                    'received_result' => $request['result'] ?? null,
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }

            if ($cliMode) {
                spinner($loopIndex, $successCounter);
            }
            $loopIndex++;
        }

        if ($cliMode) {
            clearSpinner();
            echo PHP_EOL;
            echo "Synced $successCounter Custom Tests record(s)" . PHP_EOL;
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $successCounter, 'receive-requests', 'generic-tests', $url, $payload, $responsePayload['generic-tests'], 'json', $labId);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
}


$db->where('vlsm_instance_id', $general->getInstanceId());
$db->update('s_vlsm_instance', ['last_remote_requests_sync' => DateUtility::getCurrentDateTime()]);

if (
    isset($forceSyncModule) && trim((string) $forceSyncModule) != ""
    && isset($manifestCode) && trim((string) $manifestCode) != ""
) {
    $formTable = TestsService::getTestTableName($forceSyncModule);
    $primaryKey = TestsService::getTestPrimaryKeyColumn($forceSyncModule);
    $db->where("sample_package_code", $manifestCode);
    $sampleData = $db->getValue($formTable, $primaryKey, null);
    echo JsonUtility::encodeUtf8Json($sampleData);
}
