#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

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
    LoggerUtility::log('error', 'Interfacing is not enabled. Please enable it in configuration.');
    exit;
}


/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TestResultsService $testResultsService */
$testResultsService = ContainerRegistry::get(TestResultsService::class);

$labId = $general->getSystemConfig('sc_testing_lab_id');
$formId = (int) $general->getGlobalConfig('vl_form');

if (empty($labId)) {
    LoggerUtility::log('error', "No Lab ID set in System Config. Skipping Interfacing Results");
    exit(0);
}

$lastInterfaceSync = $db->connection('default')->getValue('s_vlsm_instance', 'last_interface_sync');

$mysqlConnected = false;
$sqliteConnected = false;

if (!empty(SYSTEM_CONFIG['interfacing']['database']['host']) && !empty(SYSTEM_CONFIG['interfacing']['database']['username'])) {
    $mysqlConnected = true;
    $db->addConnection('interface', SYSTEM_CONFIG['interfacing']['database']);
}

$sqliteDb = null;

if (!empty(SYSTEM_CONFIG['interfacing']['sqlite3Path'])) {
    $sqliteConnected = true;
    $sqliteDb = new PDO("sqlite:" . SYSTEM_CONFIG['interfacing']['sqlite3Path']);
}

//get the value from interfacing DB
if ($mysqlConnected) {

    if (!empty($lastInterfaceSync)) {
        $db->connection('interface')
            ->where("added_on > '$lastInterfaceSync' OR lims_sync_status = 0");
    }
    $db->connection('interface')->where('result_status', 1);
    $db->connection('interface')->orderBy('analysed_date_time', 'asc');
    $interfaceData = $db->connection('interface')->get('orders');
} elseif ($sqliteConnected) {

    $where = [];
    $where[] = " result_status = 1 ";
    if (!empty($lastInterfaceSync)) {
        $where[] = " added_on > '$lastInterfaceSync' OR lims_sync_status = 0";
    }
    $where = implode(' AND ', $where);
    $interfaceQuery = "SELECT * FROM `orders`
                        WHERE $where
                        ORDER BY analysed_date_time ASC";
    $interfaceData = $sqliteDb->query($interfaceQuery)->fetchAll(PDO::FETCH_ASSOC);
} else {
    exit(0);
}


$additionalColumns = [
    'form_hepatitis' => ['hepatitis_test_type'],
];

