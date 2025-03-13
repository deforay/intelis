<?php
//this file gets the requests from the remote server and updates the local database

$cliMode = php_sapi_name() === 'cli';
if ($cliMode) {
    require_once __DIR__ . "/../../../bootstrap.php";
    echo PHP_EOL;
    echo "=========================" . PHP_EOL;
    echo "Starting test requests sync" . PHP_EOL;
}

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

use JsonMachine\Items;
use App\Services\ApiService;
use GuzzleHttp\Promise\Utils;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);


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
    LoggerUtility::logError("No internet connectivity while trying remote sync.");
    return false;
}

//get remote data
if (empty($labId)) {
    echo "No Lab ID set in System Config";
    exit(0);
}
$forceSyncModule = !empty($_GET['forceSyncModule']) ? $_GET['forceSyncModule'] : null;
$manifestCode = !empty($_GET['manifestCode']) ? $_GET['manifestCode'] : null;

// if only one module is getting synced, lets only sync that one module
if (!empty($forceSyncModule)) {
    unset($systemConfig['modules']);
    $systemConfig['modules'][$forceSyncModule] = true;
}

$stsBearerToken = $general->getSTSToken();

$apiService->setBearerToken($stsBearerToken);

$basePayload = [
    'labId' => $labId,
    'transactionId' => $transactionId
];

$promises = [];

// Record the start time of the entire process
$startTime = microtime(true);

$responsePayload = [];
foreach ($systemConfig['modules'] as $module => $status) {
    if ($status === true) {
        $basePayload['testType'] = $module;
        if (!empty($forceSyncModule) && trim((string) $forceSyncModule) == $module && !empty($manifestCode) && trim((string) $manifestCode) != "") {
            $basePayload['manifestCode'] = $manifestCode;
        }
        $promises[$module] = $apiService->post(
            "$remoteURL/remote/v2/requests.php",
            $basePayload,
            gzip: true,
            async: true
        )->then(function ($response) use (&$responsePayload, $module, $cliMode) {
            $responsePayload[$module] = $response->getBody()->getContents();
            if ($cliMode) {
                echo "Received $module" . PHP_EOL;
            }
        })->otherwise(function ($reason) use ($module, $cliMode) {
            if ($cliMode) {
                echo "STS Request sync for $module failed: " . $reason . PHP_EOL;
            }
            LoggerUtility::logError(__FILE__ . ":" . __LINE__ . ":" . "STS Request sync for $module failed: " . $reason);
        });
    }
}

// Wait for all promises to settle
Utils::settle($promises)->wait();

// Record the end time of the entire process
$endTime = microtime(true);

// Print the total execution time
echo "Total download time for STS Requests: " . ($endTime - $startTime) . " seconds" . PHP_EOL;


