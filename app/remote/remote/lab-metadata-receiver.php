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

    $counter = 0;


    //$storageId = [];
    $labId = null;
    if (!empty($jsonResponse) && $jsonResponse != '[]' && MiscUtility::isJSON($jsonResponse)) {

        $labStorageData = [];
        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        foreach ($parsedData as $name => $data) {
            if ($name === 'transactionId') {
                $transactionId = $data;
            } elseif ($name === 'labId') {
                $labId = $data;
            } elseif ($name === 'labStorage') {
                $labStorageData = $data;
            } elseif ($name === 'instruments') {
                $instrumentsData = $data;
            } elseif ($name === 'patients') {
                $patientsData = $data;
            }
        }

        $transactionId = $transactionId ?? $general->generateUUID();

        if (!empty($labStorageData)) {

            $emptyLabStorageArray = $general->getTableFieldsAsArray('lab_storage');

            foreach ($labStorageData as $key => $resultRow) {
                $counter++;
                // Overwrite the values in $emptyLabArray with the values in $resultRow
                $labStorageData = array_merge($emptyLabStorageArray, array_intersect_key($resultRow, $emptyLabStorageArray));

                $primaryKey = $checkColumn = 'storage_id';
                $tableName = 'lab_storage';
                try {
                    if (!empty($labStorageData[$checkColumn])) {
                        $sQuery = "SELECT $primaryKey FROM $tableName WHERE $checkColumn =?";
                        $sResult = $db->rawQueryOne($sQuery, [$labStorageData[$checkColumn]]);
                    }
                    if (!empty($sResult)) {
                        $db->where($primaryKey, $sResult[$primaryKey]);
                        $id = $db->update($tableName, $labStorageData);
                    } else {
                        $id = $db->insert($tableName, $labStorageData);
                    }
                } catch (Throwable $e) {

                    if (!empty($db->getLastError())) {
                        error_log($db->getLastErrno());
                        error_log($db->getLastError());
                        error_log($db->getLastQuery());
                    }
                    LoggerUtility::log('error', $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage());
                    continue;
                }

                // if ($id === true && isset($labStorageData['storage_code'])) {
                //     $storageId[] = $labStorageData['storage_code'];
                // }
            }
        }
    }

    $payload = json_encode([
        'status' => 'success',
        'message' => 'Metadata synced successfully'
    ]);

    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'system-metadata-sync', 'common', $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labId);
    $db->commitTransaction();
} catch (Throwable $e) {
    $db->rollbackTransaction();

    $payload = json_encode([]);

    if (!empty($db->getLastError())) {
        error_log('Error in system-metadata-sync.php in remote : ' . $db->getLastErrno());
        error_log('Error in system-metadata-sync.php in remote : ' . $db->getLastError());
        error_log('Error in system-metadata-sync.php in remote : ' . $db->getLastQuery());
    }
    throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), $e->getCode(), $e);
}

echo $apiService->sendJsonResponse($payload);
