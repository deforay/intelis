<?php

use App\Services\ApiService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

//this file receives the lab results and updates in the remote db
$jsonResponse = file_get_contents('php://input');


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

// /** @var ApiService $app */
// $app = \App\Registries\ContainerRegistry::get(ApiService::class);


$transactionId = $general->generateUUID();

$sampleCodes = $facilityIds = [];

if (!empty($jsonResponse) && $jsonResponse != '[]') {

    $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = ? AND table_name='form_covid19'";
    $allColResult = $db->rawQuery($allColumns, [SYSTEM_CONFIG['database']['db']]);

    $oneDimensionalArray = array_map('current', $allColResult);



    $lab = [];
    $resultData = [];
    $testResultsData = [];
    $testResultsData = [];
    $symptomsData = [];
    $comorbiditiesData = [];
    $options = [
        'decoder' => new ExtJsonDecoder(true)
    ];
    $parsedData = Items::fromString($jsonResponse, $options);
    foreach ($parsedData as $name => $data) {
        if ($name === 'labId') {
            $labId = $data;
        } else if ($name === 'result') {
            $resultData = $data;
        } else if ($name === 'testResults') {
            $testResultsData = $data;
        } else if ($name === 'symptoms') {
            //$symptomsData = $data;
        } else if ($name === 'comorbidities') {
            //$comorbiditiesData = $data;
        }
    }

    $counter = 0;
    foreach ($resultData as $key => $resultRow) {
        $counter++;
        foreach ($oneDimensionalArray as $result) {
            if (isset($resultRow[$result])) {
                $lab[$result] = $resultRow[$result];
            } else {
                $lab[$result] = null;
            }
        }

        //remove fields that we DO NOT NEED here
        $removeKeys = array(
            'covid19_id',
            'sample_package_id',
            'sample_package_code',
            //'last_modified_by',
            'request_created_by',
        );
        foreach ($removeKeys as $keys) {
            unset($lab[$keys]);
        }

        if (isset($resultRow['approved_by_name']) && $resultRow['approved_by_name'] != '') {

            $lab['result_approved_by'] = $usersService->addUserIfNotExists($resultRow['approved_by_name']);
            $lab['result_approved_datetime'] =  DateUtility::getCurrentDateTime();
            // we dont need this now
            //unset($resultRow['approved_by_name']);
        }

        $lab['data_sync'] = 1; //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
        $lab['last_modified_datetime'] = DateUtility::getCurrentDateTime();

        // unset($lab['request_created_by']);
        // unset($lab['last_modified_by']);
        // unset($lab['request_created_datetime']);

        if ($lab['result_status'] != 7 && $lab['result_status'] != 4) {
            unset($lab['result']);
            unset($lab['is_sample_rejected']);
            unset($lab['reason_for_sample_rejection']);
        }

        try {
            // Checking if Remote Sample Code is set, if not set we will check if Sample Code is set
            if (isset($lab['remote_sample_code']) && $lab['remote_sample_code'] != '') {
                $sQuery = "SELECT covid19_id,sample_code,remote_sample_code,remote_sample_code_key
                            FROM form_covid19 WHERE remote_sample_code= ?";
                $sResult = $db->rawQuery($sQuery, [$lab['remote_sample_code']]);
            } elseif (isset($lab['sample_code']) && !empty($lab['sample_code']) && !empty($lab['facility_id']) && !empty($lab['lab_id'])) {
                $sQuery = "SELECT covid19_id,sample_code,remote_sample_code,remote_sample_code_key
                            FROM form_covid19 WHERE sample_code= ? AND facility_id = ?";
                $sResult = $db->rawQuery($sQuery, [$lab['sample_code'], $lab['facility_id']]);
            } else {
                $sampleCodes[] = $lab['sample_code'];
                $facilityIds[] = $lab['facility_id'];
                continue;
            }

            if (!empty($sResult)) {
                $formAttributes = $general->jsonToSetString(
                    $lab['form_attributes'],
                    'form_attributes'
                );
                $lab['form_attributes'] = $db->func($formAttributes);
                $db = $db->where('covid19_id', $sResult[0]['covid19_id']);
                $id = $db->update('form_covid19', $lab);
            } else {
                $formAttributes = $general->jsonToSetString(
                    $lab['form_attributes'],
                    'form_attributes'
                );
                $lab['form_attributes'] = $db->func($formAttributes);
                $id = $db->insert('form_covid19', $lab);
            }
        } catch (Exception $e) {
            error_log($db->getLastError());
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            continue;
        }

        if ($id > 0 && isset($lab['sample_code'])) {
            $sampleCodes[] = $lab['sample_code'];
            $facilityIds[] = $lab['facility_id'];
        }
    }

    foreach ($testResultsData as $covid19Id => $testResults) {
        $db = $db->where('covid19_id', $covid19Id);
        $db->delete("covid19_tests");
        foreach ($testResults as $testId => $test) {
            $db->insert("covid19_tests", array(
                "covid19_id"                => $test['covid19_id'],
                "test_name"                 => $test['test_name'],
                "facility_id"               => $test['facility_id'],
                "sample_tested_datetime"    => $test['sample_tested_datetime'],
                "testing_platform"          => $test['testing_platform'],
                "result"                    => $test['result']
            ));
        }
    }
}

$payload = json_encode($sampleCodes);

$general->addApiTracking($transactionId, 'vlsm-system', $counter, 'results', 'covid19', $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labId);


$currentDateTime = DateUtility::getCurrentDateTime();

if (!empty($sampleCodes)) {
    $sql = 'UPDATE form_covid19 SET data_sync = ?,
                form_attributes = JSON_SET(COALESCE(form_attributes, "{}"), "$.remoteResultsSync", ?, "$.resultSyncTransactionId", ?)
                WHERE sample_code IN ("' . implode('","', $sampleCodes) . '")';
    $db->rawQuery($sql, array(1, $currentDateTime, $transactionId));
}

if (!empty($facilityIds)) {
    $facilityIds = array_unique(array_filter($facilityIds));
    $sql = 'UPDATE facility_details
                    SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.remoteResultsSync", ?, "$.covid19RemoteResultsSync", ?)
                    WHERE facility_id IN (' . implode(",", $facilityIds) . ')';
    $db->rawQuery($sql, array($currentDateTime, $currentDateTime));
}
$sql = 'UPDATE facility_details SET
            facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.lastResultsSync", ?, "$.covid19LastResultsSync", ?)
                WHERE facility_id = ?';
$db->rawQuery($sql, array($currentDateTime, $currentDateTime, $labId));

echo $payload;
