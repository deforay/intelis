<?php


use App\Registries\AppRegistry;
use JsonMachine\Items;
use App\Services\VlService;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Utilities\LoggerUtility;
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

try {

    $db->beginTransaction();

    /** @var Slim\Psr7\Request $request */
    $request = AppRegistry::get('request');
    $noOfFailedRecords = 0;

    $origJson = $request->getBody()->getContents();

    $appVersion = null;
    try {
        $appVersion = Items::fromString($origJson, [
            'pointer' => '/appVersion',
            'decoder' => new ExtJsonDecoder(true)
        ]);
        $appVersion = iterator_to_array($appVersion)['appVersion'];


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

    //echo '<pre>'; print_r($input); die;


    $transactionId = $general->generateUUID();

    $globalConfig = $general->getGlobalConfig();
    $vlsmSystemConfig = $general->getSystemConfig();

    $authToken = $general->getAuthorizationBearerToken();
    $user = $usersService->getUserByToken($authToken);
    $roleUser = $usersService->getUserRole($user['user_id']);
    $responseData = [];
    $instanceId = $general->getInstanceId();

    $version = $vlsmSystemConfig['sc_version'];
    $deviceId = $general->getHeader('deviceId');


    foreach ($input as $rootKey => $data) {

        $mandatoryFields = [
            'facility_name',
            'facility_code',
            'facility_type'
        ];


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
            $stateData = array(
                'geo_name' => $stateData['state'],
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            );
            $db->insert("geographical_divisions", $stateData);
            $lastStateId = $db->getInsertId();
            $data['facilityStateId'] = $lastStateId;
        }

        $districtCheckQuery = "SELECT geo_id,geo_name FROM geographical_divisions WHERE geo_name = ? AND geo_parent = ?";
        $districtInfo = $db->rawQueryOne($districtCheckQuery, [$data['state'], $data['facilityStateId']]);
        if (isset($districtInfo['geo_name']) && !empty($districtInfo['geo_name'])) {
            $data['facilityDistrictId'] = $districtInfo['geo_id'];
        } else {
            $districtData = array(
                'geo_name' => $stateData['state'],
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            );
            $db->insert("geographical_divisions", $districtData);
            $lastDistrictId = $db->getInsertId();
            $data['facilityDistrictId'] = $lastDistrictId;
        }

        if (isset($data['reportEmail']) && trim((string) $data['reportEmail']) != '') {
            $expEmail = explode(",", (string) $data['reportEmail']);
            for ($i = 0; $i < count($expEmail); $i++) {
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
            $data['testingPoints'] = json_encode($data['testingPoints']);
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
        error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());

        if ($id) {
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'status' => 'success'
            ];

            $payload = [
                'status' => 'success',
                'timestamp' => time(),
                'transactionId' => $transactionId,
            ];
        } else {
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'status' => 'failed',
                'action' => 'skipped',
                'error' => $db->getLastError()
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
        'error' => $exc->getMessage(),
        'data' => []
    ];
    error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    error_log(__FILE__ . ":" . __LINE__ . ":" . $exc->getMessage());
    error_log(__FILE__ . ":" . __LINE__ . ":" . $exc->getTraceAsString());
    LoggerUtility::log('error', $exc->getFile() . ":" . $exc->getLine() . " - " . $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}


$payload = json_encode($payload);
$general->addApiTracking($transactionId, $user['user_id'], iterator_count($input), 'save-request', 'facility', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');
echo $payload;
