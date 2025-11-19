<?php

use const COUNTRY\PNG;
use JsonMachine\Items;
use Slim\Psr7\Request;
use App\Services\TbService;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\REJECTED;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use JsonMachine\Exception\PathNotFoundException;

session_unset(); // no need of session in json response

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

try {

    $db->beginTransaction();
    ini_set('memory_limit', -1);
    set_time_limit(0);
    ini_set('max_execution_time', 20000);

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
    } catch (PathNotFoundException | Throwable) {
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
    } catch (PathNotFoundException | Throwable) {
        throw new SystemException("Invalid request");
    }

    $user = null;
    $tableName = "form_tb";
    $tableName1 = "activity_log";
    $testTableName = 'tb_tests';
    $globalConfig = $general->getGlobalConfig();
    $vlsmSystemConfig = $general->getSystemConfig();

    /* For API Tracking params */
    $requestUrl = $_SERVER['HTTP_HOST'];
    $requestUrl .= $_SERVER['REQUEST_URI'];
    $authToken = ApiService::extractBearerToken($request);
    $user = $usersService->findUserByApiToken($authToken);
    $roleUser = $usersService->getUserRole($user['user_id']);

    $uniqueIdsForSampleCodeGeneration = [];

    $instanceId = $general->getInstanceId();
    $formId = (int) $general->getGlobalConfig('vl_form');

    /* Update form attributes */
    $transactionId = MiscUtility::generateULID();
    $version = $general->getAppVersion();
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
            'sampleCollectionDate',
            'facilityId',
            'appSampleCode',
            'labId'
        ];
        $cantBeFutureDates = [
            'sampleCollectionDate',
            'dob',
            'sampleTestedDateTime',
            'sampleDispatchedOn',
            'sampleReceivedDate',
        ];

        if ($formId == PNG) {
            $mandatoryFields[] = 'provinceId';
        }

        $data = MiscUtility::arrayEmptyStringsToNull($data);

        if (MiscUtility::hasEmpty(array_intersect_key($data, array_flip($mandatoryFields)))) {
            $noOfFailedRecords++;
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'appSampleCode' => $data['appSampleCode'] ?? null,
                'status' => 'failed',
                'action' => 'skipped',
                'message' => _translate("Missing required fields")
            ];
            continue;
        } elseif (DateUtility::hasFutureDates(array_intersect_key($data, array_flip($cantBeFutureDates)))) {
            $noOfFailedRecords++;
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'appSampleCode' => $data['appSampleCode'] ?? null,
                'status' => 'failed',
                'action' => 'skipped',
                'message' => _translate("Invalid Dates. Cannot be in the future")
            ];
            continue;
        }


        if (!empty($data['provinceId']) && !is_numeric($data['provinceId'])) {
            $province = explode("##", (string) $data['provinceId']);
            if ($province !== []) {
                $data['provinceId'] = $province[0];
            }
            $data['provinceId'] = $general->getValueByName($data['provinceId'], 'geo_name', 'geographical_divisions', 'geo_id');
        }
        if (isset($data['implementingPartner']) && !is_numeric($data['implementingPartner'])) {
            $data['implementingPartner'] = $general->getValueByName($data['implementingPartner'], 'i_partner_name', 'r_implementation_partners', 'i_partner_id');
        }
        if (isset($data['fundingSource']) && !is_numeric($data['fundingSource'])) {
            $data['fundingSource'] = $general->getValueByName($data['fundingSource'], 'funding_source_name', 'r_funding_sources', 'funding_source_id');
        }

        $data['api'] = "yes";
        $provinceCode = (empty($data['provinceCode'])) ? null : $data['provinceCode'];
        $provinceId = (empty($data['provinceId'])) ? null : $data['provinceId'];


        $data['sampleCollectionDate'] = DateUtility::isoDateFormat($data['sampleCollectionDate'] ?? '', true);
        $data['sampleReceivedDate'] = DateUtility::isoDateFormat($data['sampleReceivedDate'] ?? '');
        $data['sampleReceivedHubDate'] = DateUtility::isoDateFormat($data['sampleReceivedHubDate'] ?? '');
        $data['sampleTestedDateTime'] = DateUtility::isoDateFormat($data['sampleTestedDateTime'] ?? '');
        $data['arrivalDateTime'] = DateUtility::isoDateFormat($data['arrivalDateTime'] ?? '');
        $data['revisedOn'] = DateUtility::isoDateFormat($data['revisedOn'] ?? '');
        $data['resultDispatchedOn'] = DateUtility::isoDateFormat($data['resultDispatchedOn'] ?? '');
        $data['sampleDispatchedOn'] = DateUtility::isoDateFormat($data['sampleDispatchedOn'] ?? '');
        $data['sampleDispatchedDate'] = DateUtility::isoDateFormat($data['sampleDispatchedDate'] ?? '');
        $data['arrivalDateTime'] = DateUtility::isoDateFormat($data['arrivalDateTime'] ?? '');



        $update = "no";
        $rowData = null;
        $uniqueId = null;
        if (!empty($data['labId']) && !empty($data['appSampleCode'])) {
            $sQuery = "SELECT tb_id,
            unique_id,
            sample_code,
            sample_code_format,
            sample_code_key,
            remote_sample_code,
            remote_sample_code_format,
            remote_sample_code_key,
            result_status,
            locked
            FROM form_tb ";
            $sQueryWhere = [];

            if (!empty($data['appSampleCode']) && !empty($data['labId'])) {
                $sQueryWhere[] = " (app_sample_code like '" . $data['appSampleCode'] . "' AND lab_id = '" . $data['labId'] . "') ";
            }

            if ($sQueryWhere !== []) {
                $sQuery .= " WHERE " . implode(" AND ", $sQueryWhere);
            }

            $rowData = $db->rawQueryOne($sQuery);

            if (!empty($rowData)) {
                if ($rowData['result_status'] == 7 || $rowData['locked'] == 'yes') {
                    $noOfFailedRecords++;
                    $responseData[$rootKey] = [
                        'transactionId' => $transactionId,
                        'appSampleCode' => $data['appSampleCode'] ?? null,
                        'status' => 'failed',
                        'action' => 'skipped',
                        'error' => _translate("Sample Locked or Finalized")

                    ];
                    continue;
                }
                $update = "yes";
                $uniqueId = $data['uniqueId'] = $rowData['unique_id'];
            } else {
                $uniqueId = MiscUtility::generateULID();
            }
        }

        $currentSampleData = [];
        if (!empty($rowData)) {
            $data['tbSampleId'] = $rowData['tb_id'];
            $currentSampleData['sampleCode'] = $rowData['sample_code'] ?? null;
            $currentSampleData['remoteSampleCode'] = $rowData['remote_sample_code'] ?? null;
            $currentSampleData['uniqueId'] = $rowData['unique_id'] ?? null;
            $currentSampleData['action'] = 'updated';
        } else {
            $params['appSampleCode'] = $data['appSampleCode'] ?? null;
            $params['provinceCode'] = $provinceCode;
            $params['provinceId'] = $provinceId;
            $params['uniqueId'] = $uniqueId ?? MiscUtility::generateULID();
            $params['sampleCollectionDate'] = $data['sampleCollectionDate'];
            $params['userId'] = $user['user_id'];
            $params['accessType'] = $user['access_type'];
            $params['instanceType'] = $general->getInstanceType();
            $params['facilityId'] = $data['facilityId'] ?? null;
            $params['labId'] = $data['labId'] ?? null;

            $params['insertOperation'] = true;
            $currentSampleData = $tbService->insertSample($params, returnSampleData: true);
            $uniqueIdsForSampleCodeGeneration[] = $currentSampleData['uniqueId'] = $uniqueId;
            $currentSampleData['action'] = 'inserted';
            $data['tbSampleId'] = (int) $currentSampleData['id'];
            if ($data['tbSampleId'] == 0) {
                $noOfFailedRecords++;
                $responseData[$rootKey] = [
                    'transactionId' => $transactionId,
                    'appSampleCode' => $data['appSampleCode'] ?? null,
                    'status' => 'failed',
                    'action' => 'skipped',
                    'error' => _translate("Failed to insert sample")
                ];
                continue;
            }
        }

        $status = RECEIVED_AT_TESTING_LAB;
        if ($roleUser['access_type'] != 'testing-lab') {
            $status = RECEIVED_AT_CLINIC;
        }


        if (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "yes") {
            $data['result'] = null;
            $status = REJECTED;
        } elseif (
            isset($globalConfig['tb_auto_approve_api_results']) &&
            $globalConfig['tb_auto_approve_api_results'] == "yes" &&
            (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") &&
            (!empty($data['result']))
        ) {
            $status = ACCEPTED;
        } elseif ((isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") && (!empty($data['result']))) {
            $status = PENDING_APPROVAL;
        }

        $formAttributes = [
            'applicationVersion' => $version,
            'apiTransactionId' => $transactionId,
            'mobileAppVersion' => $appVersion,
            'deviceId' => $userAttributes['deviceId'] ?? null
        ];
        /* Reason for VL Result changes */
        $reasonForChanges = null;
        $allChange = [];
        if (isset($data['reasonForResultChanges']) && !empty($data['reasonForResultChanges'])) {
            foreach ($data['reasonForResultChanges'] as $row) {
                $allChange[] = [
                    'usr' => $row['changed_by'],
                    'msg' => $row['reason'],
                    'dtime' => $row['change_datetime']
                ];
            }
        }
        if ($allChange !== []) {
            $reasonForChanges = json_encode($allChange);
        }
        $formAttributes = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');

        $tbData = [
            'vlsm_instance_id' => $data['instanceId'],
            'vlsm_country_id' => $formId,
            'unique_id' => $uniqueId,
            'external_sample_code' => $data['externalSampleCode'] ?? $data['appSampleCode'] ?? null,
            'app_sample_code' => $data['appSampleCode'] ?? $data['externalSampleCode'] ?? null,
            'sample_reordered' => empty($data['sampleReordered']) ? 'no' : $data['sampleReordered'],
            'facility_id' => empty($data['facilityId']) ? null : $data['facilityId'],
            'province_id' => empty($data['provinceId']) ? null : $data['provinceId'],
            'referring_unit' => empty($data['referringUnit']) ? null : $data['referringUnit'],
            'sample_requestor_name' => empty($data['sampleRequestorName']) ? null : $data['sampleRequestorName'],
            'sample_requestor_phone' => empty($data['sampleRequestorPhone']) ? null : $data['sampleRequestorPhone'],
            'specimen_quality' => empty($data['specimenQuality']) ? null : $data['specimenQuality'],
            'other_referring_unit' => empty($data['otherReferringUnit']) ? null : $data['otherReferringUnit'],
            'lab_id' => empty($data['labId']) ? null : $data['labId'],
            'implementing_partner' => empty($data['implementingPartner']) ? null : $data['implementingPartner'],
            'funding_source' => empty($data['fundingSource']) ? null : $data['fundingSource'],
            'patient_id' => empty($data['patientId']) ? null : $data['patientId'],
            'patient_name' => empty($data['firstName']) ? null : $data['firstName'],
            'patient_surname' => empty($data['lastName']) ? null : $data['lastName'],
            'patient_dob' => empty($data['dob']) ? null : DateUtility::isoDateFormat($data['dob']),
            'patient_gender' => empty($data['patientGender']) ? null : $data['patientGender'],
            'patient_age' => empty($data['patientAge']) ? null : $data['patientAge'],
            'patient_address' => empty($data['patientAddress']) ? null : $data['patientAddress'],
            'patient_type' => empty($data['patientType']) ? null : json_encode($data['patientType']),
            'other_patient_type' => empty($data['otherPatientType']) ? null : $data['otherPatientType'],
            'hiv_status' => empty($data['hivStatus']) ? null : $data['hivStatus'],
            'reason_for_tb_test' => empty($data['reasonFortbTest']) ? null : json_encode($data['reasonFortbTest']),
            'tests_requested' => empty($data['testTypeRequested']) ? null : json_encode($data['testTypeRequested']),
            'specimen_type' => empty($data['specimenType']) ? null : $data['specimenType'],
            'other_specimen_type' => empty($data['otherSpecimenType']) ? null : $data['otherSpecimenType'],
            'sample_collection_date' => $data['sampleCollectionDate'],
            'sample_dispatched_datetime' => $data['sampleDispatchedOn'],
            'result_dispatched_datetime' => $data['resultDispatchedOn'],
            'sample_tested_datetime' => $data['sampleTestedDateTime'] ?? null,
            'sample_received_at_hub_datetime' => empty($data['sampleReceivedHubDate']) ? null : $data['sampleReceivedHubDate'],
            'sample_received_at_lab_datetime' => empty($data['sampleReceivedDate']) ? null : $data['sampleReceivedDate'],
            'lab_technician' => (!empty($data['labTechnician']) && $data['labTechnician'] != '') ? $data['labTechnician'] : $user['user_id'],
            'lab_reception_person' => (!empty($data['labReceptionPerson']) && $data['labReceptionPerson'] != '') ? $data['labReceptionPerson'] : null,
            'is_sample_rejected' => empty($data['isSampleRejected']) ? null : $data['isSampleRejected'],
            'result' => empty($data['result']) ? null : $data['result'],
            'xpert_mtb_result' => empty($data['xpertMtbResult']) ? null : $data['xpertMtbResult'],
            'tested_by' => empty($data['testedBy']) ? null : $data['testedBy'],
            'result_reviewed_by' => empty($data['reviewedBy']) ? null : $data['reviewedBy'],
            'result_reviewed_datetime' => empty($data['reviewedOn']) ? null : DateUtility::isoDateFormat($data['reviewedOn']),
            'result_approved_by' => empty($data['approvedBy']) ? null : $data['approvedBy'],
            'result_approved_datetime' => empty($data['approvedOn']) ? null : DateUtility::isoDateFormat($data['approvedOn']),
            'lab_tech_comments' => empty($data['approverComments']) ? null : $data['approverComments'],
            'revised_by' => (isset($data['revisedBy']) && $data['revisedBy'] != "") ? $data['revisedBy'] : "",
            'revised_on' => (isset($data['revisedOn']) && $data['revisedOn'] != "") ? $data['revisedOn'] : null,
            'reason_for_changing' => $reasonForChanges ?? null,
            // 'reason_for_changing' => (!empty($data['reasonFortbResultChanges'])) ? $data['reasonFortbResultChanges'] : null,
            'rejection_on' => (!empty($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($data['rejectionDate']) : null,
            'result_status' => $status,
            'data_sync' => 0,
            'reason_for_sample_rejection' => (isset($data['sampleRejectionReason']) && $data['isSampleRejected'] == 'yes') ? $data['sampleRejectionReason'] : null,
            'source_of_request' => $data['sourceOfRequest'] ?? "API",
            'form_attributes' => $formAttributes === null || $formAttributes === '' || $formAttributes === '0' ? null : $db->func($formAttributes)
        ];
        if (!empty($rowData)) {
            $tbData['last_modified_datetime'] = (empty($data['updatedOn'])) ? DateUtility::getCurrentDateTime() : DateUtility::isoDateFormat($data['updatedOn'], true);
            $tbData['last_modified_by'] = $user['user_id'];
        } else {
            $tbData['request_created_datetime'] = DateUtility::isoDateFormat($data['createdOn'] ?? date('Y-m-d'), true);
            $tbData['request_created_by'] = $user['user_id'];
        }

        $tbData['last_modified_by'] = $user['user_id'];

        if (isset($data['tbSampleId']) && $data['tbSampleId'] != '' && ($data['isSampleRejected'] == 'no' || $data['isSampleRejected'] == '')) {
            if (!empty($data['testResults'])) {
                $db->where('tb_id', $data['tbSampleId']);
                $db->delete($testTableName);

                foreach ($data['testResults'] as $testKey => $testResult) {
                    if (isset($testResult['testResult']) && !empty($testResult['testResult'])) {
                        $db->insert($testTableName, [
                            'tb_id' => $data['tbSampleId'],
                            'actual_no' => $testResult['actualNo'] ?? null,
                            'test_result' => $testResult['testResult'],
                            'updated_datetime' => DateUtility::getCurrentDateTime()
                        ]);
                    }
                }
            }
        } else {
            $db->where('tb_id', $data['tbSampleId']);
            $db->delete($testTableName);
        }
        $id = false;
        if (!empty($data['tbSampleId'])) {
            $db->where('tb_id', $data['tbSampleId']);
            $id = $db->update($tableName, $tbData);
        }
        if ($id === true) {
            $responseData[$rootKey] = [
                'status' => 'success',
                'action' => $currentSampleData['action'] ?? null,
                'sampleCode' => $currentSampleData['remoteSampleCode'] ?? $currentSampleData['sampleCode'] ?? null,
                'transactionId' => $transactionId,
                'uniqueId' => $uniqueId ?? $currentSampleData['uniqueId'] ?? null,
                'appSampleCode' => $data['appSampleCode'] ?? null,
            ];
        } else {
            $noOfFailedRecords++;
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'status' => 'failed',
                'action' => 'skipped',
                'appSampleCode' => $data['appSampleCode'] ?? null,
                'error' => _translate('Failed to process this request. Please contact the system administrator if the problem persists'),
            ];
        }
    }


    // Commit transaction after processing all records
    // we are doing this before generating sample codes as that is a separate process in itself
    $db->commitTransaction();

    // For inserted samples, generate sample code
    if ($uniqueIdsForSampleCodeGeneration !== []) {
        $sampleCodeData = $testRequestsService->processSampleCodeQueue(uniqueIds: $uniqueIdsForSampleCodeGeneration, parallelProcess: true);
        if (!empty($sampleCodeData)) {
            foreach ($responseData as $rootKey => $currentSampleData) {
                $uniqueId = $currentSampleData['uniqueId'] ?? null;
                if ($uniqueId && isset($sampleCodeData[$uniqueId])) {
                    $responseData[$rootKey]['sampleCode'] = $sampleCodeData[$uniqueId]['remote_sample_code'] ?? $sampleCodeData[$uniqueId]['sample_code'] ?? null;
                }
            }
        }
    }

    if ($noOfFailedRecords > 0 && $noOfFailedRecords == $dataCounter) {
        $payloadStatus = 'failed';
    } elseif ($noOfFailedRecords > 0) {
        $payloadStatus = 'partial';
    } else {
        $payloadStatus = 'success';
    }

    $payload = [
        'status' => $payloadStatus,
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'data' => $responseData ?? []
    ];
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
    LoggerUtility::logError($exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'trace' => $exc->getTraceAsString()
    ]);
}
$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], $dataCounter, 'save-request', 'tb', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
