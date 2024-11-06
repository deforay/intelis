<?php

use App\Services\ApiService;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Services\FacilitiesService;
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
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var Slim\Psr7\Request $request */
$request = AppRegistry::get('request');
//$origJson = $request->getBody()->getContents();
$origJson = $apiService->getJsonFromRequest($request);
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


$transactionId = MiscUtility::generateULID();

/* For API Tracking params */
$requestUrl = $_SERVER['HTTP_HOST'];
$requestUrl .= $_SERVER['REQUEST_URI'];
$authToken = ApiService::getAuthorizationBearerToken($request);
$user = $usersService->getUserByToken($authToken);

$tableName = TestsService::getTestTableName($input['testType']);
$primaryKeyName = TestsService::getTestPrimaryKeyColumn($input['testType']);

$sampleManifestCode = strtoupper(str_replace("-", "", $input['testType']) . date('ymdH') .  MiscUtility::generateRandomString(4));

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

    $facilityMap = $facilitiesService->getUserFacilityMap($user['user_id']);
    $response = [];

    if (!empty($facilityMap)) {
        $arrFacility =  explode(",",$facilityMap);
        if(in_array($input['labId'],$arrFacility) == false){
            $response = [
                'status' => 'Failed',
                'timestamp' => time(),
                'message' => 'Samples belongs to this lab is not mapped to this user'
            ];
        }
        $where[] = " vl.facility_id IN (" . $facilityMap . ")";
    }

    $sQuery .= ' WHERE ' . implode(' AND ', $where);
    $rowData = $db->rawQuery($sQuery);
    $avilableSamples = [];
    $missiedSamples = [];
    if (isset($rowData) && !empty($rowData)) {
        $data = [
            'package_code' => $sampleManifestCode,
            'module' => $input['testType'],
            'added_by' => $user['user_id'],
            'lab_id' => $input['labId'],
            'number_of_samples' => count($rowData ?? []),
            'package_status' => 'pending',
            'request_created_datetime' => DateUtility::getCurrentDateTime(),
            'last_modified_datetime' => DateUtility::getCurrentDateTime()
        ];
        $db->insert('package_details', $data);
        $lastId = $db->getInsertId();
        foreach ($rowData as $key => $row) {
            $avilableSamples[] = $sampleId = $row['sample_code'] ?? $row['remote_sample_code'];
            $tData = [
                'sample_package_id' => $lastId,
                'sample_package_code' => $sampleManifestCode,
                'lab_id' => $input['labId'],
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];
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
    
        $missiedSamples = array_values(array_diff($input['sampleCode'], $avilableSamples));

        $payload = [
            'status' => 'success',
            'timestamp' => time(),
            'manifestCode' => $sampleManifestCode,
            'data' => ['alreadyInManifest' => $missiedSamples, 'addedToManifest' => $response]
        ];
    }
    else{
        $payload = [
            'status' => 'Failed',
            'timestamp' => time(),
            'message' => 'Samples may not belong with this lab',
            'data' => $response
        ];
    }
} catch (Throwable $exc) {

    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];
    LoggerUtility::logError($exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'requestUrl' => $requestUrl,
        'stacktrace' => $exc->getTraceAsString()
    ]);
} finally {

    $payload = JsonUtility::encodeUtf8Json($payload);
    $general->addApiTracking($transactionId, $user['user_id'], count($rowData ?? []), 'manifest', $input['testType'], $requestUrl, $origJson, $payload, 'json');

    //echo $payload
    echo ApiService::sendJsonResponse($payload, $request);
}
