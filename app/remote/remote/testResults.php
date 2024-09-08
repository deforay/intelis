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

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

try {
    $db->beginTransaction();

    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $jsonResponse = $apiService->getJsonFromRequest($request);


    //remove unwanted columns
    $unwantedColumns = [
        'vl_sample_id',
        'sample_package_id',
        'sample_package_code',
        'result_printed_datetime',
        'request_created_by'
    ];

    // Create an array with all column names set to null
    $emptyLabArray = $general->getTableFieldsAsArray('form_vl', $unwantedColumns);

    $transactionId = MiscUtility::generateULID();

    $sampleCodes = $facilityIds = [];
    $labId = null;
    if (!empty($jsonResponse) && $jsonResponse != '[]' && JsonUtility::isJSON($jsonResponse)) {

        $resultData = [];
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

            $counter++;
            $resultRow = MiscUtility::arrayEmptyStringsToNull($resultRow);
            // Overwrite the values in $emptyLabArray with the values in $resultRow
            $lab = MiscUtility::updateFromArray($emptyLabArray, $resultRow);

            if (isset($resultRow['approved_by_name']) && !empty($resultRow['approved_by_name'])) {

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
                    'result_value_log',
                    'result_value_absolute',
                    'result_value_text',
                    'result_value_absolute_decimal',
                    'is_sample_rejected',
                    'reason_for_sample_rejection'
                ];
                $lab = MiscUtility::removeFromAssociativeArray($lab, $unwantedColumns);
            }

            $primaryKey = 'vl_sample_id';
            $tableName = 'form_vl';
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
                    $sQuery = "SELECT $primaryKey FROM $tableName WHERE " . implode(' OR ', $conditions);
                    $sResult = $db->rawQueryOne($sQuery, $params);
                    //LoggerUtility::log('info', __FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
                }


                $formAttributes = JsonUtility::jsonToSetString($lab['form_attributes'], 'form_attributes');
                $lab['form_attributes'] = !empty($formAttributes) ? $db->func($formAttributes) : null;

                if (!empty($sResult)) {
                    $db->where($primaryKey, $sResult[$primaryKey]);
                    $id = $db->update($tableName, $lab);
                } else {
                    $id = $db->insert($tableName, $lab);
                }

                if ($id === true && isset($lab['sample_code'])) {
                    $sampleCodes[] = $lab['sample_code'];
                    $facilityIds[] = $lab['facility_id'];
                }
            } catch (Throwable $e) {

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

    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'results', 'vl', $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labId);
    $general->updateResultSyncDateTime('vl', $facilityIds, $labId);

    $db->commitTransaction();
} catch (Throwable $e) {
    $db->rollbackTransaction();

    $payload = json_encode([]);

    if (!empty($db->getLastError())) {
        error_log('Error in testResults.php in remote : ' . $db->getLastErrno());
        error_log('Error in testResults.php in remote : ' . $db->getLastError());
        error_log('Error in testResults.php in remote : ' . $db->getLastQuery());
    }
    throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);
}

echo $apiService->sendJsonResponse($payload);
