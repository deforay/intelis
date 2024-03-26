<?php

use JsonMachine\Items;
use App\Services\ApiService;
use App\Utilities\DateUtility;
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

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

try {
    $db->beginTransaction();

    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $jsonResponse = $apiService->getJsonFromRequest($request);


    $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = ? AND table_name= ?";
    $allColResult = $db->rawQuery($allColumns, [SYSTEM_CONFIG['database']['db'], 'lab_storage']);
    $columnNames = array_column($allColResult, 'COLUMN_NAME');

    // Create an array with all column names set to null
    $emptyLabArray = array_fill_keys($columnNames, null);

    //remove unwanted columns
    $unwantedColumns = [
        'storage_id',
        'updated_datetime'
    ];

    $emptyLabArray = MiscUtility::removeFromAssociativeArray($emptyLabArray, $unwantedColumns);

    $transactionId = $general->generateUUID();
    $storageId = [];
    $labStorageDataId = null;
    if (!empty($jsonResponse) && $jsonResponse != '[]' && MiscUtility::isJSON($jsonResponse)) {

        $resultData = [];
        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        foreach ($parsedData as $name => $data) {
            if ($name === 'labId') {
                $labStorageDataId = $data;
            } elseif ($name === 'result') {
                $resultData = $data;
            }
        }
        $counter = 0;
        foreach ($resultData as $key => $resultRow) {

            $counter++;
            // Overwrite the values in $emptyLabArray with the values in $resultRow
            $labStorageData = array_merge($emptyLabArray, array_intersect_key($resultRow, $emptyLabArray));
            $labStorageData['updated_datetime'] = DateUtility::getCurrentDateTime();

            $primaryKey = 'storage_id';
            $tableName = 'lab_storage';
            try {
                // Checking if Remote Sample ID is set, if not set we will check if Sample ID is set
                if (!empty($labStorageData['storage_code'])) {
                    $sQuery = "SELECT $primaryKey FROM $tableName WHERE storage_code=?";
                    $sResult = $db->rawQueryOne($sQuery, [$labStorageData['storage_code']]);
                } 
                if (!empty($sResult)) {
                    $db->where($primaryKey, $sResult[$primaryKey]);
                    $id = $db->update($tableName, $labStorageData);
                } else {
                    //$db->onDuplicate(array_keys($labStorageData), $primaryKey);
                    $id = $db->insert($tableName, $labStorageData);
                }
            } catch (Throwable $e) {

                //if ($db->getLastErrno() > 0) {
                error_log($db->getLastErrno());
                error_log($db->getLastError());
                error_log($db->getLastQuery());
                //}
                LoggerUtility::log('error', $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage());
                continue;
            }

            if ($id === true && isset($labStorageData['storage_code'])) {
                $storageId[] = $labStorageData['storage_code'];
            }
        }
    }

    $payload = json_encode($storageId);
    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'lab-storage', 'common', $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labStorageDataId);
    $db->commitTransaction();
} catch (Throwable $e) {
    $db->rollbackTransaction();

    $payload = json_encode([]);

    //if ($db->getLastErrno() > 0) {
    error_log('Error in system-reference-sync.php in remote : ' . $db->getLastErrno());
    error_log('Error in system-reference-sync.php in remote : ' . $db->getLastError());
    error_log('Error in system-reference-sync.php in remote : ' . $db->getLastQuery());
    //}
    throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);
}

echo $apiService->sendJsonResponse($payload);
