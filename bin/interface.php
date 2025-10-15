#!/usr/bin/env php
<?php

// bin/interface.php

// only run from command line
$isCli = php_sapi_name() === 'cli';

if ($isCli === false) {
    exit(0);
}

// Handle Ctrl+C gracefully (if pcntl extension is available)
if ($isCli && function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () {
        echo PHP_EOL . PHP_EOL;
        echo "⚠️  Interface sync cancelled by user." . PHP_EOL;
        exit(130); // Standard exit code for SIGINT
    });
}

require_once __DIR__ . "/../bootstrap.php";

declare(ticks=1);

use App\Services\VlService;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;

if (!isset(SYSTEM_CONFIG['interfacing']['enabled']) || SYSTEM_CONFIG['interfacing']['enabled'] === false) {
    LoggerUtility::logError('Interfacing is not enabled. Please enable it in configuration.');
    exit;
}



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var TestResultsService $testResultsService */
$testResultsService = ContainerRegistry::get(TestResultsService::class);


$forceExecution = false; // Default: exclude locked samples
$lastInterfaceSync = null;
$silent = false;

foreach ($argv as $arg) {
    if (str_contains($arg, 'force')) {
        $forceExecution = true;
    }

    if (!isset($lastInterfaceSync)) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg) && DateUtility::isDateFormatValid($arg, 'Y-m-d')) {
            $lastInterfaceSync = DateUtility::getDateTime($arg, 'Y-m-d');
        } elseif (is_numeric($arg)) {
            $lastInterfaceSync = DateUtility::daysAgo((int) $arg);
        } elseif (preg_match('/^(\d+)force/', $arg, $matches)) {
            $lastInterfaceSync = DateUtility::daysAgo((int) $matches[1]);
            $forceExecution = true;
        }
    }

    if (str_contains($arg, 'silent')) {
        $silent = true;
    }
}

$lockFile = MiscUtility::getLockFile(__FILE__);

// Only handle lock file if force is NOT used
if (!$forceExecution) {
    // Check if the lock file already exists
    if (!MiscUtility::isLockFileExpired($lockFile)) {
        echo "Another instance of the script : " . basename(__FILE__) . " is already running." . PHP_EOL;
        exit;
    }
}

MiscUtility::touchLockFile($lockFile); // Create or update the lock file
MiscUtility::setupSignalHandler($lockFile);



$mysqlConnected = false;
$sqliteConnected = false;

if (!empty(SYSTEM_CONFIG['interfacing']['database']['host']) && !empty(SYSTEM_CONFIG['interfacing']['database']['username'])) {
    $mysqlConnected = true;
    $db->addConnection('interface', SYSTEM_CONFIG['interfacing']['database']);
}

// Default to database value if no valid date or days were provided
if ($lastInterfaceSync === null) {
    $lastInterfaceSync = $db->connection('default')->getValue('s_vlsm_instance', 'last_interface_sync');
}

$labId = $general->getSystemConfig('sc_testing_lab_id');
$formId = (int) $general->getGlobalConfig('vl_form');

if (empty($labId)) {
    LoggerUtility::log('error', "No Lab ID set in System Config. Skipping Interfacing Results");
    exit(0);
}

$sqliteDb = null;
$syncedIds = [];
$unsyncedIds = [];
$addedOnValues = []; // Array to store added_on values


if (!empty(SYSTEM_CONFIG['interfacing']['sqlite3Path'])) {
    $sqliteConnected = true;
    $sqliteDb = new \PDO("sqlite:" . SYSTEM_CONFIG['interfacing']['sqlite3Path']);
}

