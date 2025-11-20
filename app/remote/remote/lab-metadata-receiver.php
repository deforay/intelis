<?php

use Psr\Http\Message\ServerRequestInterface;
use JsonMachine\Items;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

require_once __DIR__ . "/../../../bootstrap.php";

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

function saveUserSignature(array &$data): void
{
    if (empty($data['signature_image_content']) || empty($data['signature_image_filename'])) {
        return;
    }

    $signatureDir = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
    MiscUtility::makeDirectory($signatureDir);

    $filePath = $signatureDir . DIRECTORY_SEPARATOR . $data['signature_image_filename'];
    file_put_contents($filePath, base64_decode((string) $data['signature_image_content']));

    unset($data['signature_image_content'], $data['signature_image_filename']);
}

try {
    $db->beginTransaction();

    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $jsonResponse = $apiService->getJsonFromRequest($request);

    $apiRequestId  = $apiService->getHeader($request, 'X-Request-ID');
    $transactionId = $apiRequestId ?? MiscUtility::generateULID();

    $counter = 0;

    $labId = null;

    $tableMap = [
        'labStorage' => [
            'primaryKey' => 'storage_id',
            'table' => 'lab_storage'
        ],
        'labStorageHistory' => [
            'primaryKey' => 'history_id',
            'table' => 'lab_storage_history'
        ],
        'instruments' => [
            'primaryKey' => 'instrument_id',
            'table' => 'instruments'
        ],
        'instrumentMachines' => [
            'primaryKey' => 'config_machine_id',
            'table' => 'instrument_machines'
        ],
        'instrumentControls' => [
            'primaryKey' => 'instrument_id',
            'table' => 'instrument_controls'
        ],
        'users' => [
            'primaryKey' => 'user_id',
            'table' => 'user_details'
        ],
    ];

    if (!empty($jsonResponse) && $jsonResponse != '[]' && JsonUtility::isJSON($jsonResponse)) {

        $data = [];
        $options = [
            'decoder' => new ExtJsonDecoder(true, 512, JSON_THROW_ON_ERROR)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        $tableInfo = [];
        $i = 1;
        foreach ($parsedData as $name => $data) {
            if ($name === 'labId') {
                $labId = $data;
                continue;
            }
            if (isset($tableMap[$name])) {
                $tableInfo['primaryKey'][$i] = $tableMap[$name]['primaryKey'];
                $tableInfo['table'][$i] = $tableMap[$name]['table'];
                $tableInfo['data'][$i] = $data;
                $i++;
            }
        }


        if ($tableInfo !== []) {
            foreach (array_keys($tableInfo['table']) as $j) {
                $primaryKey = $checkColumn = $tableInfo['primaryKey'][$j];
                $tableName = $tableInfo['table'][$j];

                $emptyTableArray = $general->getTableFieldsAsArray($tableName);
                if (empty($emptyTableArray)) {
                    continue;
                }
                $dataResultSet = $tableInfo['data'][$j];
                $deletedId = [];
                foreach ($dataResultSet as $key => $resultRow) {
                    $counter++;
                    $data = MiscUtility::updateMatchingKeysOnly($emptyTableArray, $resultRow);
                    $data['updated_datetime'] = DateUtility::getCurrentDateTime();

                    try {
                        if ($tableName === 'instrument_controls' || $tableName === 'instrument_machines') {
                            if ((in_array($data['instrument_id'], $deletedId)) === false &&
                                !empty($data['instrument_id'])
                            ) {
                                $deletedId[] = $data['instrument_id'];
                                $db->where('instrument_id', $data['instrument_id']);
                                $db->delete($tableName);
                            }
                            $id = $db->setQueryOption(['IGNORE'])->insert($tableName, $data);
                        } else {
                            if ($tableName === 'user_details') {
                                // Unset unwanted columns
                                foreach (['login_id', 'role_id', 'password', 'status'] as $unsetKey) {
                                    unset($data[$unsetKey]);
                                }

                                // update signature image if received
                                saveUserSignature($data);

                                // Invalidate file cache for users count
                                _invalidateFileCacheByTags(['users_count']);
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
                                $id = $db->upsert($tableName, $data);
                            }
                        }
                    } catch (Throwable $e) {
                        LoggerUtility::logError("Error when processing for $tableName : " . $e->getMessage(), [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'last_db_errno' => $db->getLastErrno(),
                            'last_db_query' => $db->getLastQuery(),
                            'last_db_error' => $db->getLastError(),
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

    $general->addApiTracking($transactionId, 'intelis-system', $counter, 'system-metadata-sync', 'common', $_SERVER['REQUEST_URI'], $jsonResponse, $payload, 'json', $labId);
    $db->commitTransaction();
} catch (Throwable $e) {
    $db->rollbackTransaction();

    $payload = json_encode([]);

    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_errno' => $db->getLastErrno(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'trace' => $e->getTraceAsString(),
    ]);
}
header('Content-Type: application/json');
echo ApiService::generateJsonResponse($payload, $request);