/*
****************************************************************
* HIV VL TEST REQUESTS
****************************************************************
*/
try {
    $request = [];
    if (!empty($responsePayload['vl']) && $responsePayload['vl'] != '[]' && JsonUtility::isJSON($responsePayload['vl'])) {

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
            'vl_sample_id',
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

        $emptyLabArray = $general->getTableFieldsAsArray('form_vl', $removeKeys);

        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();
                $request = MiscUtility::updateFromArray($emptyLabArray, $remoteData);

                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                $existingSampleQuery = "SELECT vl_sample_id, sample_code, IFNULL(locked, 'no') as locked
                                        FROM form_vl AS vl
                                        WHERE unique_id =? OR remote_sample_code=? OR (sample_code=? AND lab_id=?)";
                $existingSampleResult = $db->rawQueryOne($existingSampleQuery, [$request['unique_id'], $request['remote_sample_code'], $request['sample_code'], $request['lab_id']]);
                if (!empty($existingSampleResult)) {

                    $removeMoreKeys = [
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
                        'vl_result_category'
                    ];

                    $request = array_diff_key($request, array_flip($removeMoreKeys));

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] = 'no';

                    $db->where('vl_sample_id', $existingSampleResult['vl_sample_id']);
                    $id = $db->update('form_vl', $request);
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
                        $request['is_result_mail_sent'] = 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;
                        $id = $db->insert('form_vl', $request);
                    }
                }
                if ($id === true) {
                    $counter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }
        if ($cliMode) {
            echo "Synced $counter VL record(s)" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'vl', $url, $payload, $responsePayload['vl'], 'json', $labId);
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
            'eid_id',
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

        $emptyLabArray = $general->getTableFieldsAsArray('form_eid', $removeKeys);

        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $request = MiscUtility::updateFromArray($emptyLabArray, $remoteData);


                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                $existingSampleQuery = "SELECT eid_id,sample_code FROM form_eid AS vl
                                        WHERE unique_id =? OR remote_sample_code=? OR (sample_code=? AND lab_id=?)";
                $existingSampleResult = $db->rawQueryOne($existingSampleQuery, [$request['unique_id'], $request['remote_sample_code'], $request['sample_code'], $request['lab_id']]);
                if (!empty($existingSampleResult)) {

                    $removeMoreKeys = [
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
                        'last_modified_datetime'
                    ];

                    $request = array_diff_key($request, array_flip($removeMoreKeys));

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] = 'no';

                    $db->where('eid_id', $existingSampleResult['eid_id']);
                    $id = $db->update('form_eid', $request);
                } else {
                    if (!empty($request['sample_collection_date'])) {

                        $request['source_of_request'] = "vlsts";
                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] = 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;

                        $id = $db->insert('form_eid', $request);
                    }
                }
                if ($id === true) {
                    $counter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
                LoggerUtility::logError($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                continue;
            }
        }
        if ($cliMode) {
            echo "Synced $counter EID record(s)" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'eid', $url, $payload, $responsePayload['eid'], 'json', $labId);
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
            'covid19_id',
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

        $emptyLabArray = $general->getTableFieldsAsArray('form_covid19', $removeKeys);

        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();
                $request = MiscUtility::updateFromArray($emptyLabArray, $remoteData);

                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                //check exist remote
                $existingSampleQuery = "SELECT covid19_id,sample_code FROM form_covid19 AS vl
                                        WHERE unique_id =? OR remote_sample_code=? OR (sample_code=? AND lab_id=?)";
                $existingSampleResult = $db->rawQueryOne($existingSampleQuery, [$request['unique_id'], $request['remote_sample_code'], $request['sample_code'], $request['lab_id']]);
                if (!empty($existingSampleResult)) {

                    $removeMoreKeys = [
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
                        'result_dispatched_datetime',
                        'last_modified_datetime',
                        'data_from_comorbidities',
                        'data_from_symptoms',
                        'data_from_tests'
                    ];

                    $request = array_diff_key($request, array_flip($removeMoreKeys));

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] = 'no';

                    $db->where('covid19_id', $existingSampleResult['covid19_id']);
                    $db->update('form_covid19', $request);
                    $id = $existingSampleResult['covid19_id'];
                } else {
                    if (!empty($request['sample_collection_date'])) {
                        $request['source_of_request'] = "vlsts";
                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] = 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;
                        $db->insert('form_covid19', $request);
                        $id = $db->getInsertId();
                    }
                }
                // Symptoms
                if (isset($remoteData['data_from_symptoms']) && !empty($remoteData['data_from_symptoms'])) {
                    $db->where('covid19_id', $id);
                    $db->delete("covid19_patient_symptoms");
                    foreach ($remoteData['data_from_symptoms'] as $symId => $value) {
                        $symptomData = [];
                        $symptomData["covid19_id"] = $id;
                        $symptomData["symptom_id"] = $value['symptom_id'];
                        $symptomData["symptom_detected"] = $value['symptom_detected'];
                        $symptomData["symptom_details"] = $value['symptom_details'];
                        $db->insert("covid19_patient_symptoms", $symptomData);
                    }
                }
                // comorbidities
                if (isset($remoteData['data_from_comorbidities']) && !empty($remoteData['data_from_comorbidities'])) {
                    $db->where('covid19_id', $id);
                    $db->delete("covid19_patient_comorbidities");
                    foreach ($remoteData['data_from_comorbidities'] as $comoId => $comorbidityData) {
                        $comData = [];
                        $comData["covid19_id"] = $id;
                        $comData["comorbidity_id"] = $comorbidityData['comorbidity_id'];
                        $comData["comorbidity_detected"] = $comorbidityData['comorbidity_detected'];
                        $db->insert("covid19_patient_comorbidities", $comData);
                    }
                }
                // sub tests
                if (isset($remoteData['data_from_tests']) && !empty($remoteData['data_from_tests'])) {
                    $db->where('covid19_id', $id);
                    $db->delete("covid19_tests");
                    foreach ($remoteData['data_from_tests'] as $covid19Id => $cdata) {
                        $covid19TestData = [
                            "covid19_id" => $id,
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
                    $counter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
                LoggerUtility::logError($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                continue;
            }
        }
        if ($cliMode) {
            echo "Synced $counter Covid-19 record(s)" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'covid19', $url, $payload, $responsePayload['covid19'], 'json', $labId);
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
            'hepatitis_id',
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

        $emptyLabArray = $general->getTableFieldsAsArray('form_hepatitis', $removeKeys);

        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();
                $request = MiscUtility::updateFromArray($emptyLabArray, $remoteData);

                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                //check exist remote
                $existingSampleQuery = "SELECT hepatitis_id,sample_code FROM form_hepatitis AS vl
                                                                                WHERE unique_id =? OR remote_sample_code=? OR (sample_code=? AND lab_id=?)";
                $existingSampleResult = $db->rawQueryOne($existingSampleQuery, [$request['unique_id'], $request['remote_sample_code'], $request['sample_code'], $request['lab_id']]);
                if (!empty($existingSampleResult)) {

                    $removeMoreKeys = [
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
                        'result_dispatched_datetime',
                        'reason_for_vl_test',
                        'data_from_comorbidities',
                        'data_from_risks'
                    ];

                    $request = array_diff_key($request, array_flip($removeMoreKeys));

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] = 'no';

                    $db->where('hepatitis_id', $existingSampleResult['hepatitis_id']);
                    $db->update('form_hepatitis', $request);
                    $id = $existingSampleResult['hepatitis_id'];
                } else {
                    if (!empty($request['sample_collection_date'])) {
                        $request['source_of_request'] = "vlsts";
                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] = 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;

                        $db->insert('form_hepatitis', $request);
                        $id = $db->getInsertId();
                    }
                }

                foreach ($remoteData['data_from_risks'] as $hepatitisId => $risks) {
                    $db->where('hepatitis_id', $hepatitisId);
                    $db->delete("hepatitis_risk_factors");


                    foreach ($risks as  $riskId => $riskValue) {
                        $riskFactorsData = [];
                        $riskFactorsData["hepatitis_id"] = $hepatitisId;
                        $riskFactorsData["riskfactors_id"] = $riskId;
                        $riskFactorsData["riskfactors_detected"] = $riskValue;
                        $db->insert("hepatitis_risk_factors", $riskFactorsData);
                    }
                }
                foreach ($remoteData['data_from_comorbidities'] as $hepatitisId => $comorbidities) {
                    $db->where('hepatitis_id', $hepatitisId);
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
                    $counter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }
        if ($cliMode) {
            echo "Synced $counter Hepatitis record(s)" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'hepatitis', $url, $payload, $responsePayload['hepatitis'], 'json', $labId);
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
            'tb_id',
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

        $emptyLabArray = $general->getTableFieldsAsArray('form_tb', $removeKeys);

        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();
                $request = MiscUtility::updateFromArray($emptyLabArray, $remoteData);

                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                //check exist remote
                $existingSampleQuery = "SELECT tb_id,sample_code FROM form_tb AS vl
                                        WHERE unique_id =? OR remote_sample_code=? OR (sample_code=? AND lab_id=?)";
                $existingSampleResult = $db->rawQueryOne($existingSampleQuery, [$request['unique_id'], $request['remote_sample_code'], $request['sample_code'], $request['lab_id']]);
                if (!empty($existingSampleResult)) {

                    $removeMoreKeys = [
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
                        'lab_technician'
                    ];

                    $request = array_diff_key($request, array_flip($removeMoreKeys));

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] = 'no';

                    $db->where('tb_id', $existingSampleResult['tb_id']);
                    $db->update('form_tb', $request);
                    $id = $existingSampleResult['tb_id'];
                } else {
                    if (!empty($request['sample_collection_date'])) {

                        $request['source_of_request'] = "vlsts";
                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'],
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                        $request['is_result_mail_sent'] = 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;

                        $db->insert('form_tb', $request);
                        $id = $db->getInsertId();
                    }
                }
                if ($id === true) {
                    $counter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }
        if ($cliMode) {
            echo "Synced $counter TB record(s)" . PHP_EOL;
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'tb', $url, $payload, $responsePayload['tb'], 'json', $labId);
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
            'cd4_id',
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

        $emptyLabArray = $general->getTableFieldsAsArray('form_cd4', $removeKeys);

        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();
                $request = MiscUtility::updateFromArray($emptyLabArray, $remoteData);

                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                $existingSampleQuery = "SELECT cd4_id, sample_code
                                    FROM form_cd4 AS vl
                                    WHERE unique_id =? OR remote_sample_code=? OR (sample_code=? AND lab_id=?)";
                $existingSampleResult = $db->rawQueryOne($existingSampleQuery, [$request['unique_id'], $request['remote_sample_code'], $request['sample_code'], $request['lab_id']]);
                if (!empty($existingSampleResult)) {

                    $removeMoreKeys = [
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
                        'result_printed_datetime'
                    ];

                    $request = array_diff_key($request, array_flip($removeMoreKeys));

                    $formAttributes = JsonUtility::jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] = 'no';

                    $db->where('cd4_id', $existingSampleResult['cd4_id']);
                    $id = $db->update('form_cd4', $request);
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
                        $request['is_result_mail_sent'] = 'no';

                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;
                        $id = $db->insert('form_cd4', $request);
                    }
                }
                if ($id === true) {
                    $counter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }
        if ($cliMode) {
            echo "Synced $counter CD4 record(s)" . PHP_EOL;
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'cd4', $url, $payload, $responsePayload['cd4'], 'json', $labId);
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
            'sample_id',
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

        $emptyLabArray = $general->getTableFieldsAsArray('form_generic', $removeKeys);

        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();
                $request = MiscUtility::updateFromArray($emptyLabArray, $remoteData);

                $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                $existingSampleQuery = "SELECT sample_id, sample_code, test_type_form
                            FROM form_generic AS vl
                            WHERE unique_id =? OR remote_sample_code=? OR (sample_code=? AND lab_id=?)";
                $existingSampleResult = $db->rawQueryOne($existingSampleQuery, [$request['unique_id'], $request['remote_sample_code'], $request['sample_code'], $request['lab_id']]);
                if (!empty($existingSampleResult)) {

                    $removeMoreKeys = [
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
                        'data_from_tests'
                    ];

                    $request = array_diff_key($request, array_flip($removeMoreKeys));

                    $testTypeForm = JsonUtility::jsonToSetString(
                        $existingSampleResult['test_type_form'],
                        'test_type_form',
                        $request['test_type_form'],
                    );
                    $request['test_type_form'] = !empty($testTypeForm) ? $db->func($testTypeForm) : null;

                    $formAttributes = JsonUtility::jsonToSetString(
                        $existingSampleResult['form_attributes'],
                        'form_attributes',
                        $request['form_attributes'],
                    );
                    $request['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;
                    $request['is_result_mail_sent'] = 'no';
                    $db->where('sample_id', $existingSampleResult['sample_id']);
                    $id = $db->update('form_generic', $request);
                    $genericId = $existingSampleResult['sample_id'];
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
                        $request['is_result_mail_sent'] = 'no';

                        $request['source_of_request'] = "vlsts";
                        //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                        $request['data_sync'] = 0;
                        $id = $db->insert('form_generic', $request);
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
                    $counter++;
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                    'last_db_query' => $db->getLastQuery(),
                    'last_db_error' => $db->getLastError(),
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'stacktrace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }

        if ($cliMode) {
            echo "Synced $counter Custom Tests record(s)" . PHP_EOL;
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'generic-tests', $url, $payload, $responsePayload['generic-tests'], 'json', $labId);
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

$instanceId = $general->getInstanceId();
$db->where('vlsm_instance_id', $instanceId);
$id = $db->update('s_vlsm_instance', ['last_remote_requests_sync' => DateUtility::getCurrentDateTime()]);

if (isset($forceSyncModule) && trim((string) $forceSyncModule) != "" && isset($manifestCode) && trim((string) $manifestCode) != "") {
    return 1;
}
