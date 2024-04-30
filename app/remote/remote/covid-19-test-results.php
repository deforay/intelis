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

try {
    $db->beginTransaction();
    //this file receives the lab results and updates in the remote db
    //$jsonResponse = $contentEncoding = $request->getHeaderLine('Content-Encoding');

    /** @var ApiService $apiService */
    $apiService = ContainerRegistry::get(ApiService::class);

    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $jsonResponse = $apiService->getJsonFromRequest($request);

    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var UsersService $usersService */
    $usersService = ContainerRegistry::get(UsersService::class);

    $transactionId = $general->generateUUID();

    $sampleCodes = $facilityIds = [];

    if (!empty($jsonResponse) && $jsonResponse != '[]' && MiscUtility::isJSON($jsonResponse)) {

        //remove fields that we DO NOT NEED here
        $unwantedColumns = [
            'covid19_id',
            'sample_package_id',
            'sample_package_code',
            //'last_modified_by',
            'request_created_by'
        ];
        // Create an array with all column names set to null
        $emptyLabArray = $general->getTableFieldsAsArray('form_covid19', $unwantedColumns);

        $resultData = [];
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
            // Overwrite the values in $emptyLabArray with the values in $resultRow
            $lab = MiscUtility::updateFromArray($emptyLabArray, $resultRow);

            if (isset($lab['approved_by_name']) && $lab['approved_by_name'] != '') {

                $lab['result_approved_by'] = $usersService->getOrCreateUser($lab['approved_by_name']);
                $lab['result_approved_datetime'] = DateUtility::getCurrentDateTime();
                // we dont need this now
                //unset($lab['approved_by_name']);
            }

            $lab['data_sync'] = 1; //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
            $lab['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            // unset($lab['request_created_by']);
            // unset($lab['last_modified_by']);
            // unset($lab['request_created_datetime']);

            if ($lab['result_status'] != SAMPLE_STATUS\ACCEPTED && $lab['result_status'] != SAMPLE_STATUS\REJECTED) {
                $keysToRemove = [
                    'result',
                    'is_sample_rejected',
                    'reason_for_sample_rejection'
                ];
                $lab = MiscUtility::removeFromAssociativeArray($lab, $keysToRemove);
            }

            $primaryKey = 'covid19_id';
            $tableName = 'form_covid19';
            try {
                // Checking if Remote Sample ID is set, if not set we will check if Sample ID is set
                $conditions = [];
                $params = [];

                if (!empty($lab['unique_id'])) {
                    $conditions[] = "unique_id = ?";
                    $params[] = $lab['unique_id'];
                }
                if (!empty($lab['remote_sample_code'])) {
                    $conditions[] = "remote_sample_code = ?";
                    $params[] = $lab['remote_sample_code'];
                }
                if (!empty($lab['sample_code'])) {
                    if (!empty($lab['lab_id'])) {
                        $conditions[] = "sample_code = ? AND lab_id = ?";
                        $params[] = $lab['sample_code'];
                        $params[] = $lab['lab_id'];
                    } elseif (!empty($lab['facility_id'])) {
                        $conditions[] = "sample_code = ? AND facility_id = ?";
                        $params[] = $lab['sample_code'];
                        $params[] = $lab['facility_id'];
                    }
                }
                $sResult = [];
                if (!empty($conditions)) {
                    $sQuery = "SELECT $primaryKey FROM $tableName WHERE " . implode(' OR ', $conditions);
                    $sResult = $db->rawQueryOne($sQuery, $params);
                }

                $formAttributes = $general->jsonToSetString(
                    $lab['form_attributes'],
                    'form_attributes'
                );
                $lab['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;

                if (!empty($sResult)) {
                    $db->where($primaryKey, $sResult[$primaryKey]);
                    $id = $db->update($tableName, $lab);
                    $primaryKeyValue = $sResult[$primaryKey];
                } else {
                    $id = $db->insert($tableName, $lab);
                    $primaryKeyValue = $db->getInsertId();
                }

                $db->where('covid19_id', $primaryKeyValue);
                $db->delete("covid19_tests");
            } catch (Throwable $e) {
                if ($db->getLastError()) {
                    error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastErrno());
                    error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
                    error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
                }
                LoggerUtility::log('error', $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage());
                continue;
            }

            if ($id === true && isset($lab['sample_code'])) {
                $sampleCodes[] = $lab['sample_code'];
                $facilityIds[] = $lab['facility_id'];
            }
        }



        $unwantedColumns = [
            'test_id'
        ];
        $emptyTestsArray = $general->getTableFieldsAsArray('covid19_tests', $unwantedColumns);

        foreach ($testResultsData as $covid19Id => $testResults) {
            $db->where('covid19_id', $covid19Id);
            $db->delete("covid19_tests");
            foreach ($testResults as $covid19TestData) {
                $covid19TestData = MiscUtility::updateFromArray($emptyTestsArray, $covid19TestData);
                $db->insert("covid19_tests", $covid19TestData);
            }
        }
    }

    $payload = json_encode($sampleCodes);

    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'results', 'covid19', $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labId);


    $general->updateResultSyncDateTime('covid19', $facilityIds, $labId);
    $db->commitTransaction();
} catch (Throwable $e) {
    $db->rollbackTransaction();

    $payload = json_encode([]);

    if ($db->getLastError()) {
        LoggerUtility::log('error', __FILE__ . ":" . __LINE__ . ":" . $db->getLastErrno() . ":" . $db->getLastError());
        LoggerUtility::log('error', __FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
        LoggerUtility::log('error', __FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
    }
    LoggerUtility::log('error', $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage());
}

echo $apiService->sendJsonResponse($payload);
