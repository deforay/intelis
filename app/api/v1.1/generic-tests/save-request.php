<?php

use JsonMachine\Items;
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
use App\Services\GenericTestsService;
use App\Services\TestRequestsService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
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

/** @var GenericTestsService $genericService */
$genericService = ContainerRegistry::get(GenericTestsService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

try {

    $db->beginTransaction();
    ini_set('memory_limit', -1);
    set_time_limit(0);
    ini_set('max_execution_time', 20000);

    /** @var Slim\Psr7\Request $request */
    $request = AppRegistry::get('request');
    $noOfFailedRecords = 0;

    $origJson = $apiService->getJsonFromRequest($request);
    if (JsonUtility::isJSON($origJson) === false) {
        throw new SystemException("Invalid JSON Payload", 400);
    }
    $appVersion = null;
    try {
        $appVersion = Items::fromString($origJson, [
            'pointer' => '/appVersion',
            'decoder' => new ExtJsonDecoder(true)
        ]);


        $appVersion = _getIteratorKey($appVersion, 'appVersion');

        $input = Items::fromString($origJson, [
            'pointer' => '/data',
            'decoder' => new ExtJsonDecoder(true)
        ]);
        if (empty($input)) {
            throw new PathNotFoundException();
        }
    } catch (PathNotFoundException | Throwable $ex) {
        throw new SystemException("Invalid request");
    }

    $user = null;
    $tableName = "form_generic";
    $tableName1 = "activity_log";
    $testTableName = 'generic_test_results';

    /* For API Tracking params */
    $requestUrl = $_SERVER['HTTP_HOST'];
    $requestUrl .= $_SERVER['REQUEST_URI'];
    $authToken = ApiService::extractBearerToken($request);
    $user = $usersService->findUserByApiToken($authToken);
    $roleUser = $usersService->getUserRole($user['user_id']);
    $responseData = [];
    $uniqueIdsForSampleCodeGeneration = [];

    $instanceId = $general->getInstanceId();
    $formId = (int) $general->getGlobalConfig('vl_form');

    /* Update form attributes */
    $transactionId = MiscUtility::generateULID();
    $version = $general->getAppVersion();
    /* To save the user attributes from API */
    $userAttributes = [];
    foreach (array('deviceId', 'osVersion', 'ipAddress') as $header) {
        $userAttributes[$header] = $apiService->getHeader($request, $header);
    }
    $userAttributes = JsonUtility::jsonToSetString(json_encode($userAttributes), 'user_attributes');
    $usersService->saveUserAttributes($userAttributes, $user['user_id']);
    if (isset($input) && !empty($input)) {
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

            if ($formId == COUNTRY\PNG) {
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
                if (!empty($province)) {
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
            $provinceCode = (!empty($data['provinceCode'])) ? $data['provinceCode'] : null;
            $provinceId = (!empty($data['provinceId'])) ? $data['provinceId'] : null;

            $data['sampleCollectionDate'] = DateUtility::isoDateFormat($data['sampleCollectionDate'] ?? '', true);
            $data['sampleReceivedDate'] = DateUtility::isoDateFormat($data['sampleReceivedDate'] ?? '');
            $data['sampleReceivedHubDate'] = DateUtility::isoDateFormat($data['sampleReceivedHubDate'] ?? '');
            $data['sampleTestedDateTime'] = DateUtility::isoDateFormat($data['sampleTestedDateTime'] ?? '', true);
            $data['arrivalDateTime'] = DateUtility::isoDateFormat($data['arrivalDateTime'] ?? '', true);
            $data['revisedOn'] = DateUtility::isoDateFormat($data['revisedOn'] ?? '');
            $data['resultDispatchedOn'] = DateUtility::isoDateFormat($data['resultDispatchedOn'] ?? '');
            $data['sampleDispatchedOn'] = DateUtility::isoDateFormat($data['sampleDispatchedOn'] ?? '');
            $data['sampleDispatchedDate'] = DateUtility::isoDateFormat($data['sampleDispatchedDate'] ?? '', true);
            $data['arrivalDateTime'] = DateUtility::isoDateFormat($data['arrivalDateTime'] ?? '', true);


            $update = "no";
            $rowData = null;
            $uniqueId = null;
            if (!empty($data['labId']) && !empty($data['appSampleCode'])) {
                $sQuery = "SELECT sample_id,
                unique_id,
                sample_code,
                sample_code_format,
                sample_code_key,
                remote_sample_code,
                remote_sample_code_format,
                remote_sample_code_key,
                result_status,
                locked
                FROM form_generic ";
                $sQueryWhere = [];


                if (!empty($data['appSampleCode']) && !empty($data['labId'])) {
                    $sQueryWhere[] = " (app_sample_code like '" . $data['appSampleCode'] . "' AND lab_id = '" . $data['labId'] . "') ";
                }

                if (!empty($sQueryWhere)) {
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
                $data['genericSampleId'] = $rowData['sample_id'];
                $currentSampleData['sampleCode'] = $rowData['sample_code'] ?? null;
                $currentSampleData['remoteSampleCode'] = $rowData['remote_sample_code'] ?? null;
                $currentSampleData['action'] = 'updated';
            } else {
                $params['appSampleCode'] = $data['appSampleCode'] ?? null;
                $params['provinceCode'] = $provinceCode;
                $params['provinceId'] = $provinceId;
                $params['uniqueId'] = $uniqueId;
                $params['sampleCollectionDate'] = $data['sampleCollectionDate'];
                $params['userId'] = $user['user_id'];
                $params['accessType'] = $user['access_type'];
                $params['instanceType'] = $general->getInstanceType();
                $params['facilityId'] = $data['facilityId'] ?? null;
                $params['labId'] = $data['labId'] ?? null;

                $params['insertOperation'] = true;
                $currentSampleData = $genericService->insertSample($params, returnSampleData: true);
                $uniqueIdsForSampleCodeGeneration[] = $currentSampleData['uniqueId'] = $uniqueId;
                $currentSampleData['action'] = 'inserted';
                $data['genericSampleId'] = (int) $currentSampleData['id'];
                if ($data['genericSampleId'] == 0) {
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

            $status = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
            if ($roleUser['access_type'] != 'testing-lab') {
                $status = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
            }


            if (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "yes") {
                $data['result'] = null;
                $status = SAMPLE_STATUS\REJECTED;
            } elseif ((isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") && (!empty($data['result']))) {
                $status = SAMPLE_STATUS\PENDING_APPROVAL;
            }
            $formAttributes = [
                'applicationVersion' => $version,
                'apiTransactionId' => $transactionId,
                'mobileAppVersion' => $appVersion,
                'deviceId' => $userAttributes['deviceId'] ?? null
            ];
            $formAttributes = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');

            /* Reason for VL Result changes */
            $reasonForChanges = null;
            $allChange = [];
            if (isset($data['reasonForResultChanges']) && !empty($data['reasonForResultChanges'])) {
                foreach ($data['reasonForResultChanges'] as $row) {
                    $allChange[] = array(
                        'usr' => $row['changed_by'],
                        'msg' => $row['reason'],
                        'dtime' => $row['change_datetime']
                    );
                }
            }
            if (!empty($allChange)) {
                $reasonForChanges = json_encode($allChange);
            }

            $testTypeForm = JsonUtility::jsonToSetString(json_encode($data['testTypeForm']), 'test_type_form');

            $genericData = [
                'vlsm_instance_id' => $data['instanceId'],
                'vlsm_country_id' => $formId,
                'unique_id' => $uniqueId,
                'test_type' => !empty($data['testType']) ? $data['testType'] : null,
                'test_type_form' => !empty($testTypeForm) ? $db->func($testTypeForm) : null,
                'external_sample_code' => $data['externalSampleCode'] ?? $data['appSampleCode'] ?? null,
                'app_sample_code' => $data['appSampleCode'] ?? $data['externalSampleCode'] ?? null,
                'sample_reordered' => !empty($data['sampleReordered']) ? $data['sampleReordered'] : 'no',
                'facility_id' => !empty($data['facilityId']) ? $data['facilityId'] : null,
                'province_id' => !empty($data['provinceId']) ? $data['provinceId'] : null,
                'lab_id' => !empty($data['labId']) ? $data['labId'] : null,
                'implementing_partner' => !empty($data['implementingPartner']) ? $data['implementingPartner'] : null,
                'funding_source' => !empty($data['fundingSource']) ? $data['fundingSource'] : null,
                'patient_id' => !empty($data['patientId']) ? $data['patientId'] : null,
                'patient_first_name' => !empty($data['firstName']) ? $data['firstName'] : null,
                'patient_middle_name' => !empty($data['middleName']) ? $data['middleName'] : null,
                'patient_last_name' => !empty($data['lastName']) ? $data['lastName'] : null,
                'patient_dob' => !empty($data['dob']) ? DateUtility::isoDateFormat($data['dob']) : null,
                'patient_gender' => !empty($data['patientGender']) ? $data['patientGender'] : null,
                'patient_age_in_years' => !empty($data['patientAge']) ? $data['patientAge'] : null,
                'patient_address' => !empty($data['patientAddress']) ? $data['patientAddress'] : null,
                'reason_for_testing' => !empty($data['reasonForTest']) ? json_encode($data['reasonForTest']) : null,
                'test_urgency' => !empty($data['testUrgency']) ? $data['testUrgency'] : null,
                'specimen_type' => !empty($data['specimenType']) ? $data['specimenType'] : null,
                'sample_collection_date' => $data['sampleCollectionDate'],
                'sample_dispatched_datetime' => $data['sampleDispatchedOn'],
                'result_dispatched_datetime' => $data['resultDispatchedOn'],
                'sample_tested_datetime' => $data['sampleTestedDateTime'] ?? null,
                'sample_received_at_hub_datetime' => !empty($data['sampleReceivedHubDate']) ? $data['sampleReceivedHubDate'] : null,
                'sample_received_at_lab_datetime' => !empty($data['sampleReceivedDate']) ? $data['sampleReceivedDate'] : null,
                'lab_technician' => (!empty($data['labTechnician']) && $data['labTechnician'] != '') ? $data['labTechnician'] : $user['user_id'],
                'is_sample_rejected' => !empty($data['isSampleRejected']) ? $data['isSampleRejected'] : null,
                'result' => !empty($data['result']) ? $data['result'] : null,
                'tested_by' => !empty($data['testedBy']) ? $data['testedBy'] : null,
                'result_reviewed_by' => !empty($data['reviewedBy']) ? $data['reviewedBy'] : null,
                'result_reviewed_datetime' => !empty($data['reviewedOn']) ? DateUtility::isoDateFormat($data['reviewedOn']) : null,
                'result_approved_by' => !empty($data['approvedBy']) ? $data['approvedBy'] : null,
                'result_approved_datetime' => !empty($data['approvedOn']) ? DateUtility::isoDateFormat($data['approvedOn']) : null,
                'lab_tech_comments' => !empty($data['approverComments']) ? $data['approverComments'] : null,
                'revised_by' => (isset($data['revisedBy']) && $data['revisedBy'] != "") ? $data['revisedBy'] : "",
                'revised_on' => (isset($data['revisedOn']) && $data['revisedOn'] != "") ? $data['revisedOn'] : null,
                'reason_for_test_result_changes' => $reasonForChanges ?? null,
                'rejection_on' => (!empty($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($data['rejectionDate']) : null,
                'result_status' => $status,
                'data_sync' => 0,
                'reason_for_sample_rejection' => (isset($data['sampleRejectionReason']) && $data['isSampleRejected'] == 'yes') ? $data['sampleRejectionReason'] : null,
                'source_of_request' => $data['sourceOfRequest'] ?? "API",
                'form_attributes' => !empty($formAttributes) ? $db->func($formAttributes) : null
            ];
            if (!empty($rowData)) {
                $genericData['last_modified_datetime'] = (!empty($data['updatedOn'])) ? DateUtility::isoDateFormat($data['updatedOn'], true) : DateUtility::getCurrentDateTime();
                $genericData['last_modified_by'] = $user['user_id'];
            } else {
                $genericData['request_created_datetime'] = DateUtility::isoDateFormat($data['createdOn'] ?? date('Y-m-d'), true);
                $genericData['request_created_by'] = $user['user_id'];
            }
            if (isset($data['genericSampleId']) && $data['genericSampleId'] != '' && ($data['isSampleRejected'] == 'no' || $data['isSampleRejected'] == '')) {
                if (!empty($data['testName'])) {
                    $finalResult = "";
                    if (isset($data['subTestResult']) && !empty($data['subTestResult'])) {
                        foreach ($data['testName'] as $subTestName => $subTests) {
                            foreach ($subTests as $testKey => $testKitName) {
                                if (!empty($testKitName)) {
                                    $testData = array(
                                        'generic_id' => $data['vlSamplgenericSampleIdeId'],
                                        'sub_test_name' => $subTestName,
                                        'result_type' => $data['resultType'][$subTestName],
                                        'test_name' => ($testKitName == 'other') ? $data['testNameOther'][$subTestName][$testKey] : $testKitName,
                                        'facility_id' => $data['labId'] ?? null,
                                        'sample_tested_datetime' => DateUtility::isoDateFormat($data['testDate'][$subTestName][$testKey] ?? ''),
                                        'testing_platform' => $data['testingPlatform'][$subTestName][$testKey] ?? null,
                                        'kit_lot_no' => (str_contains((string)$testKitName, 'RDT')) ? $data['lotNo'][$subTestName][$testKey] : null,
                                        'kit_expiry_date' => (str_contains((string)$testKitName, 'RDT')) ? DateUtility::isoDateFormat($data['expDate'][$subTestName][$testKey]) : null,
                                        'result_unit' => $data['testResultUnit'][$subTestName][$testKey],
                                        'result' => $data['testResult'][$subTestName][$testKey],
                                        'final_result' => $data['finalResult'][$subTestName],
                                        'final_result_unit' => $data['finalTestResultUnit'][$subTestName],
                                        'final_result_interpretation' => $data['resultInterpretation'][$subTestName]
                                    );
                                    $db->insert('generic_test_results', $testData);
                                    if (isset($data['finalResult'][$subTestName]) && !empty($data['finalResult'][$subTestName])) {
                                        $finalResult = $data['finalResult'][$subTestName];
                                    }
                                }
                            }
                        }
                    } else {
                        foreach ($data['testName'] as $testKey => $testKitName) {
                            if (!empty($data['testName'][$testKey][0])) {
                                $testData = array(
                                    'generic_id' => $data['genericSampleId'] ?? null,
                                    'sub_test_name' => null,
                                    'result_type' => $data['resultType'][$testKey][0] ?? null,
                                    'test_name' => ($data['testName'][$testKey][0] == 'other') ? $data['testNameOther'][$testKey][0] : $data['testName'][$testKey][0],
                                    'facility_id' => $data['labId'] ?? null,
                                    'sample_tested_datetime' => (isset($data['testDate'][$testKey][0]) && !empty($data['testDate'][$testKey][0])) ? DateUtility::isoDateFormat($data['testDate'][$testKey][0]) : null,
                                    'testing_platform' => $data['testingPlatform'][$testKey][0] ?? null,
                                    'kit_lot_no' => (str_contains((string)$data['testName'][$testKey][0], 'RDT')) ? $data['lotNo'][$testKey][0] : null,
                                    'kit_expiry_date' => (str_contains((string)$data['testName'][$testKey][0], 'RDT')) ? DateUtility::isoDateFormat($data['expDate'][$testKey][0]) : null,
                                    'result_unit' => $data['testResultUnit'][$testKey][0] ?? null,
                                    'result' => $data['testResult'][$testKey][0] ?? null
                                );
                                foreach ($data['finalResult'] as $key => $value) {
                                    if (isset($value) && !empty($value)) {
                                        $testData['final_result'] = $value;
                                    }
                                    if (isset($data['finalTestResultUnit'][$key]) && !empty($data['finalTestResultUnit'][$key])) {
                                        $testData['final_result_unit'] = $data['finalTestResultUnit'][$key];
                                    }
                                    if (isset($data['resultInterpretation'][$key]) && !empty($data['resultInterpretation'][$key])) {
                                        $testData['final_result_interpretation'] = $data['resultInterpretation'][$key];
                                    }
                                }
                                $db->insert('generic_test_results', $testData);
                                if (isset($testData['final_result']) && !empty($testData['final_result'])) {
                                    $finalResult = $testData['final_result'];
                                }
                            }
                        }
                    }
                    $genericData['result'] = $finalResult;
                }
            } else {
                $db->where('generic_id', $data['genericSampleId']);
                $db->delete($testTableName);
                $genericData['sample_tested_datetime'] = null;
            }
            $id = false;
            $genericData = MiscUtility::arrayEmptyStringsToNull($genericData);
            if (!empty($data['genericSampleId'])) {
                $db->where('sample_id', $data['genericSampleId']);
                $id = $db->update($tableName, $genericData);
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
    }


    // Commit transaction after processing all records
    // we are doing this before generating sample codes as that is a separate process in itself
    $db->commitTransaction();

    // For inserted samples, generate sample code
    if (!empty($uniqueIdsForSampleCodeGeneration)) {
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
    $db->commitTransaction();
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
$general->addApiTracking($transactionId, $user['user_id'], $dataCounter, 'save-request', 'generic-tests', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