try {
    $interfaceData = [];
    //get the value from interfacing DB
    if ($mysqlConnected) {
        if ($isCli) {
            echo "Connected to MySQL" . PHP_EOL;
        }

        $db->connection('interface')->beginTransaction();

        if (!empty($lastInterfaceSync)) {
            $db->connection('interface')
                ->where(" (added_on > '$lastInterfaceSync' OR lims_sync_status = 0) ");
        } else {
            $db->connection('interface')
                ->where(" lims_sync_status = 0 ");
        }
        $db->connection('interface')->where('result_status', 1);
        $db->connection('interface')->orderBy('analysed_date_time', 'asc');
        $mysqlData = $db->connection('interface')->get('orders');
        if ($isCli) {
            echo "# of records from MySQL : " . count($mysqlData) . PHP_EOL;
        }
        $interfaceData = array_merge($interfaceData, $mysqlData); // Add MySQL data
    }

    if ($sqliteConnected) {
        if ($isCli) {
            echo "Connected to sqlite" . PHP_EOL;
        }
        $where = [];
        $where[] = " result_status = 1 ";
        if (!empty($lastInterfaceSync)) {
            $where[] = " (added_on > '$lastInterfaceSync' OR lims_sync_status = 0) ";
        }
        $where = implode(' AND ', $where);
        $interfaceQuery = "SELECT * FROM `orders`
                            WHERE $where
                            ORDER BY analysed_date_time ASC";

        $sqliteData = $sqliteDb->query($interfaceQuery)->fetchAll(PDO::FETCH_ASSOC);
        if ($isCli) {
            echo "# of records from SQLITE3 : " . count($sqliteData) . PHP_EOL;
        }
        $interfaceData = array_merge($interfaceData, $sqliteData); // Add SQLite data
    }


    // Group by order_id + test_id
    $grouped = [];
    foreach ($interfaceData as $row) {
        $groupKey = $row['order_id'] . '::' . $row['test_id'];
        $grouped[$groupKey][] = $row;
    }

    $filtered = [];

    /** @var VlService $vlService */
    $vlService = ContainerRegistry::get(VlService::class);

    foreach ($grouped as $group) {
        $hasCopiesUnit = false;

        // First pass to check if any row has "copies"-type unit (but NOT containing "log")
        foreach ($group as $row) {
            $unit = strtolower(preg_replace('/\s+/', '', (string) ($row['test_unit'] ?? '')));

            // Skip if it contains "log" - it's not a pure copies unit
            if (str_contains($unit, 'log')) {
                continue;
            }

            foreach ($vlService->copiesPatterns as $pattern) {
                if (str_contains($unit, $pattern)) {
                    $hasCopiesUnit = true;
                    break 2; // Exit both loops
                }
            }
        }

        // Second pass to filter
        foreach ($group as $row) {
            $unit = strtolower(preg_replace('/\s+/', '', (string) ($row['test_unit'] ?? '')));
            $isLog = str_contains($unit, 'log');

            // If we have pure copies (not log), skip log results
            if ($hasCopiesUnit && $isLog) {
                continue;
            }
            $filtered[] = $row;
        }
    }

    $filteredIds = array_column($filtered, 'id');
    $skippedIds = [];

    foreach ($interfaceData as $row) {
        if (!in_array($row['id'], $filteredIds, true)) {
            $skippedIds[] = $row['id'];
        }
    }


    $interfaceData = $filtered;

    if (empty($interfaceData)) {
        if ($isCli) {
            echo "No results to process" . PHP_EOL;
        }
        exit(0);
    }

    $additionalColumns = [
        'form_hepatitis' => ['hepatitis_test_type'],
    ];

    $numberOfResults = 0;
    if (!empty($interfaceData)) {

        $totalResults = count($interfaceData); // Get the total number of items
        if ($isCli) {
            echo "Processing $totalResults filtered results from Interface Tool" . PHP_EOL;
        }

        $availableModules = [];

        $activeModules = SystemService::getActiveModules(onlyTests: true);

        foreach ($activeModules as $module) {
            $primaryKey = TestsService::getPrimaryColumn($module);
            $availableModules[$primaryKey] = TestsService::getTestTableName($module);
        }

        $processedResults = [];
        //$allowRepeatedTests = false;

        foreach ($interfaceData as $key => $result) {


            // This is to prevent the lock file from being deleted by the signal handler
            // and to keep the script running
            // touch the lock file every 10 iterations to reduce the number of times disk is accessed
            if ($key % 10 === 0) {
                MiscUtility::touchLockFile($lockFile);
            }

            $db->connection('default')->beginTransaction();
            if ($isCli) {
                MiscUtility::progressBar($key + 1, $totalResults); // Update progress bar
            }

            if (empty($result['order_id']) && empty($result['test_id'])) {
                continue;
            }

            // if ($allowRepeatedTests === false && (in_array($result['test_id'], $processedResults) || in_array($result['order_id'], $processedResults))) {
            //     continue;
            // }


            $tableInfo = [];
            foreach ($availableModules as $primaryKeyColumn => $individualTableName) {

                $columnsToSelect = "$primaryKeyColumn, unique_id, sample_code, remote_sample_code, lab_assigned_code";

                // If the table name is there in $additionalColumns, add the additional columns
                if (!empty($additionalColumns) && array_key_exists($individualTableName, $additionalColumns)) {
                    $extraColumnsString = implode(', ', $additionalColumns[$individualTableName]);
                    $columnsToSelect = "$primaryKeyColumn, unique_id, $extraColumnsString";
                }

                $conditions = [];
                if ($forceExecution === false) {
                    // Default: Exclude locked samples
                    $conditions[] = "IFNULL(locked, 'no') = 'no'";
                }
                $conditions[] = "(sample_code IN (?, ?) OR remote_sample_code IN (?, ?) OR lab_assigned_code IN (?, ?))";

                $conditions = implode(' AND ', $conditions);
                $tableQuery = "SELECT *
                                    FROM $individualTableName
                                    WHERE $conditions";

                // Execute the query
                $tableInfo = $db->connection('default')
                    ->rawQueryOne($tableQuery, [
                        $result['order_id'],
                        $result['order_id'],
                        $result['order_id'],
                        $result['test_id'],
                        $result['test_id'],
                        $result['test_id']
                    ]);
                // If we found the information, break out of the loop
                // if (!empty($tableInfo[$primaryKeyColumn])) {
                //     break;
                // }

                $matchedColumn = null;
                $matchedTable = null;

                // Check which columns match
                // Determine which column matches
                if (!empty($tableInfo)) {

                    $matchedTable = $individualTableName;

                    if ($result['order_id'] === $tableInfo['sample_code'] || $result['test_id'] === $tableInfo['sample_code']) {
                        $matchedColumn = 'sample_code';
                    } elseif ($result['order_id'] === $tableInfo['remote_sample_code'] || $result['test_id'] === $tableInfo['remote_sample_code']) {
                        $matchedColumn = 'remote_sample_code';
                    } elseif ($result['order_id'] === $tableInfo['lab_assigned_code'] || $result['test_id'] === $tableInfo['lab_assigned_code']) {
                        $matchedColumn = 'lab_assigned_code';
                    }
                    break;
                }
            }

            //Getting Approved By and Reviewed By from Instruments table
            $instrumentDetails = $db->connection('default')
                ->rawQueryOne(
                    "SELECT * FROM instruments WHERE instruments.machine_name = ?",
                    [$result['instrument_id'] ?? $result['machine_used']]
                );

            if (empty($instrumentDetails)) {
                $sql = "SELECT * FROM instruments
                    INNER JOIN instrument_machines ON instruments.instrument_id = instrument_machines.instrument_id
                    WHERE instrument_machines.config_machine_name = ?";
                $instrumentDetails = $db->connection('default')
                    ->rawQueryOne($sql, [$result['instrument_id'] ?? $result['machine_used']]);
            }


            $approved = !empty($instrumentDetails['approved_by']) ? json_decode((string) $instrumentDetails['approved_by'], true) : [];
            $reviewed = !empty($instrumentDetails['reviewed_by']) ? json_decode((string) $instrumentDetails['reviewed_by'], true) : [];
            $instrumentId = $instrumentDetails['instrument_id'] ?? null;
            $lowerLimit = $instrumentDetails['lower_limit'] ?? null;

            if ($matchedTable === 'form_vl') {

                $absDecimalVal = null;
                $absVal = null;
                $logVal = null;
                $txtVal = null;
                $vlResult = null;
                $resultStatus = null;
                //set result in result fields
                if (!empty($result['results'])) {

                    $vlResult = trim(str_ireplace(['cp/ml', 'copies/ml'], '', (string) $result['results']));

                    $unit = trim((string) $result['test_unit']);

                    if ($vlResult == "-1.00") {
                        $vlResult = "Target Not Detected";
                    } elseif (strtolower($vlResult) == 'detected' && !empty($lowerLimit)) {
                        $vlResult = "< $lowerLimit";
                    }

                    $logVal = null;
                    $absDecimalVal = null;
                    $absVal = null;
                    $txtVal = null;
                    $interpretedResults = [];


                    if (!empty($vlResult) && !in_array(strtolower($vlResult), ['fail', 'failed', 'failure', 'error', 'err'])) {
                        $interpretedResults = $vlService->interpretViralLoadResult($vlResult, $unit, $instrumentDetails['low_vl_result_text'] ?? null);
                        if (!empty($interpretedResults)) {
                            $logVal = $interpretedResults['logVal'];
                            $vlResult = $interpretedResults['result'] ?? $interpretedResults['txtVal'];
                            $absDecimalVal = $interpretedResults['absDecimalVal'];
                            $absVal = $interpretedResults['absVal'];
                            $txtVal = $interpretedResults['txtVal'];
                            $resultStatus = $interpretedResults['resultStatus'] ?? SAMPLE_STATUS\ACCEPTED;
                        }
                    }
                }

                $testedByUserId = null;
                $tester = $result['tested_by'];
                // if ^ exists it means the Operator Name has both tester and releaser name
                if (str_contains(strtolower((string)$result['tested_by']), '^')) {
                    $operatorArray = explode("^", (string) $result['tested_by']);
                    $tester = $operatorArray[0];
                }

                $testedByUserId = $usersService->getOrCreateUser($tester);

                $autoApprove = $general->getGlobalConfig('auto_approve_interface_results') === 'yes' ?? false;

                if (empty($vlResult) || $vlResult == '' || $autoApprove === false) {
                    $resultStatus = SAMPLE_STATUS\PENDING_APPROVAL;
                }

                $data = [
                    'lab_id' => $labId,
                    'instrument_id' => $instrumentId,
                    'tested_by' => $testedByUserId,
                    'result_approved_by' => $approved['vl'] ?? null,
                    'result_approved_datetime' => $result['authorised_date_time'],
                    'result_reviewed_by' => $reviewed['vl'] ?? null,
                    'result_reviewed_datetime' => $result['authorised_date_time'],
                    'sample_tested_datetime' => $result['result_accepted_date_time'],
                    'result_value_log' => $logVal,
                    'result_value_absolute' => $absVal,
                    'result_value_absolute_decimal' => $absDecimalVal,
                    'result_value_text' => $txtVal,
                    'result' => $vlResult,
                    'result_status' => $resultStatus ?? SAMPLE_STATUS\ACCEPTED,
                    'vl_test_platform' => $instrumentDetails['machine_name'] ?? $result['machine_used'],
                    'manual_result_entry' => 'no',
                    'import_machine_file_name' => 'interface',
                    'result_printed_datetime' => null,
                    // 'result_printed_on_lis_datetime' => null,
                    // 'result_printed_on_sts_datetime' => null,
                    'result_dispatched_datetime' => null,
                    'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                    'data_sync' => 0
                ];

                if ($silent === true) {
                    unset($data['last_modified_datetime']);
                }

                if ($formId === COUNTRY\CAMEROON && !empty($result['raw_text'])) {
                    $sampleCode = $tableInfo['sample_code'];

                    $stringToSearch = preg_quote($sampleCode, '/') . '\^CV\s+(\d+)';

                    $pattern = "/$stringToSearch/i";

                    $data['cv_number'] = (preg_match($pattern, $result['raw_text'], $matches)) ? trim($matches[1]) : null;
                }

                if (strtolower((string) $vlResult) == 'failed' || strtolower((string) $vlResult) == 'fail' || strtolower((string) $vlResult) == 'invalid' || strtolower((string) $vlResult) == 'inconclusive') {
                    $data['result_status'] = SAMPLE_STATUS\TEST_FAILED; // Invalid
                }

                $data['vl_result_category'] = $vlService->getVLResultCategory($data['result_status'], $data['result']);
                if ($data['vl_result_category'] == 'failed' || $data['vl_result_category'] == 'invalid') {
                    $data['result_status'] = SAMPLE_STATUS\TEST_FAILED;
                } elseif ($data['vl_result_category'] == 'rejected') {
                    $data['result_status'] = SAMPLE_STATUS\REJECTED;
                }
                // $db->connection('default')->where('vl_sample_id', $tableInfo['vl_sample_id']);
                // $queryStatus = $db->connection('default')->update('form_vl', $data);
                // $numberOfResults++;
                // if ($queryStatus === true) {
                //     $syncedIds[]  = $result['id'];
                // }

                $ignoredKeys = ['last_modified_datetime', 'result_printed_datetime']; // add more if needed

                $needsUpdate = !MiscUtility::isArrayEqual($data, $tableInfo, $ignoredKeys);

                if ($needsUpdate) {
                    //echo "Updating VL Sample Code: " . $tableInfo['sample_code'] . PHP_EOL;
                    $db->connection('default')->where('vl_sample_id', $tableInfo['vl_sample_id']);
                    $queryStatus = $db->connection('default')->update('form_vl', $data);
                    if ($queryStatus === true) {
                        $syncedIds[] = $result['id'];
                        $numberOfResults++;
                    }
                } else {
                    //echo "Skipping update for VL Sample Code: " . $tableInfo['sample_code'] . PHP_EOL;
                    // Skip update, consider as synced anyway
                    $syncedIds[] = $result['id'];
                }
            } elseif ($matchedTable === 'form_eid') {

                $absDecimalVal = null;
                $absVal = null;
                $logVal = null;
                $txtVal = null;
                //set result in result fields
                if (trim((string) $result['results']) != "") {

                    if (str_contains(strtolower((string)$result['results']), 'not detected')) {
                        $eidResult = 'negative';
                    } elseif ((str_contains(strtolower((string)$result['results']), 'detected')) || (str_contains(strtolower((string)$result['results']), 'passed'))) {
                        $eidResult = 'positive';
                    } else {
                        $eidResult = strtolower((string)$result['results']);
                    }
                }

                $autoApprove = $general->getGlobalConfig('auto_approve_interface_results') === 'yes' ?? false;
                $resultStatus = ($autoApprove === false) ? SAMPLE_STATUS\PENDING_APPROVAL : SAMPLE_STATUS\ACCEPTED;
                $data = [
                    'lab_id' => $labId,
                    'tested_by' => $result['tested_by'],
                    'instrument_id' => $instrumentId,
                    'result_approved_datetime' => $result['authorised_date_time'],
                    'sample_tested_datetime' => $result['result_accepted_date_time'],
                    'result' => $eidResult,
                    'eid_test_platform' => $result['machine_used'],
                    'result_status' => $resultStatus,
                    'manual_result_entry' => 'no',
                    'result_approved_by' => $approved['eid'] ?? null,
                    'result_reviewed_by' => $reviewed['eid'] ?? null,
                    'result_printed_datetime' => null,
                    // 'result_printed_on_lis_datetime' => null,
                    // 'result_printed_on_sts_datetime' => null,
                    'result_dispatched_datetime' => null,
                    'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                    'data_sync' => 0
                ];

                if ($silent === true) {
                    unset($data['last_modified_datetime']);
                }

                // $db->connection('default')->where('eid_id', $tableInfo['eid_id']);
                // $queryStatus = $db->connection('default')->update('form_eid', $data);
                // $numberOfResults++;
                // if ($queryStatus === true) {
                //     $syncedIds[]  = $result['id'];
                // }

                $ignoredKeys = ['last_modified_datetime', 'result_printed_datetime']; // add more if needed

                $needsUpdate = !MiscUtility::isArrayEqual($data, $tableInfo, $ignoredKeys);

                if ($needsUpdate) {
                    //echo "Updating EID Sample Code: " . $tableInfo['sample_code'] . PHP_EOL;
                    $db->connection('default')->where('eid_id', $tableInfo['eid_id']);
                    $queryStatus = $db->connection('default')->update('form_eid', $data);
                    if ($queryStatus === true) {
                        $syncedIds[] = $result['id'];
                        $numberOfResults++;
                    }
                } else {
                    //echo "Skipping update for EID Sample Code: " . $tableInfo['sample_code'] . PHP_EOL;
                    $syncedIds[] = $result['id'];
                }
            } elseif ($matchedTable === 'form_covid19') {

                // TODO: Add covid19 results

            } elseif ($matchedTable === 'form_hepatitis') {

                /** @var VlService $vlService */
                $vlService = ContainerRegistry::get(VlService::class);

                $absDecimalVal = null;
                $absVal = null;
                $logVal = null;
                $txtVal = null;
                $otherFieldResult = null;
                $testType = strtolower((string) $tableInfo['hepatitis_test_type']);
                if ($testType == 'hbv') {
                    $resultField = "hbv_vl_count";
                    $otherField = "hcv_vl_count";
                } elseif ($testType == 'hcv') {
                    $resultField = "hcv_vl_count";
                    $otherField = "hbv_vl_count";
                } else {
                    continue;
                }
                //set result in result fields
                if (trim((string) $result['results']) != "") {

                    $hepatitisResult = trim((string) $result['results']);
                    $unit = trim((string) $result['test_unit']);
                    $interpretedResults = $vlService->interpretViralLoadResult($hepatitisResult, $unit, $instrumentDetails['low_vl_result_text']);
                    $hepatitisResult = $interpretedResults['result'];
                }

                $userId = $usersService->getOrCreateUser($result['tested_by']);

                $autoApprove = $general->getGlobalConfig('auto_approve_interface_results') === 'yes' ?? false;
                $resultStatus = ($autoApprove === false) ? SAMPLE_STATUS\PENDING_APPROVAL : SAMPLE_STATUS\ACCEPTED;

                $data = [
                    'lab_id' => $labId,
                    'instrument_id' => $instrumentId,
                    'tested_by' => $userId,
                    'result_approved_datetime' => $result['authorised_date_time'],
                    'sample_tested_datetime' => $result['result_accepted_date_time'],
                    $resultField => $hepatitisResult ?? null,
                    $otherField => $otherFieldResult ?? null,
                    'hepatitis_test_platform' => $result['machine_used'],
                    'result_status' => $resultStatus,
                    'manual_result_entry' => 'no',
                    'result_approved_by' => $approved['hepatitis'] ?? null,
                    'result_reviewed_by' => $reviewed['hepatitis'] ?? null,
                    'result_printed_datetime' => null,
                    // 'result_printed_on_lis_datetime' => null,
                    // 'result_printed_on_sts_datetime' => null,
                    'result_dispatched_datetime' => null,
                    'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                    'data_sync' => 0
                ];

                if ($silent === true) {
                    unset($data['last_modified_datetime']);
                }

                // $db->connection('default')->where('hepatitis_id', $tableInfo['hepatitis_id']);
                // $queryStatus = $db->connection('default')->update('form_hepatitis', $data);
                // $numberOfResults++;
                // // $processedResults[] = $result['order_id'];
                // //  $processedResults[] = $result['test_id'];
                // if ($queryStatus === true) {
                //     $syncedIds[]  = $result['id'];
                // }

                $ignoredKeys = ['last_modified_datetime', 'result_printed_datetime']; // add more if needed

                $needsUpdate = !MiscUtility::isArrayEqual($data, $tableInfo, $ignoredKeys);

                if ($needsUpdate) {
                    //echo "Updating Hepatitis Sample Code: " . $tableInfo['sample_code'] . PHP_EOL;
                    $db->connection('default')->where('hepatitis_id', $tableInfo['hepatitis_id']);
                    $queryStatus = $db->connection('default')->update('form_hepatitis', $data);
                    if ($queryStatus === true) {
                        $syncedIds[] = $result['id'];
                        $numberOfResults++;
                    }
                } else {
                    //echo "Skipping update for Hepatitis Sample Code: " . $tableInfo['sample_code'] . PHP_EOL;
                    $syncedIds[] = $result['id'];
                }
            } else {
                $unsyncedIds[] = $result['id'];
            }

            if (!empty($result['added_on'])) {
                $addedOnValues[] = strtotime($result['added_on']);
            }

            $db->connection('default')->commitTransaction();
        }

        if ($numberOfResults > 0) {
            $importedBy = $_SESSION['userId'] ?? 'AUTO';
            $testResultsService->resultImportStats($numberOfResults, 'interface', $importedBy);
        }
    }

    $db->connection('interface')->commitTransaction();
} catch (Throwable $e) {
    $db->connection('default')->rollbackTransaction();
    $db->connection('interface')->rollbackTransaction();
    LoggerUtility::logError($e->getMessage(),  [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_interface_db_query' => $db->connection('interface')->getLastQuery(),
        'last_interface_db_error' => $db->connection('interface')->getLastError(),
        'last_default_db_query' => $db->connection('default')->getLastQuery(),
        'last_default_db_error' => $db->connection('default')->getLastError(),
        'trace' => $e->getTraceAsString()
    ]);
} finally {

    $batchSize = 1000;

    $updateSyncStatus = function ($db, $sqliteDb, $ids, $status, $mysqlConnected, $sqliteConnected) use ($batchSize) {
        if (!empty($ids)) {
            $currentDateTime = DateUtility::getCurrentDateTime();

            $totalBatches = ceil(count($ids) / $batchSize);

            for ($i = 0; $i < $totalBatches; $i++) {
                $batchIds = array_slice($ids, $i * $batchSize, $batchSize);

                // Update MySQL
                if ($mysqlConnected) {
                    $interfaceData = [
                        'lims_sync_status' => $status,
                        'lims_sync_date_time' => $currentDateTime,
                    ];

                    $db->connection('interface')->reset();
                    $db->connection('interface')->where('id', $batchIds, 'IN');
                    $db->connection('interface')->update('orders', $interfaceData);
                }

                // Update SQLite
                if ($sqliteConnected) {
                    $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
                    $sql = "UPDATE orders
                        SET lims_sync_status = ?, lims_sync_date_time = ?
                        WHERE id IN ($placeholders)";
                    $stmt = $sqliteDb->prepare($sql);
                    $stmt->bindValue(1, $status, PDO::PARAM_INT);
                    $stmt->bindValue(2, $currentDateTime);

                    foreach ($batchIds as $index => $id) {
                        $stmt->bindValue($index + 3, $id, PDO::PARAM_INT);
                    }

                    $stmt->execute();
                }
            }
        }
    };


    try {
        // Update synced IDs
        $db->connection('interface')->beginTransaction();
        $updateSyncStatus($db, $sqliteDb, $syncedIds, 1, $mysqlConnected, $sqliteConnected);

        // Update unsynced IDs
        $updateSyncStatus($db, $sqliteDb, $unsyncedIds, 2, $mysqlConnected, $sqliteConnected);
        $updateSyncStatus($db, $sqliteDb, $skippedIds, 2, $mysqlConnected, $sqliteConnected);

        $db->connection('interface')->commitTransaction();
    } catch (Throwable $e) {
        if ($isCli) {
            echo "Error while syncing interface results. Please check error log for more details." . PHP_EOL;
        }
        $db->connection('interface')->rollbackTransaction();
        LoggerUtility::logError($e->getMessage(),  [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'last_interface_db_query' => $db->connection('interface')->getLastQuery(),
            'last_interface_db_error' => $db->connection('interface')->getLastError(),
            'last_default_db_query' => $db->connection('default')->getLastQuery(),
            'last_default_db_error' => $db->connection('default')->getLastError(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    // Close SQLite connection
    if ($sqliteConnected && $sqliteDb !== null) {
        $sqliteDb = null;
    }

    if (!empty($addedOnValues)) {

        $maxAddedOn = DateUtility::getDateTime(max($addedOnValues));

        // Update s_vlsm_instance with the maximum added_on
        $db->connection('default')->update('s_vlsm_instance', ['last_interface_sync' => $maxAddedOn]);
    }

    // Delete the lock file after execution completes
    MiscUtility::deleteLockFile($lockFile);
}
