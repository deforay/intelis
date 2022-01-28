<?php

require_once(__DIR__ . "/../startup.php");

if (!isset($interfaceConfig['enabled']) || $interfaceConfig['enabled'] === false) {
    error_log('Interfacing is not enabled. Please enable it in configuration.');
    exit;
}

$db  = MysqliDb::getInstance();

$usersModel = new \Vlsm\Models\Users();
$general = new \Vlsm\Models\General();

$labId = $general->getSystemConfig('sc_testing_lab_id');

if (empty($labId)) {
    echo "No Lab ID set in System Config";
    exit(0);
}

$db->addConnection('interface', array(
    'host' => $interfaceConfig['dbHost'],
    'username' => $interfaceConfig['dbUser'],
    'password' => $interfaceConfig['dbPassword'],
    'db' =>  $interfaceConfig['dbName'],
    'port' => (!empty($interfaceConfig['dbPort']) ? $interfaceConfig['dbPort'] : 3306),
    'charset' => (!empty($interfaceConfig['dbCharset']) ? $interfaceConfig['dbCharset'] : 'utf8mb4')
));


//get the value from interfacing DB
$interfaceQuery = "SELECT * FROM `orders` WHERE `result_status` = 1 AND `lims_sync_status`= 0";

$interfaceInfo = $db->connection('interface')->rawQuery($interfaceQuery);

