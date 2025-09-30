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
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

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

/**
 * Helper to sync a single test request: find matching local record, optionally backfill remote_sample_code,
 * compare meaningful fields, and update or insert.
 *
 * @return array ['success' => bool, 'is_insert' => bool, 'localRecord' => array]
 */
function syncTestRequest(
    array $incoming,
    string $tableName,
    string $primaryKeyName,
    array $excludeKeysForUpdate,
    string $transactionId,
    bool $isSilent,
    DatabaseService $db,
    TestRequestsService $testRequestsService
): array {
    $localRecord = $testRequestsService->findMatchingLocalRecord($incoming, $tableName, $primaryKeyName);
    $didInsert = false;
    $didUpdate = false;
    $didFail = false;
    $failureReason = null;
    $resultRecord = $localRecord;

    if (!empty($localRecord)) {
        // Build the patchable payload
        $updatePayload = MiscUtility::excludeKeys($incoming, $excludeKeysForUpdate);

        // Prepare form_attributes
        $formAttributes = JsonUtility::jsonToSetString(
            $incoming['form_attributes'] ?? null,
            'form_attributes',
            ['syncTransactionId' => $transactionId]
        );
        if (!empty($formAttributes)) {
            $updatePayload['form_attributes'] = $db->func($formAttributes);
        } else {
            $updatePayload['form_attributes'] = null;
        }
        $updatePayload['is_result_mail_sent'] ??= 'no';

        // Conditional backfill of remote_sample_code
        if (!empty($incoming['remote_sample_code']) && empty($localRecord['remote_sample_code'])) {
            $db->rawQuery(
                "UPDATE {$tableName} SET remote_sample_code = ? WHERE {$primaryKeyName} = ? AND (remote_sample_code IS NULL OR remote_sample_code = '')",
                [$incoming['remote_sample_code'], $localRecord[$primaryKeyName]]
            );
            $localRecord['remote_sample_code'] = $incoming['remote_sample_code'];
        }

        // Determine if meaningful change exists
        $needsUpdate = !MiscUtility::isArrayEqual(
            $updatePayload,
            $localRecord,
            ['last_modified_datetime', 'form_attributes']
        );

        if ($needsUpdate) {
            $updatePayload['last_modified_datetime'] = DateUtility::getCurrentDateTime();
            if ($isSilent) {
                unset($updatePayload['last_modified_datetime']);
            }
            $db->where($primaryKeyName, $localRecord[$primaryKeyName]);
            $res = $db->update($tableName, $updatePayload);

            if ($res === true) {
                $didUpdate = true;
                $resultRecord = array_merge($localRecord, $updatePayload);
            } else {
                $didFail = true;
                $failureReason = 'update_failed: ' . ($db->getLastError() ?: 'unknown error');
            }
        } else {
            $resultRecord = $localRecord;
        }
    } else {
        // Insert path
        $incoming['source_of_request'] = $incoming['source_of_request'] ?? 'vlsts';
        $formAttributes = JsonUtility::jsonToSetString(
            $incoming['form_attributes'] ?? null,
            'form_attributes',
            ['syncTransactionId' => $transactionId]
        );
        if (!empty($formAttributes)) {
            $incoming['form_attributes'] = $db->func($formAttributes);
        } else {
            $incoming['form_attributes'] = null;
        }
        $incoming['is_result_mail_sent'] ??= 'no';
        $incoming['data_sync'] = 0;

        $res = $db->insert($tableName, $incoming);
        if ($res === true || $res > 0) {
            $didInsert = true;
            $insertId = $db->getInsertId();
            $resultRecord = [$primaryKeyName => $insertId] + $incoming;
        } else {
            $didFail = true;
            $failureReason = 'insert_failed: ' . ($db->getLastError() ?: 'unknown error');
        }
    }

    return [
        'success' => $didInsert || $didUpdate,
        'is_insert' => $didInsert,
        'is_update' => $didUpdate,
        'is_failure' => $didFail,
        'failure_reason' => $failureReason,
        'localRecord' => $resultRecord,
    ];
}

