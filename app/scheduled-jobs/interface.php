<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

use App\Services\VlService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

if (
    !isset(SYSTEM_CONFIG['interfacing']['enabled']) ||
    SYSTEM_CONFIG['interfacing']['enabled'] === false
) {
    error_log('Interfacing is not enabled. Please enable it in configuration.');
    exit;
}

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$labId = $general->getSystemConfig('sc_testing_lab_id');

if (empty($labId)) {
    echo "No Lab ID set in System Config";
    exit(0);
}

$mysqlConnected = false;
$sqliteConnected = false;

if (
    !empty(SYSTEM_CONFIG['interfacing']['database']['host']) &&
    !empty(SYSTEM_CONFIG['interfacing']['database']['username'])
) {
    $mysqlConnected = true;
    $db->addConnection('interface', SYSTEM_CONFIG['interfacing']['database']);
}

$sqliteDb = null;

if (!empty(SYSTEM_CONFIG['interfacing']['sqlite3Path'])) {
    $sqliteConnected = true;
    $sqliteDb = new PDO("sqlite:" . SYSTEM_CONFIG['interfacing']['sqlite3Path']);
}

//get the value from interfacing DB
$interfaceQuery = "SELECT * FROM `orders`
                    WHERE `result_status` = 1 AND
                    `lims_sync_status`= 0
                    ORDER BY analysed_date_time ASC";
if ($mysqlConnected) {
    $interfaceData = $db->connection('interface')->rawQuery($interfaceQuery);
} elseif ($sqliteConnected) {
    $interfaceData = $sqliteDb->query($interfaceQuery)->fetchAll(PDO::FETCH_ASSOC);
} else {
    exit(0);
}


