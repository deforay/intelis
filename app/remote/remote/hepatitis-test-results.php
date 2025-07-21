<?php

use JsonMachine\Items;
use App\Services\ApiService;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Utilities\QueryLoggerUtility;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

require_once dirname(__FILE__) . "/../../../bootstrap.php";

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

try {

    $testType = 'hepatitis';

    $primaryKey = TestsService::getTestPrimaryKeyColumn($testType);
    $tableName = TestsService::getTestTableName($testType);

    //this file receives the lab results and updates in the remote db

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

    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $sampleCodes = $facilityIds = [];
    $labId = null;
    if (!empty($jsonResponse) && $jsonResponse != '[]' && JsonUtility::isJSON($jsonResponse)) {

        //remove fields that we DO NOT NEED here
        $unwantedColumns = [
            $primaryKey,
            'sample_package_id',
            'sample_package_code',
            'request_created_by'
        ];
        // Create an array with all column names set to null
        $emptyLabArray = $general->getTableFieldsAsArray($tableName, $unwantedColumns);

        $counter = 0;

        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        foreach ($parsedData as $name => $data) {
            if ($name === 'labId') {
                $labId = $data;
            } elseif ($name === 'result') {
                $resultData = $data;
            }
        }

        $counter = 0;
        foreach ($resultData as $key => $resultRow) {

            $db->beginTransaction();

            $counter++;
            // Overwrite the values in $emptyLabArray with the values in $resultRow
            $lab = MiscUtility::updateMatchingKeysOnly($emptyLabArray, $resultRow);

            if (isset($resultRow['approved_by_name']) && $resultRow['approved_by_name'] != '') {

                $lab['result_approved_by'] = $usersService->getOrCreateUser($resultRow['approved_by_name']);
                $lab['result_approved_datetime'] ??= DateUtility::getCurrentDateTime();
                // we dont need this now
                //unset($resultRow['approved_by_name']);
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

                $condition = '';
                $params = [];

                if (!empty($lab['unique_id'])) {
                    $condition = "unique_id = ?";
                    $params[] = $lab['unique_id'];
                } elseif (!empty($lab['remote_sample_code'])) {
                    $condition = "remote_sample_code = ?";
                    $params[] = $lab['remote_sample_code'];
                } elseif (!empty($lab['sample_code'])) {
                    if (!empty($lab['lab_id'])) {
                        $condition = "sample_code = ? AND lab_id = ?";
                        $params[] = $lab['sample_code'];
                        $params[] = $lab['lab_id'];
                    } elseif (!empty($lab['facility_id'])) {
                        $condition = "sample_code = ? AND facility_id = ?";
                        $params[] = $lab['sample_code'];
                        $params[] = $lab['facility_id'];
                    }
                }

                $sResult = [];
                if (!empty($condition)) {
                    $sQuery = "SELECT unique_id FROM $tableName WHERE $condition FOR UPDATE";
                    $sResult = $db->rawQueryOne($sQuery, $params);
                }

                $formAttributes = JsonUtility::jsonToSetString(
                    $lab['form_attributes'],
                    'form_attributes'
                );
                $lab['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;

                if (!empty($sResult)) {
                    $db->reset();
                    $db->where('unique_id', $sResult['unique_id']);
                    $id = $db->update($tableName, $lab);
                } else {
                    $id = $db->insert($tableName, $lab);
                }

                if ($id === true && isset($lab['sample_code'])) {
                    $sampleCodes[] = $lab['sample_code'];
                    $facilityIds[] = $lab['facility_id'];
                }
                $db->commitTransaction();
            } catch (Throwable $e) {
                $db->rollbackTransaction();
                LoggerUtility::logError(JsonUtility::encodeUtf8Json($resultRow));
                LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
                LoggerUtility::logError($e->getFile() . ":" . $e->getLine()  . ":" . $db->getLastQuery());
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
    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'results', $testType, $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labId);
    $general->updateResultSyncDateTime($testType, $facilityIds, $labId);
} catch (Throwable $e) {


    $payload = json_encode([]);

    QueryLoggerUtility::log($e->getFile() . ":" . $e->getLine() . ":" . $db->getLastErrno());
    QueryLoggerUtility::log($e->getFile() . ":" . $e->getLine()  . ":" . $db->getLastError());
    QueryLoggerUtility::log($e->getFile() . ":" . $e->getLine()  . ":" . $db->getLastQuery());

    throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);
}

echo ApiService::generateJsonResponse($payload, $request);
