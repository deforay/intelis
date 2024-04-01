<?php

use App\Registries\AppRegistry;
use JsonMachine\Items;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


try {
    $db->beginTransaction();
    //this file receives the lab results and updates in the remote db
    //$jsonResponse = $contentEncoding = $request->getHeaderLine('Content-Encoding');

    /** @var ApiService $apiService */
    $apiService = ContainerRegistry::get(ApiService::class);

    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $jsonResponse = $apiService->getJsonFromRequest($request);

    // Create an array with all column names set to null
    $emptyLabArray = $general->getTableFieldsAsArray('form_geenric');

    //remove unwanted columns
    $unwantedColumns = [
        'sample_id',
        'sample_package_id',
        'sample_package_code',
        //'last_modified_by',
        'request_created_by',
        'result_printed_datetime'
    ];
    $emptyLabArray = MiscUtility::removeFromAssociativeArray($emptyLabArray, $unwantedColumns);

    $transactionId = $general->generateUUID();

    $sampleCodes = $facilityIds = [];
    $labId = null;
    if (!empty($jsonResponse) && $jsonResponse != '[]' && MiscUtility::isJSON($jsonResponse)) {

        $resultData = [];
        $testResultsData = [];
        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        foreach ($parsedData as $name => $data) {
            if ($name === 'labId') {
                $labId = $data;
            } else if ($name === 'testResults') {
                $testResultsData = $data;
            } else if ($name === 'result') {
                $resultData = $data;
            }
        }

        $counter = 0;
        foreach ($resultData as $key => $resultRow) {

            $counter++;
            // Overwrite the values in $emptyLabArray with the values in $resultRow
            $lab = array_merge($emptyLabArray, array_intersect_key($resultRow, $emptyLabArray));

            if (isset($resultRow['approved_by_name']) && $resultRow['approved_by_name'] != '') {

                $lab['result_approved_by'] = $usersService->getOrCreateUser($resultRow['approved_by_name']);
                $lab['result_approved_datetime'] = DateUtility::getCurrentDateTime();
                // we dont need this now
                //unset($resultRow['approved_by_name']);
            }

            //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
            $lab['data_sync'] = 1;
            $lab['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            if ($lab['result_status'] != SAMPLE_STATUS\ACCEPTED && $lab['result_status'] != SAMPLE_STATUS\REJECTED) {
                $keysToRemove = [
                    'result',
                    'is_sample_rejected',
                    'reason_for_sample_rejection'
                ];
                $lab = MiscUtility::removeFromAssociativeArray($lab, $keysToRemove);
            }

            $primaryKey = 'sample_id';
            $tableName = 'form_generic';
            try {
                // Checking if Remote Sample ID is set, if not set we will check if Sample ID is set
                if (!empty($lab['unique_id'])) {
                    $sQuery = "SELECT $primaryKey FROM $tableName WHERE unique_id=?";
                    $sResult = $db->rawQueryOne($sQuery, [$lab['unique_id']]);
                } elseif (!empty($lab['remote_sample_code'])) {
                    $sQuery = "SELECT $primaryKey FROM $tableName WHERE remote_sample_code= ?";
                    $sResult = $db->rawQueryOne($sQuery, [$lab['remote_sample_code']]);
                } elseif (!empty($lab['sample_code']) && !empty($lab['lab_id'])) {
                    $sQuery = "SELECT $primaryKey FROM $tableName WHERE sample_code=? AND lab_id = ?";
                    $sResult = $db->rawQueryOne($sQuery, [$lab['sample_code'], $lab['lab_id']]);
                } elseif (!empty($lab['sample_code']) && !empty($lab['facility_id'])) {
                    $sQuery = "SELECT $primaryKey FROM $tableName WHERE sample_code=? AND facility_id = ?";
                    $sResult = $db->rawQueryOne($sQuery, [$lab['sample_code'], $lab['facility_id']]);
                }

                $formAttributes = $general->jsonToSetString(
                    $lab['form_attributes'],
                    'form_attributes'
                );
                $lab['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;

                if (!empty($sResult)) {
                    $db->where($primaryKey, $sResult[$primaryKey]);
                    $id = $db->update($tableName, $lab);
                } else {
                    //$db->onDuplicate(array_keys($lab), $primaryKey);
                    $id = $db->insert($tableName, $lab);
                }
            } catch (Throwable $e) {
                if ($db->getLastErrno() > 0) {
                    error_log($db->getLastErrno());
                    error_log($db->getLastError());
                    error_log($db->getLastQuery());
                }
                LoggerUtility::log('error', $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage());
                continue;
            }

            if ($id === true && isset($lab['sample_code'])) {
                $sampleCodes[] = $lab['sample_code'];
                $facilityIds[] = $lab['facility_id'];
            }
        }

        foreach ($testResultsData as $genId => $testResults) {
            if (empty($genId) || empty($testResults)) {
                continue;
            }
            $db->where('generic_id', $genId);
            $db->delete("generic_test_results");
            foreach ($testResults as $testId => $test) {
                $db->insert("generic_test_results", [
                    "generic_id" => $sampleId,
                    "test_name" => $test['test_name'],
                    "facility_id" => $test['facility_id'],
                    "sample_tested_datetime" => $test['sample_tested_datetime'],
                    "testing_platform" => $test['testing_platform'],
                    "result" => $test['result'],
                    "updated_datetime" => DateUtility::getCurrentDateTime()
                ]);
            }
        }
    }

    $payload = json_encode($sampleCodes);

    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'results', 'generic-tests', $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labId);
    $general->updateResultSyncDateTime('generic', $facilityIds, $labId);

    $db->commitTransaction();
} catch (Throwable $e) {
    $db->rollbackTransaction();

    $payload = json_encode([]);

    if ($db->getLastErrno() > 0) {
        error_log($db->getLastErrno());
        error_log($db->getLastError());
        error_log($db->getLastQuery());
    }
    throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);
}

echo $apiService->sendJsonResponse($payload);
