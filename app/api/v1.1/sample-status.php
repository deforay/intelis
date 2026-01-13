<?php

use Slim\Psr7\Request;
use App\Services\ApiService;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
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


$origJson = $apiService->getJsonFromRequest($request);
if (JsonUtility::isJSON($origJson) === false) {
    throw new SystemException("Invalid JSON Payload", 400);
}
$input = JsonUtility::decodeJson($origJson, true);
if (
    empty($input) ||
    empty($input['testType']) ||
    (empty($input['uniqueId']) && empty($input['sampleCode']))
) {
    http_response_code(400);
    throw new SystemException('Invalid request', 400);
}


$transactionId = MiscUtility::generateULID();

/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$authToken = ApiService::extractBearerToken($request);
$user = $usersService->findUserByApiToken($authToken);

$tableName = TestsService::getTestTableName($input['testType']);
$primaryKeyName = TestsService::getPrimaryColumn($input['testType']);

try {
    $sQuery = "SELECT ts.status_name as resultStatus, sample_code as sampleCode, remote_sample_code as remoteSampleCode, app_sample_code as appSampleCode, unique_id as uniqueId, '" . $transactionId . "' as transactionId  FROM $tableName as vl
    LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status ";

    $where = [];
    /* To check the uniqueId filter */
    $uniqueId = $input['uniqueId'] ?? [];
    if (!empty($uniqueId)) {
        $uniqueId = implode("','", $uniqueId);
        $where[] = " vl.unique_id IN ('$uniqueId')";
    }
    /* To check the sample id filter */
    $sampleCode = $input['sampleCode'] ?? [];
    if (!empty($sampleCode)) {
        $sampleCode = implode("','", $sampleCode);
        $where[] = " (vl.sample_code IN ('$sampleCode') OR vl.remote_sample_code IN ('$sampleCode')) ";
    }

    /* To skip some status */
    // $where[] = " (vl.result_status NOT IN (4, 7, 8)) ";
    $whereString = '';
    if ($where !== []) {
        $whereString = " WHERE " . implode(" AND ", $where);
    }
    $sQuery .= $whereString;
    // die($sQuery);
    $rowData = $db->rawQuery($sQuery);
    $payload = [
        'status' => 'success',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'data' => $rowData
    ];
} catch (Throwable $exc) {

    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];
    LoggerUtility::logError($exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'requestUrl' => $requestUrl,
        'stacktrace' => $exc->getTraceAsString()
    ]);
}

$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($rowData ?? []), 'sample-status', $input['testType'], $requestUrl, $origJson, $payload, 'json', null, null, $authToken);

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
