<?php

use Slim\Psr7\Request;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var Request $request */
$request = AppRegistry::get('request');

$transactionId = MiscUtility::generateULID();
$requestUrl = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$authToken = ApiService::extractBearerToken($request);
$user = $usersService->findUserByApiToken($authToken);

try {
    $labId = !empty($_GET['labId']) ? $_GET['labId'] : null;
    $detailed = !empty($_GET['detailed']);

    $params = [];
    $labFilter = '';
    if ($labId !== null) {
        $labFilter = ' AND ins.lab_id = ?';
        $params[] = $labId;
    }

    $nameSql = "SELECT TRIM(im.config_machine_name) AS name" . ($detailed ? ",
                    ins.instrument_id,
                    im.date_format" : "") . "
                FROM instrument_machines im
                INNER JOIN instruments ins ON ins.instrument_id = im.instrument_id
                WHERE ins.status = 'active'
                  AND im.config_machine_name IS NOT NULL
                  AND TRIM(im.config_machine_name) != ''
                  $labFilter

                UNION

                SELECT TRIM(ins.machine_name) AS name" . ($detailed ? ",
                    ins.instrument_id,
                    NULL AS date_format" : "") . "
                FROM instruments ins
                WHERE ins.status = 'active'
                  AND ins.machine_name IS NOT NULL
                  AND TRIM(ins.machine_name) != ''
                  AND NOT EXISTS (
                      SELECT 1 FROM instrument_machines im2
                      WHERE im2.instrument_id = ins.instrument_id
                        AND im2.config_machine_name IS NOT NULL
                        AND TRIM(im2.config_machine_name) != ''
                  )
                  $labFilter";

    if ($detailed) {
        $sql = "$nameSql ORDER BY name ASC";
        $allParams = array_merge($params, $params);
    } else {
        $sql = "SELECT DISTINCT name FROM ($nameSql) AS combined ORDER BY name ASC";
        $allParams = array_merge($params, $params);
    }

    $rows = $db->rawQuery($sql, !empty($allParams) ? $allParams : null);

    $instruments = [];
    foreach ($rows as $row) {
        if ($detailed) {
            $instruments[] = [
                'instrumentId' => $row['instrument_id'],
                'name' => $row['name'],
                'dateFormat' => $row['date_format'],
            ];
        } else {
            $instruments[] = ['name' => $row['name']];
        }
    }

    $payload = [
        'status' => 'success',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'instruments' => $instruments,
    ];
} catch (Throwable $exc) {
    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'instruments' => [],
    ];
    LoggerUtility::logError($exc->getMessage(), [
        'transactionId' => $transactionId,
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'requestUrl' => $requestUrl,
        'stacktrace' => $exc->getTraceAsString(),
    ]);
}

$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($instruments ?? []), 'instruments', null, $requestUrl, null, $payload, 'json', null, null, $authToken);

echo ApiService::generateJsonResponse($payload, $request);