$numberOfResults = 0;
if (!empty($interfaceData)) {

    $availableModules = [];

    if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
        $availableModules['vl_sample_id'] = 'form_vl';
        $platform["vl_sample_id"] = "vl_test_platform";
    }

    if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
        $availableModules['eid_id'] = 'form_eid';
        $platform["eid_id"] = "eid_test_platform";
    }

    if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
        $availableModules['covid19_id'] = 'form_covid19';
        $platform["covid19_id"] = "covid19_test_platform";
    }

    if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {
        $availableModules['hepatitis_id'] = 'form_hepatitis';
        $platform["hepatitis_id"] = "hepatitis_test_platform";
    }
    if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
        $availableModules['tb_id'] = 'form_tb';
        $platform["tb_id"] = "tb_test_platform";
    }

    $processedResults = [];
    $allowRepeatedTests = false;

    foreach ($interfaceData as $key => $result) {

        if (empty($result['order_id'])) {
            continue;
        }

        if ($allowRepeatedTests === false && in_array($result['order_id'], $processedResults)) {
            continue;
        }

        $tableInfo = [];
        foreach ($availableModules as $individualIdColumn => $individualTableName) {
            $tableQuery = "SELECT * FROM $individualTableName WHERE sample_code = ?";
            $tableInfo = $db->rawQueryOne($tableQuery, [$result['order_id']]);
            if (!empty($tableInfo[$individualIdColumn])) {
                break;
            }
        }

        //Getting Approved By and Reviewed By from Instruments table
        $instrumentDetails = $db->rawQueryOne("SELECT * FROM instruments
                                                WHERE machine_name like ?", [$result['machine_used']]);

        if (empty($instrumentDetails)) {
            $sql = "SELECT * FROM instruments
                    INNER JOIN instrument_machines ON instruments.config_id = instrument_machines.config_machine_id
                    WHERE instrument_machines.config_machine_name LIKE ?";
            $instrumentDetails = $db->rawQueryOne($sql, [$result['machine_used']]);
        }

        $approved = !empty($instrumentDetails['approved_by']) ? json_decode($instrumentDetails['approved_by'], true) : [];
        $reviewed = !empty($instrumentDetails['reviewed_by']) ? json_decode($instrumentDetails['reviewed_by'], true) : [];

        if (isset($tableInfo['vl_sample_id'])) {

            /** @var VlService $vlService */
            $vlService = ContainerRegistry::get(VlService::class);
            $absDecimalVal = null;
            $absVal = null;
            $logVal = null;
            $txtVal = null;
            //set result in result fields
            if (trim($result['results']) != "") {

                $vlResult = trim(str_ireplace(['cp/ml', 'copies/ml'], '', $result['results']));

                $unit = trim($result['test_unit']);

                if ($vlResult == "-1.00") {
                    $vlResult = "Target Not Detected";
                }

                $logVal = null;
                $absDecimalVal = null;
                $absVal = null;
                $txtVal = null;
                $interpretedResults = [];

                if (!empty($vlResult) && !in_array(strtolower($vlResult), ['fail', 'failed', 'failure', 'error', 'err'])) {
                    $interpretedResults = $vlService->interpretViralLoadResult($vlResult, $unit, $instrumentDetails['low_vl_result_text']);

                    if (!empty($interpretedResults)) {
                        $logVal = $interpretedResults['logVal'];
                        $vlResult = $interpretedResults['result'];
                        $absDecimalVal = $interpretedResults['absDecimalVal'];
                        $absVal = $interpretedResults['absVal'];
                        $txtVal = $interpretedResults['txtVal'];
                    }
                }
            }

            $testedByUserId = $approvedByUserId = $reviewedByUserId = null;
            // if ^ exists it means the Operator Name has both tester and releaser name
            if (strpos(strtolower($result['tested_by']), '^') !== false) {
                $operatorArray = explode("^", $result['tested_by']);
                $tester = $operatorArray[0];
                $testedByUserId = $usersService->getOrCreateUser($tester);
            } else {
                $testedByUserId = $usersService->getOrCreateUser($result['tested_by']);
            }

            $data = [
                'lab_id' => $labId,
                'tested_by' => $testedByUserId,
                'result_approved_by' => (isset($approved['vl']) && $approved['vl'] != "") ? $approved['vl'] : null,
                'result_approved_datetime' => $result['authorised_date_time'],
                'result_reviewed_by' => (isset($reviewed['vl']) && $reviewed['vl'] != "") ? $reviewed['vl'] : null,
                'result_reviewed_datetime' => $result['authorised_date_time'],
                'sample_tested_datetime' => $result['result_accepted_date_time'],
                'result_value_log' => $logVal,
                'result_value_absolute' => $absVal,
                'result_value_absolute_decimal' => $absDecimalVal,
                'result_value_text' => $txtVal,
                'result' => $vlResult,
                'vl_test_platform' => $result['machine_used'],
                'result_status' => 7,
                'manual_result_entry' => 'no',
                'result_printed_datetime' => null,
                'result_dispatched_datetime' => null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];

            if (strtolower($vlResult) == 'failed' || strtolower($vlResult) == 'fail' || strtolower($vlResult) == 'invalid' || strtolower($vlResult) == 'inconclusive') {
                $data['result_status'] = SAMPLE_STATUS\TEST_FAILED; // Invalid
            }

            $data['vl_result_category'] = $vlService->getVLResultCategory($data['result_status'], $data['result']);
            if ($data['vl_result_category'] == 'failed' || $data['vl_result_category'] == 'invalid') {
                $data['result_status'] = SAMPLE_STATUS\TEST_FAILED;
            } elseif ($data['vl_result_category'] == 'rejected') {
                $data['result_status'] = SAMPLE_STATUS\REJECTED;
            }
            $db = $db->where('vl_sample_id', $tableInfo['vl_sample_id']);
            $vlUpdateId = $db->update('form_vl', $data);
            $numberOfResults++;
            $processedResults[] = $result['order_id'];
            if ($vlUpdateId) {
                if ($mysqlConnected) {
                    $interfaceData = [
                        'lims_sync_status' => 1,
                        'lims_sync_date_time' => DateUtility::getCurrentDateTime(),
                    ];
                    $db->connection('interface')->where('order_id', $result['order_id']);
                    $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
                }

                if ($sqliteConnected) {
                    // Prepare the SQL query
                    $stmt = $sqliteDb->prepare("UPDATE orders SET lims_sync_status = :lims_sync_status WHERE order_id = :order_id");

                    // Bind the values to the placeholders in the prepared statement
                    $stmt->bindValue(':lims_sync_status', 1, PDO::PARAM_INT);
                    $stmt->bindValue(':lims_sync_date_time', DateUtility::getCurrentDateTime());
                    $stmt->bindValue(':order_id', $result['order_id'], PDO::PARAM_INT);

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
            if (trim($result['results']) != "") {

                if (strpos(strtolower($result['results']), 'not detected') !== false) {
                    $eidResult = 'negative';
                } elseif ((strpos(strtolower($result['results']), 'detected') !== false) || (strpos(strtolower($result['results']), 'passed') !== false)) {
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
                'result_status' => 7,
                'manual_result_entry' => 'no',
                'result_approved_by' => (isset($approved['eid']) && $approved['eid'] != "") ? $approved['eid'] : null,
                'result_reviewed_by' => (isset($reviewed['eid']) && $reviewed['eid'] != "") ? $reviewed['eid'] : null,
                'result_printed_datetime' => null,
                'result_dispatched_datetime' => null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];

            $db = $db->where('eid_id', $tableInfo['eid_id']);
            $eidUpdateId = $db->update('form_eid', $data);
            $numberOfResults++;
            $processedResults[] = $result['order_id'];
            if ($eidUpdateId) {
                if ($mysqlConnected) {
                    $interfaceData = [
                        'lims_sync_status' => 1,
                        'lims_sync_date_time' => DateUtility::getCurrentDateTime(),
                    ];
                    $db->connection('interface')->where('order_id', $result['order_id']);
                    $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
                }
                if ($sqliteConnected) {
                    // Prepare the SQL query
                    $stmt = $sqliteDb->prepare("UPDATE orders SET lims_sync_status = :lims_sync_status WHERE order_id = :order_id");

                    // Bind the values to the placeholders in the prepared statement
                    $stmt->bindValue(':lims_sync_status', 1, PDO::PARAM_INT);
                    $stmt->bindValue(':lims_sync_date_time', DateUtility::getCurrentDateTime());
                    $stmt->bindValue(':order_id', $result['order_id'], PDO::PARAM_INT);

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
            $testType = strtolower($tableInfo['hepatitis_test_type']);
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
            if (trim($result['results']) != "") {

                $hepatitisResult = trim($result['results']);
                $unit = trim($result['test_unit']);
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
                'result_status' => 7,
                'manual_result_entry' => 'no',
                'result_approved_by' => $approved['hepatitis'] ?? null,
                'result_reviewed_by' => $reviewed['hepatitis'] ?? null,
                'result_printed_datetime' => null,
                'result_dispatched_datetime' => null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];

            $db = $db->where('hepatitis_id', $tableInfo['hepatitis_id']);
            $vlUpdateId = $db->update('form_hepatitis', $data);
            $numberOfResults++;
            $processedResults[] = $result['order_id'];
            if ($vlUpdateId) {
                if ($mysqlConnected) {
                    $interfaceData = [
                        'lims_sync_status' => 1,
                        'lims_sync_date_time' => DateUtility::getCurrentDateTime(),
                    ];
                    $db->connection('interface')->where('order_id', $result['order_id']);
                    $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
                }
                if ($sqliteConnected) {
                    // Prepare the SQL query
                    $stmt = $sqliteDb->prepare("UPDATE orders SET lims_sync_status = :lims_sync_status WHERE order_id = :order_id");

                    // Bind the values to the placeholders in the prepared statement
                    $stmt->bindValue(':lims_sync_status', 1, PDO::PARAM_INT);
                    $stmt->bindValue(':lims_sync_date_time', DateUtility::getCurrentDateTime());
                    $stmt->bindValue(':order_id', $result['order_id'], PDO::PARAM_INT);

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
                $db->connection('interface')->where('order_id', $result['order_id']);
                $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
            }
            if ($sqliteConnected) {
                // Prepare the SQL query
                $stmt = $sqliteDb->prepare("UPDATE orders SET lims_sync_status = :lims_sync_status WHERE order_id = :order_id");

                // Bind the values to the placeholders in the prepared statement
                $stmt->bindValue(':lims_sync_status', 2, PDO::PARAM_INT);
                $stmt->bindValue(':lims_sync_date_time', DateUtility::getCurrentDateTime());
                $stmt->bindValue(':order_id', $result['order_id'], PDO::PARAM_INT);

                // Execute the prepared statement
                $stmt->execute();
            }
        }
    }


    if ($numberOfResults > 0) {
        $importedBy = $_SESSION['userId'] ?? 'AUTO';
        $general->resultImportStats($numberOfResults, 'interface', $importedBy);
    }
}
