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

    $params = [];
    $labFilter = '';
    if ($labId !== null) {
        $labFilter = ' AND ins.lab_id = ?';
        $params[] = $labId;
    }

    $sql = "SELECT
                ins.instrument_id,
                COALESCE(NULLIF(TRIM(im.config_machine_name), ''), ins.machine_name) AS name,
                im.date_format
            FROM instruments ins
            LEFT JOIN instrument_machines im ON im.instrument_id = ins.instrument_id
            WHERE ins.status = 'active'
              AND COALESCE(NULLIF(TRIM(im.config_machine_name), ''), ins.machine_name) IS NOT NULL
              $labFilter
            GROUP BY ins.instrument_id, name, im.date_format
            ORDER BY name ASC";

    $rows = $db->rawQuery($sql, !empty($params) ? $params : null);

    $instruments = [];
    foreach ($rows as $row) {
        $instruments[] = [
            'instrumentId' => $row['instrument_id'],
            'name' => $row['name'],
            'dateFormat' => $row['date_format'],
        ];
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