function outputSyncResults(ConsoleOutput $output, string $module, int $success, int $failures): void
{
    if ($success > 0) {
        $output->writeln("<success>✓ Synced $success " . strtoupper($module) . " record(s)</success>");
    } else {
        $output->writeln("Synced $success " . strtoupper($module) . " record(s)");
    }

    if ($failures > 0) {
        $output->writeln("<error>✗ Failed to sync $failures " . strtoupper($module) . " record(s)</error>");
    }
}

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
// Initialize Symfony Console Output
$output = new ConsoleOutput();
// Define custom styles
$output->getFormatter()->setStyle('success', new OutputFormatterStyle('green'));
$output->getFormatter()->setStyle('error', new OutputFormatterStyle('red'));
$output->getFormatter()->setStyle('warning', new OutputFormatterStyle('yellow'));
$output->getFormatter()->setStyle('info', new OutputFormatterStyle('cyan'));
$output->getFormatter()->setStyle('comment', new OutputFormatterStyle('black', 'white'));
$output->getFormatter()->setStyle('highlight', new OutputFormatterStyle('black', 'yellow'));

if ($cliMode) {

    $output->writeln('');
    $output->writeln('=========================');
    $output->writeln('<info>Starting test requests sync</info>');

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

if (!empty($_POST)) {
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
}

if ($syncSinceDate !== null) {
    $output->writeln("<comment>Filtering requests from: $syncSinceDate</comment>");
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

// if only one module is getting synced, limit to that
if (!empty($forceSyncModule)) {
    unset($systemConfig['modules']);
    $systemConfig['modules'][$forceSyncModule] = true;
}

$stsBearerToken = $general->getSTSToken();
$apiService->setBearerToken($stsBearerToken);

$promises = [];
$requestInfo = []; // to retain url+payload for tracking
$startTime = microtime(true);
$responsePayload = [];

foreach ($systemConfig['modules'] as $module => $status) {
    $moduleUrl = "$remoteURL/remote/v2/requests.php";
    $basePayload = [
        'labId' => $labId,
        'transactionId' => $transactionId
    ];
    if ($status === true) {
        $basePayload['testType'] = $module;
        if (!empty($forceSyncModule) && trim((string)$forceSyncModule) == $module && !empty($manifestCode) && trim((string)$manifestCode) != "") {
            $basePayload['manifestCode'] = $manifestCode;
        }
        if (!empty($syncSinceDate)) {
            $basePayload['syncSinceDate'] = $syncSinceDate;
        }

        // preserve for tracking later
        $requestInfo[$module] = [
            'url' => $moduleUrl,
            'payload' => $basePayload
        ];

        $promises[$module] = $apiService->post(
            $moduleUrl,
            $basePayload,
            gzip: true,
            async: true
        )->then(function ($response) use (&$responsePayload, $module, $cliMode, $output) {
            $responsePayload[$module] = $response->getBody()->getContents();
            if ($cliMode) {
                $output->writeln("<info>Received server response for $module</info>");
            }
        })->otherwise(function ($reason) use ($module, $cliMode, $output) {
            if ($cliMode) {
                $output->writeln("<error>STS Request sync for $module failed: $reason</error>");
            }
            LoggerUtility::logError(__FILE__ . ":" . __LINE__ . ":" . "STS Request sync for $module failed: " . $reason);
        });
    }
}

// Wait for all promises
Utils::settle($promises)->wait();

$endTime = microtime(true);
if ($cliMode) {
    $output->writeln(sprintf(
        '<comment>Total download time for STS Requests: %.2f seconds</comment>',
        $endTime - $startTime
    ));
}

// Define per-module config
$moduleConfigs = [
    'vl' => [
        'removeKeys' => [
            TestsService::getPrimaryColumn('vl'),
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
        ],
        'excludeUpdateKeys' => [
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
        ],
    ],
    'eid' => [
        'removeKeys' => [
            TestsService::getPrimaryColumn('eid'),
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
        ],
        'excludeUpdateKeys' => [
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
        ],
    ],
    'covid19' => [
        'removeKeys' => [
            TestsService::getPrimaryColumn('covid19'),
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
        ],
        'excludeUpdateKeys' => [
            'sample_code',
            'sample_code_key',
            'sample_code_format',
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
        ],
    ],
    'hepatitis' => [
        'removeKeys' => [
            TestsService::getPrimaryColumn('hepatitis'),
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
        ],
        'excludeUpdateKeys' => [
            'sample_code',
            'sample_code_key',
            'sample_code_format',
            'sample_batch_id',
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
        ],
    ],
    'tb' => [
        'removeKeys' => [
            TestsService::getPrimaryColumn('tb'),
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
        ],
        'excludeUpdateKeys' => [
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
            'last_modified_by',
            'last_modified_datetime',
            'lab_technician',
            'result_printed_datetime',
            'result_printed_on_sts_datetime',
        ],
    ],
    'cd4' => [
        'removeKeys' => [
            TestsService::getPrimaryColumn('cd4'),
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
        ],
        'excludeUpdateKeys' => [
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
        ],
    ],
];

// Process modules
try {
    foreach ($moduleConfigs as $module => $cfg) {
        if (empty($responsePayload[$module]) || $responsePayload[$module] === '[]' || !JsonUtility::isJSON($responsePayload[$module])) {
            continue;
        }

        $primaryKeyName = TestsService::getPrimaryColumn($module);
        $tableName = TestsService::getTestTableName($module);

        if ($cliMode) {
            $output->writeln('');
            $output->writeln("<highlight>Processing for " . strtoupper($module) . "...</highlight>");
        }

        $options = [
            'pointer' => '/requests',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($responsePayload[$module], $options);

        $localDbFieldArray = $general->getTableFieldsAsArray($tableName, $cfg['removeKeys']);

        $loopIndex = 0;
        $successCounter = 0;
        $failureCounter = 0;

        foreach ($parsedData as $key => $remoteData) {
            try {
                $db->beginTransaction();

                $request = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $remoteData);
                $syncResult = syncTestRequest(
                    $request,
                    $tableName,
                    $primaryKeyName,
                    $cfg['excludeUpdateKeys'],
                    $transactionId,
                    $isSilent,
                    $db,
                    $testRequestsService
                );
                $localRecord = $syncResult['localRecord'];

                if ($syncResult['is_failure']) {
                    $failureCounter++;
                    LoggerUtility::logError("Sync operation failed", [
                        'reason' => $syncResult['failure_reason'],
                        'unique_id' => $request['unique_id'] ?? null,
                        'sample_code' => $request['sample_code'] ?? null,
                        'module' => $module,
                        'last_db_error' => $db->getLastError()
                    ]);
                    $db->rollbackTransaction();
                    continue; // Skip to next record
                }

                // Module-specific logic
                if ($module === 'covid19') {
                    $covid19Id = $localRecord[$primaryKeyName] ?? null;
                    if (isset($remoteData['data_from_symptoms']) && !empty($remoteData['data_from_symptoms']) && $covid19Id) {
                        $db->where($primaryKeyName, $covid19Id);
                        $db->delete("covid19_patient_symptoms");
                        foreach ($remoteData['data_from_symptoms'] as $value) {
                            $symptomData = [
                                "covid19_id" => $covid19Id,
                                "symptom_id" => $value['symptom_id'],
                                "symptom_detected" => $value['symptom_detected'],
                                "symptom_details" => $value['symptom_details'],
                            ];
                            $db->insert("covid19_patient_symptoms", $symptomData);
                        }
                    }
                    if (isset($remoteData['data_from_comorbidities']) && !empty($remoteData['data_from_comorbidities']) && $covid19Id) {
                        $db->where($primaryKeyName, $covid19Id);
                        $db->delete("covid19_patient_comorbidities");
                        foreach ($remoteData['data_from_comorbidities'] as $comorbidityData) {
                            $comData = [
                                "covid19_id" => $covid19Id,
                                "comorbidity_id" => $comorbidityData['comorbidity_id'],
                                "comorbidity_detected" => $comorbidityData['comorbidity_detected'],
                            ];
                            $db->insert("covid19_patient_comorbidities", $comData);
                        }
                    }
                    if (isset($remoteData['data_from_tests']) && !empty($remoteData['data_from_tests']) && $covid19Id) {
                        $db->where($primaryKeyName, $covid19Id);
                        $db->delete("covid19_tests");
                        foreach ($remoteData['data_from_tests'] as $cdata) {
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
                                "updated_datetime" => $cdata['updated_datetime'],
                            ];
                            $db->insert("covid19_tests", $covid19TestData);
                        }
                    }
                }

                if ($module === 'hepatitis') {
                    $hepatitisId = $localRecord[$primaryKeyName] ?? null;
                    if (isset($remoteData['data_from_risks']) && !empty($remoteData['data_from_risks']) && $hepatitisId) {
                        $db->where($primaryKeyName, $hepatitisId);
                        $db->delete("hepatitis_risk_factors");
                        foreach ($remoteData['data_from_risks'] as $riskId => $riskValue) {
                            $riskFactorsData = [
                                "hepatitis_id" => $hepatitisId,
                                "riskfactors_id" => $riskId,
                                "riskfactors_detected" => $riskValue,
                            ];
                            $db->insert("hepatitis_risk_factors", $riskFactorsData);
                        }
                    }
                    if (isset($remoteData['data_from_comorbidities']) && !empty($remoteData['data_from_comorbidities']) && $hepatitisId) {
                        $db->where($primaryKeyName, $hepatitisId);
                        $db->delete("hepatitis_patient_comorbidities");
                        foreach ($remoteData['data_from_comorbidities'] as $comoId => $comoValue) {
                            $comorbidityData = [
                                "hepatitis_id" => $hepatitisId,
                                "comorbidity_id" => $comoId,
                                "comorbidity_detected" => $comoValue,
                            ];
                            $db->insert('hepatitis_patient_comorbidities', $comorbidityData);
                        }
                    }
                }

                if ($syncResult['success']) {
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
            outputSyncResults($output, $module, $successCounter, $failureCounter);
        }

        $general->addApiTracking(
            $transactionId,
            'vlsm-system',
            $successCounter,
            'receive-requests',
            $module,
            $requestInfo[$module]['url'] ?? null,
            $requestInfo[$module]['payload'] ?? null,
            $responsePayload[$module],
            'json',
            $labId
        );
    }

    // Special-case generic-tests (preserve its merging logic)
    if (!empty($responsePayload['generic-tests']) && $responsePayload['generic-tests'] !== '[]' && JsonUtility::isJSON($responsePayload['generic-tests'])) {
        $module = 'generic-tests';
        $primaryKeyName = TestsService::getPrimaryColumn($module);
        $tableName = TestsService::getTestTableName($module);

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

                $request = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $remoteData);
                $localRecord = $testRequestsService->findMatchingLocalRecord($request, $tableName, $primaryKeyName);

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

                    // Merge test_type_form and form_attributes like original
                    $testTypeForm = JsonUtility::jsonToSetString(
                        $localRecord['test_type_form'] ?? null,
                        'test_type_form',
                        $request['test_type_form'] ?? null
                    );
                    if (!empty($testTypeForm)) {
                        $request['test_type_form'] = $db->func($testTypeForm);
                    } else {
                        $request['test_type_form'] = null;
                    }

                    $formAttributes = JsonUtility::jsonToSetString(
                        $localRecord['form_attributes'] ?? null,
                        'form_attributes',
                        $request['form_attributes'] ?? null
                    );
                    if (!empty($formAttributes)) {
                        $request['form_attributes'] = $db->func($formAttributes);
                    } else {
                        $request['form_attributes'] = null;
                    }

                    $request['is_result_mail_sent'] ??= 'no';

                    $updatePayload = MiscUtility::excludeKeys($request, $removeKeysForUpdate);

                    // Conditional backfill of remote_sample_code
                    if (!empty($request['remote_sample_code']) && empty($localRecord['remote_sample_code'])) {
                        $db->rawQuery(
                            "UPDATE {$tableName} SET remote_sample_code = ? WHERE {$primaryKeyName} = ? AND (remote_sample_code IS NULL OR remote_sample_code = '')",
                            [$request['remote_sample_code'], $localRecord[$primaryKeyName]]
                        );
                        $localRecord['remote_sample_code'] = $request['remote_sample_code'];
                    }

                    $needsUpdate = !MiscUtility::isArrayEqual(
                        $updatePayload,
                        $localRecord,
                        ['last_modified_datetime', 'form_attributes']
                    );

                    if ($needsUpdate) {
                        $updatePayload['last_modified_datetime'] = DateUtility::getCurrentDateTime();
                        if ($isSilent) {
                            unset($updatePayload['last_modified_datetime']);
                        }
                        $db->where($primaryKeyName, $localRecord[$primaryKeyName]);
                        $id = $db->update($tableName, $updatePayload);
                    } else {
                        $id = true;
                    }
                    $genericId = $localRecord[$primaryKeyName];
                } else {
                    // Insert path
                    if (!empty($request['sample_collection_date'])) {
                        $request['source_of_request'] = 'vlsts';
                        $testTypeForm = JsonUtility::jsonToSetString(
                            $request['test_type_form'] ?? null,
                            'test_type_form'
                        );
                        if (!empty($testTypeForm)) {
                            $request['test_type_form'] = $db->func($testTypeForm);
                        } else {
                            $request['test_type_form'] = null;
                        }

                        $formAttributes = JsonUtility::jsonToSetString(
                            $request['form_attributes'] ?? null,
                            'form_attributes',
                            ['syncTransactionId' => $transactionId]
                        );
                        if (!empty($formAttributes)) {
                            $request['form_attributes'] = $db->func($formAttributes);
                        } else {
                            $request['form_attributes'] = null;
                        }
                        $request['is_result_mail_sent'] ??= 'no';
                        $request['data_sync'] = 0;

                        $id = $db->insert($tableName, $request);
                        $genericId = $db->getInsertId();
                    } else {
                        $id = false;
                        $genericId = null;
                    }
                }

                if (isset($remoteData['data_from_tests']) && !empty($remoteData['data_from_tests']) && !empty($genericId)) {
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
                            "updated_datetime" => $genericTestData['updated_datetime'],
                        ]);
                    }
                }

                if (!empty($id) && $id === true) {
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
            echo "Synced $successCounter Custom Tests record(s)" . PHP_EOL;
        }

        $general->addApiTracking(
            $transactionId,
            'vlsm-system',
            $successCounter,
            'receive-requests',
            'generic-tests',
            $requestInfo['generic-tests']['url'] ?? null,
            $requestInfo['generic-tests']['payload'] ?? null,
            $responsePayload['generic-tests'],
            'json',
            $labId
        );
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

// Final sync timestamp update
$db->where('vlsm_instance_id', $general->getInstanceId());
$db->update('s_vlsm_instance', ['last_remote_requests_sync' => DateUtility::getCurrentDateTime()]);

if (
    isset($forceSyncModule) && trim((string)$forceSyncModule) != ""
    && isset($manifestCode) && trim((string)$manifestCode) != ""
) {
    $formTable = TestsService::getTestTableName($forceSyncModule);
    $primaryKey = TestsService::getPrimaryColumn($forceSyncModule);
    $db->where("sample_package_code", $manifestCode);
    $sampleData = $db->getValue($formTable, $primaryKey, null);
    echo JsonUtility::encodeUtf8Json($sampleData);
}