$numberOfResults = 0;
if (count($interfaceInfo) > 0) {

    $availableModules = array();

    if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
        $availableModules['vl_sample_id'] = 'vl_request_form';
        $vlLock = $general->getGlobalConfig('lock_approved_vl_samples');
    }

    if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
        $availableModules['eid_id'] = 'eid_form';
        $eidlock = $general->getGlobalConfig('lock_approved_eid_samples');
    }

    if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
        $availableModules['covid19_id'] = 'form_covid19';
        $covid19Lock = null;
    }

    if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) {
        $availableModules['hepatitis_id'] = 'form_hepatitis';
        $hepatitisLock = null;
    }


    foreach ($interfaceInfo as $key => $result) {
        if (empty($result['test_id']))  continue;
        $tableInfo = array();
        foreach ($availableModules as $individualIdColumn => $individualTableName) {
            $tableQuery = "SELECT * FROM $individualTableName WHERE sample_code = '" . $result['test_id'] . "'";
            $tableInfo = $db->rawQueryOne($tableQuery);
            if (isset($tableInfo[$individualIdColumn]) && !empty($tableInfo[$individualIdColumn])) {
                break;
            }
        }

        if (isset($tableInfo['vl_sample_id'])) {
            $absDecimalVal = null;
            $absVal = null;
            $logVal = null;
            $txtVal = null;
            //set result in result fields
            if (trim($result['results']) != "") {

                $vlResult = trim($result['results']);
                $unit = trim($result['test_unit']);



                if ($vlResult == "< INF") {
                    $absDecimalVal = 839;
                    $vlResult = $absVal = 839;
                    $logVal = 2.92;
                } else if ($vlResult == "< Titer min") {
                    $absDecimalVal = 20;
                    $txtVal = $vlResult = $absVal = "< 20";
                } else if ($vlResult == "> Titer max") {
                    $absDecimalVal = 10000000;
                    $txtVal = $vlResult = $absVal = "> 1000000";
                } else if (strpos($vlResult, "<") !== false) {
                    $logVal = str_replace("<", "", $vlResult);
                    $absDecimalVal = round((float) round(pow(10, $logVal) * 100) / 100);
                    $txtVal = $vlResult = $absVal = "< " . trim($absDecimalVal);
                } else if (strpos($vlResult, ">") !== false) {
                    $logVal = str_replace(">", "", $vlResult);
                    $absDecimalVal = round((float) round(pow(10, $logVal) * 100) / 100);
                    $txtVal = $vlResult = $absVal = "> " . trim($absDecimalVal);
                } else if (strpos($unit, '10') !== false) {
                    $unitArray = explode(".", $unit);
                    $exponentArray = explode("*", $unitArray[0]);
                    $multiplier = pow($exponentArray[0], $exponentArray[1]);
                    $vlResult = $vlResult * $multiplier;
                    $unit = $unitArray[1];
                } else if (strpos($unit, 'Log') !== false && is_numeric($vlResult)) {
                    $logVal = $vlResult;
                    $vlResult = $absVal = $absDecimalVal = round((float) round(pow(10, $logVal) * 100) / 100);
                } else if (strpos($vlResult, 'E+') !== false || strpos($vlResult, 'E-') !== false) {
                    if (strpos($vlResult, '< 2.00E+1') !== false) {
                        $vlResult = "< 20";
                        //$vlResultCategory = 'Suppressed';
                    } else {
                        $resultArray = explode("(", $vlResult);
                        $exponentArray = explode("E", $resultArray[0]);
                        $vlResult = (float) $resultArray[0];
                        $absDecimalVal = (float) trim($vlResult);
                        $logVal = round(log10($absDecimalVal), 2);
                    }
                } else {
                    $vlResult = $txtVal = trim($result['results']);
                }

                // if (is_numeric($vlResult)) {
                //     $absVal = (float) trim($vlResult);
                //     $absDecimalVal = (float) trim($vlResult);
                //     $logVal = round(log10($absDecimalVal), 2);
                // } else {
                //     if ($vlResult == "< Titer min") {
                //         $absDecimalVal = 20;
                //         $txtVal = $vlResult = $absVal = "< 20";
                //     } else if ($vlResult == "> Titer max") {
                //         $absDecimalVal = 10000000;
                //         $txtVal = $vlResult = $absVal = ">1000000";
                //     } else if (strpos($vlResult, "<") !== false) {
                //         $vlResult = str_replace("<", "", $vlResult);
                //         $absDecimalVal = (float) trim($vlResult);
                //         $logVal = round(log10($absDecimalVal), 2);
                //         $absVal = "< " . (float) trim($vlResult);
                //     } else if (strpos($vlResult, ">") !== false) {
                //         $vlResult = str_replace(">", "", $vlResult);
                //         $absDecimalVal = (float) trim($vlResult);
                //         $logVal = round(log10($absDecimalVal), 2);
                //         $absVal = "> " . (float) trim($vlResult);
                //     } else {
                //         $txtVal = trim($result['results']);
                //     }
                // }
            }

            $userId = $usersModel->addUserIfNotExists($result['tested_by']);

            $data = array(
                'lab_id' => $labId,
                'tested_by' => $userId,
                'result_approved_by' => $userId,
                'result_approved_datetime' => $result['authorised_date_time'],
                'sample_tested_datetime' => $result['result_accepted_date_time'],
                'result_value_log' => $logVal,
                'result_value_absolute' => $absVal,
                'result_value_absolute_decimal' => $absDecimalVal,
                'result_value_text' => $txtVal,
                'result' => $vlResult,
                'vl_test_platform' => $result['machine_used'],
                'result_status' => 7,
                'result_printed_datetime' => NULL,
                'result_dispatched_datetime' => NULL,
                'data_sync' => 0
            );
            /* Updating the high and low viral load data */
            if ($data['result_status'] == 4 || $data['result_status'] == 7) {
                $vlDb = new \Vlsm\Models\Vl();
                $data['vl_result_category'] = $vlDb->getVLResultCategory($data['result_status'], $data['result']);
            }
            if ($vlLock == 'yes' && $data['result_status'] == 7) {
                $data['locked'] = 'yes';
            }
            $db = $db->where('vl_sample_id', $tableInfo['vl_sample_id']);
            $vlUpdateId = $db->update('vl_request_form', $data);
            $numberOfResults++;
            if ($vlUpdateId) {
                $interfaceData = array(
                    'lims_sync_status' => 1,
                    'lims_sync_date_time' => date('Y-m-d H:i:s'),
                );
                $db->connection('interface')->where('id', $result['id']);
                $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
            }
        } else if (isset($tableInfo['eid_id'])) {

            $absDecimalVal = null;
            $absVal = null;
            $logVal = null;
            $txtVal = null;
            //set result in result fields
            if (trim($result['results']) != "") {

                if (strpos(strtolower($result['results']), 'not detected') !== false) {
                    $eidResult = 'negative';
                } else if ((strpos(strtolower($result['results']), 'detected') !== false) || (strpos(strtolower($result['results']), 'passed') !== false)) {
                    $eidResult = 'positive';
                } else {
                    $eidResult = 'indeterminate';
                }
            }

            $data = array(
                'tested_by' => $result['tested_by'],
                'result_approved_by' => $result['tested_by'],
                'result_approved_datetime' => $result['authorised_date_time'],
                'sample_tested_datetime' => $result['result_accepted_date_time'],
                'result' => $eidResult,
                'eid_test_platform' => $result['machine_used'],
                'result_status' => 7,
                'result_printed_datetime' => NULL,
                'result_dispatched_datetime' => NULL,
                'data_sync' => 0
            );
            if ($eidlock == 'yes' && $data['result_status'] == 7) {
                $data['locked'] = 'yes';
            }
            $db = $db->where('eid_id', $tableInfo['eid_id']);
            $eidUpdateId = $db->update('eid_form', $data);
            $numberOfResults++;
            if ($eidUpdateId) {
                $interfaceData = array(
                    'lims_sync_status' => 1,
                    'lims_sync_date_time' => date('Y-m-d H:i:s'),
                );
                $db->connection('interface')->where('id', $result['id']);
                $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
            }
        } else if (isset($tableInfo['covid19_id'])) {
        } else if (isset($tableInfo['hepatitis_id'])) {


            $absDecimalVal = null;
            $absVal = null;
            $logVal = null;
            $txtVal = null;
            $otherFieldResult = null;
            $testType = strtolower($tableInfo['hepatitis_test_type']);
            if ($testType == 'hbv') {
                $resultField = "hbv_vl_count";
                $otherField = "hcv_vl_count";
            } else if ($testType == 'hcv') {
                $resultField = "hcv_vl_count";
                $otherField = "hbv_vl_count";
            } else {
                continue;
            }
            //set result in result fields
            if (trim($result['results']) != "") {

                $hepatitisResult = trim($result['results']);
                $unit = trim($result['test_unit']);

                if (strpos($unit, 'Log') !== false) {
                    if (is_numeric($hepatitisResult)) {
                        $logVal = $hepatitisResult;
                        $hepatitisResult = $absVal = $absDecimalVal = round((float) round(pow(10, $logVal) * 100) / 100);
                    } else {
                        if ($hepatitisResult == "< Titer min") {
                            $absDecimalVal = 20;
                            $txtVal = $hepatitisResult = $absVal = "< 20";
                        } else if ($hepatitisResult == "> Titer max") {
                            $absDecimalVal = 10000000;
                            $txtVal = $hepatitisResult = $absVal = ">1000000";
                        } else if (strpos($hepatitisResult, "<") !== false) {
                            $logVal = str_replace("<", "", $hepatitisResult);
                            $absDecimalVal = round((float) round(pow(10, $logVal) * 100) / 100);
                            $txtVal = $hepatitisResult = $absVal = "< " . trim($absDecimalVal);
                        } else if (strpos($hepatitisResult, ">") !== false) {
                            $logVal = str_replace(">", "", $hepatitisResult);
                            $absDecimalVal = round((float) round(pow(10, $logVal) * 100) / 100);
                            $txtVal = $hepatitisResult = $absVal = "> " . trim($absDecimalVal);
                        } else {
                            $hepatitisResult = $txtVal = trim($result['results']);
                        }
                    }
                } else if (strpos($unit, '10') !== false) {
                    $unitArray = explode(".", $unit);
                    $exponentArray = explode("*", $unitArray[0]);
                    $multiplier = pow($exponentArray[0], $exponentArray[1]);
                    $hepatitisResult = $hepatitisResult * $multiplier;
                    $unit = $unitArray[1];
                } else if (strpos($hepatitisResult, 'E+') !== false || strpos($hepatitisResult, 'E-') !== false) {
                    if (strpos($hepatitisResult, '< 2.00E+1') !== false) {
                        $hepatitisResult = "< 20";
                        //$vlResultCategory = 'Suppressed';
                    } else if (strpos($hepatitisResult, '>') !== false) {
                        $hepatitisResult = str_replace(">", "", $hepatitisResult);
                        $absDecimalVal = (float) $hepatitisResult;
                        $txtVal = $hepatitisResult = $absVal = "> " . trim($absDecimalVal);
                    } else {
                        $resultArray = explode("(", $hepatitisResult);
                        $exponentArray = explode("E", $resultArray[0]);
                        $hepatitisResult = (float) $resultArray[0];
                        $absDecimalVal = (float) trim($hepatitisResult);
                        $logVal = round(log10($absDecimalVal), 2);
                    }
                } else if (is_numeric($hepatitisResult)) {
                    $absVal = (float) trim($hepatitisResult);
                    $absDecimalVal = (float) trim($hepatitisResult);
                    $logVal = round(log10($absDecimalVal), 2);
                } else {
                    if ($hepatitisResult == "< Titer min") {
                        $absDecimalVal = 20;
                        $txtVal = $hepatitisResult = $absVal = "< 20";
                    } else if ($hepatitisResult == "> Titer max") {
                        $absDecimalVal = 10000000;
                        $txtVal = $hepatitisResult = $absVal = ">1000000";
                    } else if (strpos($hepatitisResult, "<") !== false) {
                        $hepatitisResult = str_replace("<", "", $hepatitisResult);
                        $absDecimalVal = (float) trim($hepatitisResult);
                        $logVal = round(log10($absDecimalVal), 2);
                        $absVal = "< " . (float) trim($hepatitisResult);
                    } else if (strpos($hepatitisResult, ">") !== false) {
                        $hepatitisResult = str_replace(">", "", $hepatitisResult);
                        $absDecimalVal = (float) trim($hepatitisResult);
                        $logVal = round(log10($absDecimalVal), 2);
                        $absVal = "> " . (float) trim($hepatitisResult);
                    } else {
                        $txtVal = trim($result['results']);
                    }
                }
            }

            $userId = $usersModel->addUserIfNotExists($result['tested_by']);

            $data = array(
                'lab_id' => $labId,
                'tested_by' => $userId,
                'result_approved_by' => $userId,
                'result_approved_datetime' => $result['authorised_date_time'],
                'sample_tested_datetime' => $result['result_accepted_date_time'],
                $resultField => $hepatitisResult,
                $otherField => $otherFieldResult,
                'hepatitis_test_platform' => $result['machine_used'],
                'result_status' => 7,
                'result_printed_datetime' => NULL,
                'result_dispatched_datetime' => NULL,
                'data_sync' => 0
            );

            if ($hepatitisLock == 'yes' && $data['result_status'] == 7) {
                $data['locked'] = 'yes';
            }
            $db = $db->where('hepatitis_id', $tableInfo['hepatitis_id']);
            $vlUpdateId = $db->update('form_hepatitis', $data);
            $numberOfResults++;
            if ($vlUpdateId) {
                $interfaceData = array(
                    'lims_sync_status' => 1,
                    'lims_sync_date_time' => date('Y-m-d H:i:s'),
                );
                $db->connection('interface')->where('id', $result['id']);
                $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
            }
        } else {
            $interfaceData = array(
                'lims_sync_status' => 2,
                'lims_sync_date_time' => date('Y-m-d H:i:s'),
            );
            $db->connection('interface')->where('id', $result['id']);
            $interfaceUpdateId = $db->connection('interface')->update('orders', $interfaceData);
        }
    }


    if ($numberOfResults > 0) {
        $importedBy = isset($_SESSION['userId']) ? $_SESSION['userId'] : 'AUTO';
        $general->resultImportStats($numberOfResults, 'interface', $importedBy);
    }
}
