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
use App\Abstracts\AbstractTestService;

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
//$origJson = $request->getBody()->getContents();
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
$serviceClass = TestsService::getTestServiceClass($input['testType']);

/** @var AbstractTestService $testTypeService */
$testTypeService = ContainerRegistry::get($serviceClass);


try {
    $sQuery = "SELECT * FROM $tableName as vl
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
        $where[] = " (vl.sample_code IN ('$sampleCode') OR vl.remote_sample_code IN ('$sampleCode') ) ";
    }

    $whereString = '';
    if ($where !== []) {
        $whereString = " WHERE " . implode(" AND ", $where);
    }
    $sQuery .= $whereString;
    $rowData = $db->rawQuery($sQuery);
    $response = [];
    foreach ($rowData as $key => $row) {

        $status = $testTypeService->cancelSample($row['unique_id'], $user['user_id']);

        if ($status) {
            $response[$key]['status'] = 'success';
        } else {
            $response[$key]['status'] = 'fail';
            $response[$key]['message'] = 'Unable to Cancel Sample';
        }
        $response[$key]['sampleCode'] = $row['sample_code'] ?? null;
        $response[$key]['remoteSampleCode'] = $row['remote_sample_code'] ??  null;
    }
    $payload = [
        'status' => 'success',
        'timestamp' => time(),
        'data' => $response
    ];
} catch (Throwable $e) {

    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];
    LoggerUtility::logError($e->getMessage(), [
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'requestUrl' => $requestUrl,
        'stacktrace' => $e->getTraceAsString()
    ]);
}

$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($rowData ?? []), 'cancel-requests', $input['testType'], $requestUrl, $origJson, $payload, 'json', null, null, $authToken);

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
