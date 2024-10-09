<?php

use JsonMachine\Items;
use App\Services\ApiService;
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

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

try {
    $db->beginTransaction();

    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $jsonResponse = $apiService->getJsonFromRequest($request);

    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $counter = 0;

    $labId = null;
    if (!empty($jsonResponse) && $jsonResponse != '[]' && JsonUtility::isJSON($jsonResponse)) {

        $data = [];
        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        $tableInfo = [];
        $i = 1;
        foreach ($parsedData as $name => $data) {
            if ($name === 'labId') {
                $labId = $data;
            } elseif ($name === 'labStorage') {
                $tableInfo['primaryKey'][$i] = 'storage_id';
                $tableInfo['table'][$i] = 'lab_storage';
            } elseif ($name === 'labStorageHistory') {
                $tableInfo['primaryKey'][$i] = 'history_id';
                $tableInfo['table'][$i] = 'lab_storage_history';
            } elseif ($name === 'instruments') {
                $tableInfo['primaryKey'][$i] = 'instrument_id';
                $tableInfo['table'][$i] = 'instruments';
            } elseif ($name === 'instrumentMachines') {
                $tableInfo['primaryKey'][$i] = 'config_machine_id';
                $tableInfo['table'][$i] = 'instrument_machines';
            } elseif ($name === 'instrumentControls') {
                $tableInfo['primaryKey'][$i] = 'instrument_id';
                $tableInfo['table'][$i] = 'instrument_controls';
            } elseif ($name === 'patients') {
                $tableInfo['primaryKey'][$i] = 'system_patient_code';
                $tableInfo['table'][$i] = 'patients';
            } elseif ($name === 'users') {
                $tableInfo['primaryKey'][$i] = 'user_id';
                $tableInfo['table'][$i] = 'user_details';
            }
            $tableInfo['data'][$i] = $data;
            $i++;
        }

        $transactionId ??= MiscUtility::generateUUID();
        if (!empty($tableInfo)) {
            foreach ($tableInfo['table'] as $j => $table) {
                $emptyTableArray = $general->getTableFieldsAsArray($table);
                if (empty($emptyTableArray)) {
                    continue;
                }
                $deletedId = [];
                foreach ($tableInfo['data'][$j] as $key => $resultRow) {
                    $counter++;
                    $data = MiscUtility::updateFromArray($emptyTableArray, $resultRow);
                    $data['updated_datetime'] = DateUtility::getCurrentDateTime();
                    $primaryKey = $checkColumn = $tableInfo['primaryKey'][$j];
                    $tableName = $tableInfo['table'][$j];
                    try {
                        if ($tableName == 'instrument_controls' || $tableName == 'instrument_machines') {
                            if ((in_array($data['instrument_id'], $deletedId)) === false &&
                                !empty($data['instrument_id'])
                            ) {
                                $deletedId[] = $data['instrument_id'];
                                $db->where('instrument_id', $data['instrument_id']);
                                $db->delete($tableName);
                            }
                            $id = $db->setQueryOption(['IGNORE'])->insert($tableName, $data);
                        } else {
                            if ($tableName == 'user_details' && !empty($data['signature_image_content']) && !empty($data['signature_image_filename'])) {
                                $signatureImagePathBase = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature";
                                MiscUtility::makeDirectory($signatureImagePathBase);
                                $signatureImagePathBase = realpath($signatureImagePathBase);

                                $signatureImage = base64_decode($data['signature_image_content']);
                                $signatureImagePath = $signatureImagePathBase . DIRECTORY_SEPARATOR . $data['signature_image_filename'];
                                file_put_contents($signatureImagePath, $signatureImage);
                                unset($data['signature_image_content']);
                                unset($data['signature_image_filename']);
                            }

                            $sResult = null;
                            if (!empty($data[$checkColumn])) {
                                $db->reset();
                                $db->where($checkColumn, $data[$checkColumn]);
                                $sResult = $db->getOne($tableName, [$primaryKey]);
                            }
                            if (!empty($sResult)) {
                                $db->where($primaryKey, $sResult[$primaryKey]);
                                $id = $db->update($tableName, $data);
                            } else {
                                $id = $db->insert($tableName, $data);
                            }
                        }
                    } catch (Throwable $e) {
                        LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $db->getLastErrno());
                        LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
                        LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $db->getLastQuery());
                        LoggerUtility::logError("Error when processing for $tableName : " . $e->getMessage(), [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                        ]);
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
        LoggerUtility::logError('Error in lab-metadata-receiver.php : ' . $db->getLastErrno());
        LoggerUtility::logError('Error in lab-metadata-receiver.php : ' . $db->getLastError());
        LoggerUtility::logError('Error in lab-metadata-receiver.php : ' . $db->getLastQuery());
    }

    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}

echo ApiService::sendJsonResponse($payload, $request);
