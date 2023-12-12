<?php

use App\Registries\AppRegistry;
use JsonMachine\Items;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Exception\PathNotFoundException;

session_unset(); // no need of session in json response


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $app */
$app = ContainerRegistry::get(ApiService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var GenericTestsService $genericService */
$genericService = ContainerRegistry::get(GenericTestsService::class);


try {

    $db->beginTransaction();
    ini_set('memory_limit', -1);
    set_time_limit(0);
    ini_set('max_execution_time', 20000);

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
    } catch (PathNotFoundException $ex) {
        throw new SystemException("Invalid request");
    }

    $user = null;
    $tableName = "form_generic";
    $tableName1 = "activity_log";
    $testTableName = 'generic_test_results';
    $globalConfig = $general->getGlobalConfig();
    $vlsmSystemConfig = $general->getSystemConfig();

    /* For API Tracking params */
    $requestUrl = $_SERVER['HTTP_HOST'];
    $requestUrl .= $_SERVER['REQUEST_URI'];
    $authToken = $general->getAuthorizationBearerToken();
    $user = $usersService->getUserByToken($authToken);
    $roleUser = $usersService->getUserRole($user['user_id']);
    $responseData = [];

    $instanceId = $general->getInstanceId();
    $formId = (int) $general->getGlobalConfig('vl_form');

    /* Update form attributes */
    $transactionId = $general->generateUUID();
    $version = $general->getSystemConfig('sc_version');
    $deviceId = $general->getHeader('deviceId');

    foreach ($input as $rootKey => $data) {

        $mandatoryFields = [
            'sampleCollectionDate',
            'facilityId',
            'appSampleCode'
        ];
        $cantBeFutureDates = [
            'sampleCollectionDate',
            'patientDob',
            'sampleTestedDateTime',
            'sampleDispatchedOn',
            'sampleReceivedDate',
        ];

        if ($formId == COUNTRY\PNG) {
            $mandatoryFields[] = 'provinceId';
        }

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
        $sampleCollectionDate = $data['sampleCollectionDate'] = DateUtility::isoDateFormat($data['sampleCollectionDate'], true);

        $update = "no";
        $rowData = null;
        $uniqueId = null;
        if (!empty($data['uniqueId']) || !empty($data['appSampleCode'])) {
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

            if (!empty($data['uniqueId'])) {
                $uniqueId = $data['uniqueId'];
                $sQueryWhere[] = " unique_id like '" . $data['uniqueId'] . "'";
            }
            if (!empty($data['appSampleCode'])) {
                $sQueryWhere[] = " app_sample_code like '" . $data['appSampleCode'] . "'";
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
            }
        }

        if (empty($uniqueId) || $uniqueId === 'undefined' || $uniqueId === 'null') {
            $uniqueId = $data['uniqueId'] = $general->generateUUID();
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
            $params['sampleCollectionDate'] = $sampleCollectionDate;
            $params['userId'] = $user['user_id'];
            $params['accessType'] = $user['access_type'];
            $params['instanceType'] = $vlsmSystemConfig['sc_user_type'];
            $params['facilityId'] = $data['facilityId'] ?? null;
            $params['labId'] = $data['labId'] ?? null;

            $params['insertOperation'] = true;
            $currentSampleData = $genericService->insertSample($params, true);
            $currentSampleData['action'] = 'inserted';
            $data['genericSampleId'] = intval($currentSampleData['id']);
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

        if (!empty($data['arrivalDateTime']) && trim((string) $data['arrivalDateTime']) != "") {
            $arrivalDate = explode(" ", (string) $data['arrivalDateTime']);
            $data['arrivalDateTime'] = DateUtility::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
        } else {
            $data['arrivalDateTime'] = null;
        }
        if (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "yes") {
            $data['result'] = null;
            $status = SAMPLE_STATUS\REJECTED;
        } elseif ((isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") && (!empty($data['result']))) {
            $status = SAMPLE_STATUS\PENDING_APPROVAL;
        }

        if (!empty($data['sampleCollectionDate']) && trim((string) $data['sampleCollectionDate']) != "") {
            $sampleCollectionDate = $data['sampleCollectionDate'] = DateUtility::isoDateFormat($data['sampleCollectionDate'], true);
        } else {
            $sampleCollectionDate = $data['sampleCollectionDate'] = null;
        }


        //Set sample received date
        if (!empty($data['sampleReceivedDate']) && trim((string) $data['sampleReceivedDate']) != "") {
            $sampleReceivedDate = explode(" ", (string) $data['sampleReceivedDate']);
            $data['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
        } else {
            $data['sampleReceivedDate'] = null;
        }

        if (!empty($data['sampleReceivedHubDate']) && trim((string) $data['sampleReceivedHubDate']) != "") {
            $sampleReceivedHubDate = explode(" ", (string) $data['sampleReceivedHubDate']);
            $data['sampleReceivedHubDate'] = DateUtility::isoDateFormat($sampleReceivedHubDate[0]) . " " . $sampleReceivedHubDate[1];
        } else {
            $data['sampleReceivedHubDate'] = null;
        }
        if (!empty($data['sampleTestedDateTime']) && trim((string) $data['sampleTestedDateTime']) != "") {
            $sampleTestedDate = explode(" ", (string) $data['sampleTestedDateTime']);
            $data['sampleTestedDateTime'] = DateUtility::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
        } else {
            $data['sampleTestedDateTime'] = null;
        }

        if (!empty($data['arrivalDateTime']) && trim((string) $data['arrivalDateTime']) != "") {
            $arrivalDate = explode(" ", (string) $data['arrivalDateTime']);
            $data['arrivalDateTime'] = DateUtility::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
        } else {
            $data['arrivalDateTime'] = null;
        }

        if (!empty($data['revisedOn']) && trim((string) $data['revisedOn']) != "") {
            $revisedOn = explode(" ", (string) $data['revisedOn']);
            $data['revisedOn'] = DateUtility::isoDateFormat($revisedOn[0]) . " " . $revisedOn[1];
        } else {
            $data['revisedOn'] = null;
        }

        if (isset($data['resultDispatchedOn']) && trim((string) $data['resultDispatchedOn']) != "") {
            $resultDispatchedOn = explode(" ", (string) $data['resultDispatchedOn']);
            $data['resultDispatchedOn'] = DateUtility::isoDateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
        } else {
            $data['resultDispatchedOn'] = null;
        }

        if (isset($data['sampleDispatchedOn']) && trim((string) $data['sampleDispatchedOn']) != "") {
            $sampleDispatchedOn = explode(" ", (string) $data['sampleDispatchedOn']);
            $data['sampleDispatchedOn'] = DateUtility::isoDateFormat($sampleDispatchedOn[0]) . " " . $sampleDispatchedOn[1];
        } else {
            $data['sampleDispatchedOn'] = null;
        }

        if (isset($data['sampleDispatchedDate']) && trim((string) $data['sampleDispatchedDate']) != "") {
            $sampleDispatchedDate = explode(" ", (string) $data['sampleDispatchedDate']);
            $data['sampleDispatchedDate'] = DateUtility::isoDateFormat($sampleDispatchedDate[0]) . " " . $sampleDispatchedDate[1];
        } else {
            $data['sampleDispatchedDate'] = null;
        }

        $formAttributes = [
            'applicationVersion' => $version,
            'apiTransactionId' => $transactionId,
            'mobileAppVersion' => $appVersion,
            'deviceId' => $deviceId
        ];
        $formAttributes = $general->jsonToSetString(json_encode($formAttributes), 'form_attributes');
        $testTypeForm = $general->jsonToSetString(json_encode($data['testTypeForm']), 'test_type_form');

        $genericData = [
            'vlsm_instance_id' => $data['instanceId'],
            'vlsm_country_id' => $formId,
            'unique_id' => $uniqueId,
            'test_type' => !empty($data['testType']) ? $data['testType'] : null,
            'test_type_form' => !empty($testTypeForm) ? $db->func($testTypeForm) : null,
            'app_sample_code' => !empty($data['appSampleCode']) ? $data['appSampleCode'] : null,
            'external_sample_code' => !empty($data['externalSampleCode']) ? $data['externalSampleCode'] : null,
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
            'patient_dob' => !empty($data['patientDob']) ? DateUtility::isoDateFormat($data['patientDob']) : null,
            'patient_gender' => !empty($data['patientGender']) ? $data['patientGender'] : null,
            'patient_age_in_years' => !empty($data['patientAge']) ? $data['patientAge'] : null,
            'patient_address' => !empty($data['patientAddress']) ? $data['patientAddress'] : null,
            'reason_for_testing' => !empty($data['reasonForTest']) ? json_encode($data['reasonForTest']) : null,
            'test_urgency' => !empty($data['testUrgency']) ? $data['testUrgency'] : null,
            'sample_type' => !empty($data['specimenType']) ? $data['specimenType'] : null,
            'sample_collection_date' => $data['sampleCollectionDate'],
            'sample_dispatched_datetime' => $data['sampleDispatchedOn'],
            'result_dispatched_datetime' => $data['resultDispatchedOn'],
            'sample_tested_datetime' => $data['sampleTestedDateTime'] ?? null,
            'sample_received_at_hub_datetime' => !empty($data['sampleReceivedHubDate']) ? $data['sampleReceivedHubDate'] : null,
            'sample_received_at_testing_lab_datetime' => !empty($data['sampleReceivedDate']) ? $data['sampleReceivedDate'] : null,
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
            'reason_for_test_result_changes' => (!empty($data['reasonForResultChanges'])) ? $data['reasonForResultChanges'] : null,
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
            $genericData['sample_registered_at_lab'] = DateUtility::getCurrentDateTime();
            $genericData['request_created_by'] = $user['user_id'];
        }

        if (isset($data['genericSampleId']) && $data['genericSampleId'] != '' && ($data['isSampleRejected'] == 'no' || $data['isSampleRejected'] == '')) {
            if (!empty($data['testResult'])) {
                $db->where('sample_id', $data['genericSampleId']);
                $db->delete($testTableName);

                foreach ($data['testResult'] as $testKey => $testResult) {
                    if (!empty($testResult) && trim((string) $testResult) != "") {
                        $db->insert($testTableName, [
                            'sample_id' => $data['genericSampleId'],
                            'actual_no' => $data['actualNo'][$testKey] ?? null,
                            'test_result' => $testResult,
                            'updated_datetime' => DateUtility::getCurrentDateTime()
                        ]);
                    }
                }
            }
        } else {
            $db->where('sample_id', $data['genericSampleId']);
            $db->delete($testTableName);
        }
        $id = false;
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
                'error' => $db->getLastError()
            ];
        }
    }

    if ($noOfFailedRecords > 0 && $noOfFailedRecords == iterator_count($input)) {
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
} catch (SystemException $exc) {
    $db->rollbackTransaction();
    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'error' => $exc->getMessage(),
        'data' => []
    ];
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
$payload = json_encode($payload);
$general->addApiTracking($transactionId, $user['user_id'], iterator_count($input), 'save-request', 'generic-tests', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');
echo $payload;
