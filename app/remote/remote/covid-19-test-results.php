<?php

use JsonMachine\Items;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$jsonResponse = $apiService->getJsonFromRequest($request);



$primaryKey = 'covid19_id';
$tableName = 'form_covid19';

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);


try {

    //this file receives the lab results and updates in the remote db
    //$jsonResponse = $contentEncoding = $request->getHeaderLine('Content-Encoding');

    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $sampleCodes = $facilityIds = [];

    if (!empty($jsonResponse) && $jsonResponse != '[]' && JsonUtility::isJSON($jsonResponse)) {

        //remove fields that we DO NOT NEED here
        $unwantedColumns = [
            $primaryKey,
            'sample_package_id',
            'sample_package_code',
            'request_created_by'
        ];
        // Create an array with all column names set to null
        $emptyLabArray = $general->getTableFieldsAsArray('form_covid19', $unwantedColumns);

        $resultsData = [];
        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        foreach ($parsedData as $name => $data) {
            if ($name === 'labId') {
                $labId = $data;
            } elseif ($name === 'results') {
                $resultsData = $data;
            }
        }

        $counter = 0;
        foreach ($resultsData as $uniqueId => $resultRow) {

            $db->beginTransaction();

            $counter++;

            $formData = $resultRow['form_data'] ?? [];
            if (empty($formData)) {
                continue;
            }

            // Overwrite the values in $emptyLabArray with the values in $formData
            $lab = MiscUtility::updateMatchingKeysOnly($emptyLabArray, $formData);

            if (isset($lab['approved_by_name']) && $lab['approved_by_name'] != '') {

                $lab['result_approved_by'] = $usersService->getOrCreateUser($lab['approved_by_name']);
                $lab['result_approved_datetime'] ??= DateUtility::getCurrentDateTime();
                // we dont need this now
                //unset($lab['approved_by_name']);
            }

            $lab['data_sync'] = 1; //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
            $lab['last_modified_datetime'] = DateUtility::getCurrentDateTime();


            if ($lab['result_status'] != SAMPLE_STATUS\ACCEPTED && $lab['result_status'] != SAMPLE_STATUS\REJECTED) {
                $keysToRemove = [
                    'result',
                    'is_sample_rejected',
                    'reason_for_sample_rejection'
                ];
                $lab = MiscUtility::excludeKeys($lab, $keysToRemove);
            }

            try {
                // Checking if Remote Sample ID is set, if not set we will check if Sample ID is set
                $conditions = [];
                $params = [];

                if (!empty($lab['unique_id'])) {
                    $conditions[] = "unique_id = ?";
                    $params[] = $lab['unique_id'];
                } elseif (!empty($lab['remote_sample_code'])) {
                    $conditions[] = "remote_sample_code = ?";
                    $params[] = $lab['remote_sample_code'];
                } elseif (!empty($lab['sample_code'])) {
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
                    $sQuery = "SELECT $primaryKey FROM $tableName WHERE " . implode(' OR ', $conditions) . " FOR UPDATE";
                    $sResult = $db->rawQueryOne($sQuery, $params);
                }

                $formAttributes = JsonUtility::jsonToSetString(
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

                // Insert covid19_tests
                $testsData = $resultRow[$uniqueId]['data_from_tests'] ?? [];

                $db->where($primaryKey, $primaryKeyValue);
                $db->delete("covid19_tests");
                foreach ($testsData as $tRow) {
                    $covid19TestData = [
                        "covid19_id"                => $primaryKeyValue,
                        "facility_id"               => $tRow['facility_id'],
                        "test_name"                 => $tRow['test_name'],
                        "tested_by"                 => $tRow['tested_by'],
                        "sample_tested_datetime"    => $tRow['sample_tested_datetime'],
                        "testing_platform"          => $tRow['testing_platform'],
                        "instrument_id"             => $tRow['instrument_id'],
                        "kit_lot_no"                => $tRow['kit_lot_no'],
                        "kit_expiry_date"           => $tRow['kit_expiry_date'],
                        "result"                    => $tRow['result'],
                        "updated_datetime"          => $tRow['updated_datetime']
                    ];
                    $db->insert("covid19_tests", $covid19TestData);
                }


                if ($id === true && isset($lab['sample_code'])) {
                    $sampleCodes[] = $lab['sample_code'];
                    $facilityIds[] = $lab['facility_id'];
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
    }

    $payload = JsonUtility::encodeUtf8Json($sampleCodes);

    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'results', 'covid19', $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labId);
    $general->updateResultSyncDateTime('covid19', $facilityIds, $labId);
} catch (Throwable $e) {


    $payload = json_encode([]);

    if ($db->getLastError()) {
        LoggerUtility::log('error', __FILE__ . ":" . __LINE__ . ":" . $db->getLastErrno() . ":" . $db->getLastError());
        LoggerUtility::log('error', __FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
        LoggerUtility::log('error', __FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
    }
    LoggerUtility::log('error', $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage());
}

echo ApiService::generateJsonResponse($payload, $request);