$numberOfResults = 0;
if (!empty($interfaceData)) {


    $totalResults = count($interfaceData); // Get the total number of items

    echo "Processing results from Interface Tool" . PHP_EOL;

    $availableModules = [];

    $activeModules = SystemService::getActiveModules(onlyTests: true);

    foreach ($activeModules as $module) {
        $primaryKey = TestsService::getTestPrimaryKeyColumn($module);
        $availableModules[$primaryKey] = TestsService::getTestTableName($module);
    }

    $processedResults = [];
    //$allowRepeatedTests = false;

    foreach ($interfaceData as $key => $result) {

        MiscUtility::displayProgressBar($key + 1, $totalResults); // Update progress bar

        if (empty($result['order_id']) && empty($result['test_id'])) {
            continue;
        }

        // if ($allowRepeatedTests === false && (in_array($result['test_id'], $processedResults) || in_array($result['order_id'], $processedResults))) {
        //     continue;
        // }


        $tableInfo = [];
        foreach ($availableModules as $primaryKeyColumn => $individualTableName) {

            $columnsToSelect = $primaryKeyColumn . ", sample_code, remote_sample_code";

            // If the table name is there in $additionalColumns, add the additional columns
            if (!empty($additionalColumns) && array_key_exists($individualTableName, $additionalColumns)) {
                $extraColumnsString = implode(', ', $additionalColumns[$individualTableName]);
                $columnsToSelect = "$primaryKeyColumn, $extraColumnsString";
            }

            $tableQuery = "SELECT $columnsToSelect FROM $individualTableName WHERE (sample_code = ? OR remote_sample_code = ?) OR (sample_code = ? OR remote_sample_code = ?)";

            // Execute the query
            $tableInfo = $db->rawQueryOne($tableQuery, [$result['order_id'], $result['order_id'], $result['test_id'], $result['test_id']]);

            // If we found the information, break out of the loop
            if (!empty($tableInfo[$primaryKeyColumn])) {
                break;
            }
        }

        //Getting Approved By and Reviewed By from Instruments table
        $instrumentDetails = $db->rawQueryOne("SELECT * FROM instruments
                                                WHERE machine_name like ?", [$result['machine_used']]);

        if (empty($instrumentDetails)) {
            $sql = "SELECT * FROM instruments
                    INNER JOIN instrument_machines ON instruments.instrument_id = instrument_machines.instrument_id
                    WHERE instrument_machines.config_machine_name LIKE ?";
            $instrumentDetails = $db->rawQueryOne($sql, [$result['machine_used']]);
        }

        $approved = !empty($instrumentDetails['approved_by']) ? json_decode((string) $instrumentDetails['approved_by'], true) : [];
        $reviewed = !empty($instrumentDetails['reviewed_by']) ? json_decode((string) $instrumentDetails['reviewed_by'], true) : [];

        if (isset($tableInfo['vl_sample_id'])) {

            /** @var VlService $vlService */
            $vlService = ContainerRegistry::get(VlService::class);
            $absDecimalVal = null;
            $absVal = null;
            $logVal = null;
            $txtVal = null;
            $vlResult = null;
            //set result in result fields
            if (!empty($result['results'])) {

                $vlResult = trim(str_ireplace(['cp/ml', 'copies/ml'], '', (string) $result['results']));

                $unit = trim((string) $result['test_unit']);

                if ($vlResult == "-1.00") {
                    $vlResult = "Target Not Detected";
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
                        $vlResult = $interpretedResults['result'];
                        $absDecimalVal = $interpretedResults['absDecimalVal'];
                        $absVal = $interpretedResults['absVal'];
                        $txtVal = $interpretedResults['txtVal'];
                    }
                }
            }

            $testedByUserId = null;
            // if ^ exists it means the Operator Name has both tester and releaser name
            if (str_contains(strtolower((string)$result['tested_by']), '^')) {
                $operatorArray = explode("^", (string) $result['tested_by']);
                $tester = $operatorArray[0];
                $testedByUserId = $usersService->getOrCreateUser($tester);
            } else {
                $testedByUserId = $usersService->getOrCreateUser($result['tested_by']);
            }

            $data = [
                'lab_id' => $labId,
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
                'vl_test_platform' => $instrumentDetails['machine_name'] ?? $result['machine_used'],
                'result_status' => SAMPLE_STATUS\ACCEPTED,
                'manual_result_entry' => 'no',
                'result_printed_datetime' => null,
                'result_dispatched_datetime' => null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];

            if ($formId === COUNTRY\CAMEROON && !empty($result['raw_text'])) {
                $sampleCode = $tableInfo['sample_code'];

                $stringToSearch = preg_quote($sampleCode, '/') . '\^CV\s+(\d+)';

                $pattern = '/' . $stringToSearch . '/i';

                if (preg_match($pattern, $result['raw_text'], $matches)) {
                    $data['cv_number'] = trim($matches[1]);
                } else {
                    $data['cv_number'] = null;
                }
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
            $db->where('vl_sample_id', $tableInfo['vl_sample_id']);
            $vlUpdateId = $db->update('form_vl', $data);
            $numberOfResults++;
            // $processedResults[] = $result['order_id'];
            //  $processedResults[] = $result['test_id'];
            if ($vlUpdateId) {
                if ($mysqlConnected) {
                    $interfaceData = [
                        'lims_sync_status' => 1,
                        'lims_sync_date_time' => DateUtility::getCurrentDateTime(),
                    ];
                    $db->connection('interface')->where('id', $result['id']);
                    $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
                }

                if ($sqliteConnected) {
                    // Prepare the SQL query
                    $stmt = $sqliteDb->prepare("UPDATE orders SET lims_sync_status = :lims_sync_status, lims_sync_date_time = :lims_sync_date_time WHERE id = :id");

                    // Bind the values to the placeholders in the prepared statement
                    $stmt->bindValue(':lims_sync_status', 1, PDO::PARAM_INT);
                    $stmt->bindValue(':lims_sync_date_time', DateUtility::getCurrentDateTime());
                    $stmt->bindValue(':id', $result['id'], PDO::PARAM_INT);

                    // Execute the prepared statement
                    $stmt->execute();
                }
            }
        } elseif (isset($tableInfo['eid_id'])) {

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
                    $eidResult = 'indeterminate';
                }
            }

            $data = [
                'tested_by' => $result['tested_by'],
                'result_approved_datetime' => $result['authorised_date_time'],
                'sample_tested_datetime' => $result['result_accepted_date_time'],
                'result' => $eidResult,
                'eid_test_platform' => $result['machine_used'],
                'result_status' => SAMPLE_STATUS\ACCEPTED,
                'manual_result_entry' => 'no',
                'result_approved_by' => $approved['eid'] ?? null,
                'result_reviewed_by' => $reviewed['eid'] ?? null,
                'result_printed_datetime' => null,
                'result_dispatched_datetime' => null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];

            $db->where('eid_id', $tableInfo['eid_id']);
            $eidUpdateId = $db->update('form_eid', $data);
            $numberOfResults++;
            // $processedResults[] = $result['order_id'];
            //  $processedResults[] = $result['test_id'];
            if ($eidUpdateId) {
                if ($mysqlConnected) {
                    $interfaceData = [
                        'lims_sync_status' => 1,
                        'lims_sync_date_time' => DateUtility::getCurrentDateTime(),
                    ];
                    $db->connection('interface')->where('id', $result['id']);
                    $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
                }
                if ($sqliteConnected) {
                    // Prepare the SQL query
                    $stmt = $sqliteDb->prepare("UPDATE orders SET lims_sync_status = :lims_sync_status, lims_sync_date_time = :lims_sync_date_time WHERE id = :id");

                    // Bind the values to the placeholders in the prepared statement
                    $stmt->bindValue(':lims_sync_status', 1, PDO::PARAM_INT);
                    $stmt->bindValue(':lims_sync_date_time', DateUtility::getCurrentDateTime());
                    $stmt->bindValue(':id', $result['id'], PDO::PARAM_INT);

                    // Execute the prepared statement
                    $stmt->execute();
                }
            }
        } elseif (isset($tableInfo['covid19_id'])) {

            // TODO: Add covid19 results

        } elseif (isset($tableInfo['hepatitis_id'])) {

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

            $data = [
                'lab_id' => $labId,
                'tested_by' => $userId,
                'result_approved_datetime' => $result['authorised_date_time'],
                'sample_tested_datetime' => $result['result_accepted_date_time'],
                $resultField => $hepatitisResult ?? null,
                $otherField => $otherFieldResult ?? null,
                'hepatitis_test_platform' => $result['machine_used'],
                'result_status' => SAMPLE_STATUS\ACCEPTED,
                'manual_result_entry' => 'no',
                'result_approved_by' => $approved['hepatitis'] ?? null,
                'result_reviewed_by' => $reviewed['hepatitis'] ?? null,
                'result_printed_datetime' => null,
                'result_dispatched_datetime' => null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];

            $db->where('hepatitis_id', $tableInfo['hepatitis_id']);
            $vlUpdateId = $db->update('form_hepatitis', $data);
            $numberOfResults++;
            // $processedResults[] = $result['order_id'];
            //  $processedResults[] = $result['test_id'];
            if ($vlUpdateId) {
                if ($mysqlConnected) {
                    $interfaceData = [
                        'lims_sync_status' => 1,
                        'lims_sync_date_time' => DateUtility::getCurrentDateTime(),
                    ];
                    $db->connection('interface')->where('id', $result['id']);
                    $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
                }
                if ($sqliteConnected) {
                    // Prepare the SQL query
                    $stmt = $sqliteDb->prepare("UPDATE orders SET lims_sync_status = :lims_sync_status, lims_sync_date_time = :lims_sync_date_time WHERE id = :id");

                    // Bind the values to the placeholders in the prepared statement
                    $stmt->bindValue(':lims_sync_status', 1, PDO::PARAM_INT);
                    $stmt->bindValue(':lims_sync_date_time', DateUtility::getCurrentDateTime());
                    $stmt->bindValue(':id', $result['id'], PDO::PARAM_INT);

                    // Execute the prepared statement
                    $stmt->execute();
                }
            }
        } else {
            if ($mysqlConnected) {
                $interfaceData = [
                    'lims_sync_status' => 2,
                    'lims_sync_date_time' => DateUtility::getCurrentDateTime(),
                ];
                $db->connection('interface')->where('id', $result['id']);
                $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
            }
            if ($sqliteConnected) {
                // Prepare the SQL query
                $stmt = $sqliteDb->prepare("UPDATE orders SET lims_sync_status = :lims_sync_status, lims_sync_date_time = :lims_sync_date_time WHERE id = :id");

                // Bind the values to the placeholders in the prepared statement
                $stmt->bindValue(':lims_sync_status', 2, PDO::PARAM_INT);
                $stmt->bindValue(':lims_sync_date_time', DateUtility::getCurrentDateTime());
                $stmt->bindValue(':id', $result['id'], PDO::PARAM_INT);

                // Execute the prepared statement
                $stmt->execute();
            }
        }

        if (!empty($result['added_on'])) {

            $data = [
                'last_interface_sync' => $result['added_on']
            ];

            $db->connection('default')->update('s_vlsm_instance', $data);
        }
    }

    if ($numberOfResults > 0) {
        $importedBy = $_SESSION['userId'] ?? 'AUTO';
        $testResultsService->resultImportStats($numberOfResults, 'interface', $importedBy);
    }
}
