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

        $data = [];
        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        $tableInfo = [];$i = 1;
        foreach ($parsedData as $name => $data) {
            if ($name === 'transactionId') {
                $transactionId = $data;
            } elseif ($name === 'labId') {
                $labId = $data;
            } elseif ($name === 'labStorage') {

                $tableInfo['primaryKey'][$i] = 'storage_id';
                $tableInfo['table'][$i] = 'lab_storage';
            } elseif ($name === 'instruments') {

                $tableInfo['primaryKey'][$i] = 'instrument_id';
                $tableInfo['table'][$i] = 'instruments';
                // $tableInfo[$i]['instrumentsData'] = $data;
            } elseif ($name === 'instrumentMachines') {

                $tableInfo['primaryKey'][$i] = 'config_machine_id';
                $tableInfo['table'][$i] = 'instrument_machines';
                // $tableInfo[$i]['instrumentMachinesData'] = $data;
            } elseif ($name === 'instrumentControls') {

                $tableInfo['primaryKey'][$i] = 'instrument_id';
                $tableInfo['table'][$i] = 'instrument_controls';
                // $tableInfo[$i]['instrumentControlsData'] = $data;
            } elseif ($name === 'patients') {

                $tableInfo['primaryKey'][$i] = 'system_patient_code';
                $tableInfo['table'][$i] = 'patients';
                // $tableInfo[$i]['patientsData'] = $data;
            }
            $tableInfo['data'][$i] = $data;
            $i++;
        }
        
        $transactionId = $transactionId ?? $general->generateUUID();
        if (!empty($tableInfo)) {
            foreach ($tableInfo['table'] as $j => $table) {
                $emptyDataArray = $general->getTableFieldsAsArray($table);
                foreach ($tableInfo['data'][$j] as $key => $resultRow) {
                    $deletedId = [];
                    $counter++;
                    // Overwrite the values in $emptyLabArray with the values in $resultRow
                    $data = array_merge($emptyDataArray, array_intersect_key($resultRow, $emptyDataArray));
                    $data['updated_datetime'] = DateUtility::getCurrentDateTime();
                    $primaryKey = $checkColumn = $tableInfo['primaryKey'][$j];
                    $tableName = $tableInfo['table'][$j];
                    try {
                        if (!empty($data[$checkColumn])) {
                            $sQuery = "SELECT $primaryKey FROM $tableName WHERE $checkColumn =?";
                            $sResult = $db->rawQueryOne($sQuery, [$data[$checkColumn]]);
                        }
                        if (!empty($sResult) && $r != 'instrument_controls') {
                            $db->where($primaryKey, $sResult[$primaryKey]);
                            $id = $db->update($tableName, $data);
                        } else {
                            if($r == 'instrument_controls' && !in_array($data['instrument_id'], $deletedId)){
                                $deletedId[] = $data['instrument_id'];
                                $db->delete($r, "instrument_id = " . $data['instrument_id']);
                            }
                            $id = $db->insert($tableName, $data);
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
                    
                }
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
