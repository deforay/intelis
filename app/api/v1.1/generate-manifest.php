<?php

use App\Services\ApiService;
use App\Services\TestsService;
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

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var Slim\Psr7\Request $request */
$request = AppRegistry::get('request');
$origJson = $request->getBody()->getContents();
if (JsonUtility::isJSON($origJson) === false) {
    throw new SystemException("Invalid JSON Payload");
}
$input = $request->getParsedBody();
if (
    empty($input) ||
    empty($input['testType']) ||
    empty($input['labId']) ||
    (empty($input['uniqueId']) && empty($input['sampleCode']))
) {
    http_response_code(400);
    throw new SystemException('Invalid request', 400);
}


$transactionId = MiscUtility::generateUUID();

/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$authToken = $apiService->getAuthorizationBearerToken($request);
$user = $usersService->getUserByToken($authToken);

$tableName = TestsService::getTestTableName($input['testType']);
$primaryKeyName = TestsService::getTestPrimaryKeyColumn($input['testType']);

$packageCode = strtoupper(str_replace("-", "", $input['testType']) . date('ymd') .  MiscUtility::generateRandomString(6));
try {
    $sQuery = "SELECT unique_id, sample_code, remote_sample_code, $primaryKeyName FROM $tableName as vl";

    $where = [];
    /* To check the sample id filter */
    $sampleCode = $input['sampleCode'] ?? [];
    if (!empty($sampleCode)) {
        $sampleCode = implode("','", $sampleCode);
        $where[] = " (vl.sample_code IN ('$sampleCode') OR vl.remote_sample_code IN ('$sampleCode') OR vl.app_sample_code IN ('$sampleCode') ) ";
    }
    $where[] = " ((vl.sample_package_id IS NULL OR vl.sample_package_id = '') AND (vl.sample_package_code IS NULL  OR vl.sample_package_code = ''))";
    $sQuery .= ' WHERE ' . implode(' AND ', $where);
    $rowData = $db->rawQuery($sQuery);
    $response = [];
    $avilableSamples = [];
    $missiedSamples = [];
    if (isset($rowData) && !empty($rowData)) {
        $data = array(
            'package_code'              => $packageCode,
            'module'                    => $input['testType'],
            'added_by'                  => $user['user_id'],
            'lab_id'                    => $input['labId'],
            'number_of_samples'         => count($rowData ?? []),
            'package_status'            => 'pending',
            'request_created_datetime'  => DateUtility::getCurrentDateTime(),
            'last_modified_datetime'    => DateUtility::getCurrentDateTime()
        );
        $db->insert('package_details', $data);
        $lastId = $db->getInsertId();
        foreach ($rowData as $key => $row) {
            $avilableSamples[] = $sampleId = $row['sample_code'] ?? $row['remote_sample_code'];
            $tData = array(
                'sample_package_id' => $lastId,
                'sample_package_code' => $packageCode,
                'lab_id'    => $input['labId'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            );
            $db->where($primaryKeyName, $row[$primaryKeyName]);
            $status = $db->update($tableName, $tData);
            if ($status) {
                $response[$key] = [
                    'sampleCode' => $row['sample_code'],
                    'remoteSampleCode' => $row['remote_sample_code'],
                    'appSampleCode' => $row['app_sample_code'],
                    'uniqueId' => $row['unique_id']
                ];
            }
        }
    }
    $missiedSamples = array_values(array_diff($input['sampleCode'], $avilableSamples));
    // print_r($sQuery);die;
    // LoggerUtility::log('error', __FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
    $payload = [
        'status' => 'success',
        'timestamp' => time(),
        'manifestCode' => $packageCode,
        'data' => ['alreadyInManifest' => $missiedSamples, 'addedToManifest' => $response]
    ];
} catch (Exception | InvalidArgumentException | SystemException $exc) {

    // http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => $exc->getMessage(),
        'data' => []
    ];
    LoggerUtility::log('error', $exc->getMessage());
}

$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($rowData ?? []), 'manifest', $input['testType'], $requestUrl, $origJson, $payload, 'json');
echo $payload;
