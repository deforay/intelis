<?php


use Slim\Psr7\Request;
use JsonMachine\Items;
use App\Services\VlService;
use App\Services\ApiService;
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
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Exception\PathNotFoundException;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

try {

    $db->beginTransaction();

    /** @var Request $request */
    $request = AppRegistry::get('request');
    $noOfFailedRecords = 0;


    $origJson = $apiService->getJsonFromRequest($request);
    if (JsonUtility::isJSON($origJson) === false) {
        throw new SystemException("Invalid JSON Payload", 400);
    }
    // Attempt to extract appVersion
    try {
        $appVersion = Items::fromString($origJson, [
            'pointer' => '/appVersion',
            'decoder' => new ExtJsonDecoder(true)
        ]);

        $appVersion = _getIteratorKey($appVersion, 'appVersion');
    } catch (PathNotFoundException | Throwable $e) {
        // If the pointer is not found, appVersion remains null
        $appVersion = null;
    }
    try {
        $input = Items::fromString($origJson, [
            'pointer' => '/data',
            'decoder' => new ExtJsonDecoder(true)
        ]);
        if (empty($input)) {
            throw new PathNotFoundException();
        }
    } catch (PathNotFoundException $e) {
        throw new SystemException("Invalid request", 400, $e);
    }


    $transactionId = MiscUtility::generateULID();


    $authToken = ApiService::extractBearerToken($request);
    $user = $usersService->findUserByApiToken($authToken);

    $instanceId = $general->getInstanceId();

    /* To save the user attributes from API */
    $userAttributes = [];
    foreach (['deviceId', 'osVersion', 'ipAddress'] as $header) {
        $userAttributes[$header] = $apiService->getHeader($request, $header);
    }
    $userAttributes = JsonUtility::jsonToSetString(json_encode($userAttributes), 'user_attributes');
    $usersService->saveUserAttributes($userAttributes, $user['user_id']);

    $responseData = [];
    $dataCounter = 0;
    foreach ($input as $rootKey => $data) {
        $dataCounter++;

        $mandatoryFields = [
            'facility_name',
            'facility_code',
            'facility_type'
        ];

        $data = MiscUtility::arrayEmptyStringsToNull($data);

        if (MiscUtility::hasEmpty(array_intersect_key($data, array_flip($mandatoryFields)))) {
            $noOfFailedRecords++;
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'status' => 'failed',
                'action' => 'skipped',
                'message' => _translate("Missing required fields")
            ];
            continue;
        }

        $stateCheckQuery = "SELECT geo_id,geo_name FROM geographical_divisions WHERE geo_name= ?";
        $stateInfo = $db->rawQueryOne($stateCheckQuery, [$data['state']]);
        if (isset($stateInfo['geo_name']) && !empty($stateInfo['geo_name'])) {
            $data['facilityStateId'] = $stateInfo['geo_id'];
        } else {
            $stateData = [
                'geo_name' => $data['state'],
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            ];
            $db->insert("geographical_divisions", $stateData);
            $lastStateId = $db->getInsertId();
            $data['facilityStateId'] = $lastStateId;
        }

        $districtCheckQuery = "SELECT geo_id,geo_name FROM geographical_divisions WHERE geo_name = ? AND geo_parent = ?";
        $districtInfo = $db->rawQueryOne($districtCheckQuery, [$data['state'], $data['facilityStateId']]);
        if (isset($districtInfo['geo_name']) && !empty($districtInfo['geo_name'])) {
            $data['facilityDistrictId'] = $districtInfo['geo_id'];
        } else {
            $districtData = [
                'geo_name' => $data['state'],
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            ];
            $db->insert("geographical_divisions", $districtData);
            $lastDistrictId = $db->getInsertId();
            $data['facilityDistrictId'] = $lastDistrictId;
        }

        if (isset($data['reportEmail']) && trim((string) $data['reportEmail']) !== '') {
            $expEmail = explode(",", (string) $data['reportEmail']);
            $counter = count($expEmail);
            for ($i = 0; $i < $counter; $i++) {
                $reportEmail = filter_var($expEmail[$i], FILTER_VALIDATE_EMAIL);
                if ($reportEmail != '') {
                    if ($email != '') {
                        $email .= "," . $reportEmail;
                    } else {
                        $email .= $reportEmail;
                    }
                }
            }
        }

        if (!empty($data['testingPoints'])) {
            $data['testingPoints'] = explode(",", (string) $data['testingPoints']);
            $data['testingPoints'] = array_map('trim', $data['testingPoints']);
            $data['testingPoints'] = JsonUtility::encodeUtf8Json($data['testingPoints']);
        } else {
            $data['testingPoints'] = null;
        }


        $data['api'] = "yes";


        $facilityFulldata = [
            'vlsm_instance_id' => $instanceId,
            'facility_name' => $data['facilityName'],
            'facility_code' => $data['facilityCode'],
            'facility_type' => $data['facilityType'],
            'facility_emails' => $data['facilityEmails'],
            'report_email' => $email,
            'contact_person' => $data['contactPerson'],
            'facility_mobile_numbers' => $data['facilityMobileNumbers'],
            'address' => $data['address'],
            'country' => $data['country'],
            'facility_state_id' => $data['facilityStateId'],
            'facility_district_id' => $data['facilityDistrictId'],
            'facility_state' => $data['facilityState'],
            'facility_district' => $data['facilityDistrict'],
            'facility_hub_name' => $data['facilityHubName'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            //  'facility_attributes'  => $data['facilityAttributes'],
            'testing_points' => $data['testingPoints'],
            'facility_logo' => $data['facilityLogo'],
            'header_text' => $data['headerText'],
            'status' => $data['status'],
            'test_type' => $data['testType'],
            'report_format' => $data['reportFormat']
        ];


        $id = $db->insert('facility_details', $facilityFulldata);

        if ($id) {
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'status' => 'success'
            ];

            $payload = [
                'facilityName' => $data['facilityName'],
                'facilityCode' => $data['facilityCode'],
                'status' => 'success',
                'timestamp' => time(),
                'transactionId' => $transactionId,
            ];
        } else {
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'facilityName' => $data['facilityName'],
                'facilityCode' => $data['facilityCode'],
                'status' => 'failed',
                'action' => 'skipped',
                'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
            ];
            $payload = [
                'status' => 'failed',
                'timestamp' => time(),
                'transactionId' => $transactionId,
                'data' => $responseData ?? []
            ];
        }
    }


    $db->commitTransaction();
    http_response_code(200);
} catch (Throwable $exc) {
    $db->rollbackTransaction();
    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
        'data' => []
    ];
    if (!empty($db->getLastError())) {
        LoggerUtility::logError($exc->getFile() . ':' . $exc->getLine() . ":" . $db->getLastError());
    }
    LoggerUtility::logError($exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'stacktrace' => $exc->getTraceAsString()
    ]);
}


$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], $dataCounter, 'save-request', 'facility', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json', null, null, $authToken);

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
