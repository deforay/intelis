<?php

use JsonMachine\Items;
use App\Services\ApiService;
use App\Services\EidService;
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
use App\Services\TestRequestsService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Exception\PathNotFoundException;

session_unset(); // no need of session in json response
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

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

try {

    $db->beginTransaction();

    /** @var Slim\Psr7\Request $request */
    $request = AppRegistry::get('request');

    $noOfFailedRecords = 0;

    $updatedLabs = [];
    $uniqueIdsForSampleCodeGeneration = [];

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
    } catch (PathNotFoundException | Throwable $ex) {
        throw new SystemException("Invalid request");
    }

    $transactionId = MiscUtility::generateULID();
    $globalConfig = $general->getGlobalConfig();
    $user = null;

    if (empty($input)) {
        throw new SystemException("Invalid request");
    }

    /* For API Tracking params */
    $requestUrl = $_SERVER['HTTP_HOST'];
    $requestUrl .= $_SERVER['REQUEST_URI'];
    $authToken = ApiService::extractBearerToken($request);
    $user = $usersService->findUserByApiToken($authToken);
    $roleUser = $usersService->getUserRole($user['user_id']);

    $instanceId = $general->getInstanceId();
    $formId = (int) $general->getGlobalConfig('vl_form');

    /* Update form attributes */
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
            'childDob',
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
        if (!is_numeric($data['implementingPartner'])) {
            $data['implementingPartner'] = $general->getValueByName($data['implementingPartner'], 'i_partner_name', 'r_implementation_partners', 'i_partner_id');
        }
        if (!is_numeric($data['fundingSource'])) {
            $data['fundingSource'] = $general->getValueByName($data['fundingSource'], 'funding_source_name', 'r_funding_sources', 'funding_source_id');
        }

        $data['api'] = "yes";

        $provinceCode = (!empty($data['provinceCode'])) ? $data['provinceCode'] : null;
        $provinceId = (!empty($data['provinceId'])) ? $data['provinceId'] : null;
        $sampleCollectionDate = $data['sampleCollectionDate'] = DateUtility::isoDateFormat($data['sampleCollectionDate'], true);

        $update = "no";
        $rowData = null;
        $uniqueId = null;
        if (!empty($data['labId']) && !empty($data['appSampleCode'])) {

            $sQuery = "SELECT eid_id,
                            unique_id,
                            sample_code,
                            sample_code_format,
                            sample_code_key,
                            remote_sample_code,
                            remote_sample_code_format,
                            remote_sample_code_key,
                            result_status,
                            locked
                            FROM form_eid ";

            $sQueryWhere = [];

            if (!empty($data['appSampleCode']) && !empty($data['labId'])) {
                $sQueryWhere[] = " (app_sample_code like '" . $data['appSampleCode'] . "' AND lab_id = '" . $data['labId'] . "') ";
            }

            if (!empty($sQueryWhere)) {
                $sQuery .= " WHERE " . implode(" OR ", $sQueryWhere);
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
            $data['eidSampleId'] = $rowData['eid_id'];
            $currentSampleData['sampleCode'] = $rowData['sample_code'] ?? null;
            $currentSampleData['remoteSampleCode'] = $rowData['remote_sample_code'] ?? null;
            $currentSampleData['uniqueId'] = $rowData['unique_id'] ?? null;
            $currentSampleData['action'] = 'updated';
        } else {
            $params['appSampleCode'] = $data['appSampleCode'] ?? null;
            $params['provinceCode'] = $provinceCode;
            $params['provinceId'] = $provinceId;
            $params['uniqueId'] = $uniqueId;
            $params['sampleCollectionDate'] = $sampleCollectionDate;
            $params['userId'] = $user['user_id'];
            $params['accessType'] = $user['access_type'];
            $params['instanceType'] = $general->getInstanceType();
            $params['facilityId'] = $data['facilityId'] ?? null;
            $params['labId'] = $data['labId'] ?? null;

            $params['insertOperation'] = true;
            $currentSampleData = $eidService->insertSample($params, returnSampleData: true);
            $uniqueIdsForSampleCodeGeneration[] = $currentSampleData['uniqueId'] = $uniqueId;
            $currentSampleData['action'] = 'inserted';
            $data['eidSampleId'] = (int) $currentSampleData['id'];
            if ($data['eidSampleId'] == 0) {
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
        } elseif (
            isset($globalConfig['eid_auto_approve_api_results']) &&
            $globalConfig['eid_auto_approve_api_results'] == "yes" &&
            (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") &&
            (!empty($data['result']))
        ) {
            $status = SAMPLE_STATUS\ACCEPTED;
        } elseif ((isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") && (!empty($data['result']))) {
            $status = SAMPLE_STATUS\PENDING_APPROVAL;
        }

        if (isset($data['approvedOn']) && trim((string) $data['approvedOn']) != "") {
            $data['approvedOn'] = DateUtility::isoDateFormat($data['approvedOn'], true);
        } else {
            $data['approvedOn'] = null;
        }

        //Set sample received date
        if (!empty($data['sampleReceivedDate']) && trim((string) $data['sampleReceivedDate']) != "") {
            $data['sampleReceivedDate'] = DateUtility::isoDateFormat($data['sampleReceivedDate'], true);
        } else {
            $data['sampleReceivedDate'] = null;
        }
        if (!empty($data['sampleTestedDateTime']) && trim((string) $data['sampleTestedDateTime']) != "") {
            $data['sampleTestedDateTime'] = DateUtility::isoDateFormat($data['sampleTestedDateTime'], true);
        } else {
            $data['sampleTestedDateTime'] = null;
        }

        if (isset($data['rapidtestDate']) && trim((string) $data['rapidtestDate']) != "") {
            $data['rapidtestDate'] = DateUtility::isoDateFormat($data['rapidtestDate']);
        } else {
            $data['rapidtestDate'] = null;
        }

        if (isset($data['childDob']) && trim((string) $data['childDob']) != "") {
            $data['childDob'] = DateUtility::isoDateFormat($data['childDob']);
        } else {
            $data['childDob'] = null;
        }

        if (isset($data['mothersDob']) && trim((string) $data['mothersDob']) != "") {
            $data['mothersDob'] = DateUtility::isoDateFormat($data['mothersDob']);
        } else {
            $data['mothersDob'] = null;
        }

        if (isset($data['motherTreatmentInitiationDate']) && trim((string) $data['motherTreatmentInitiationDate']) != "") {
            $data['motherTreatmentInitiationDate'] = DateUtility::isoDateFormat($data['motherTreatmentInitiationDate']);
        } else {
            $data['motherTreatmentInitiationDate'] = null;
        }

        if (isset($data['previousPCRTestDate']) && trim((string) $data['previousPCRTestDate']) != "") {
            $data['previousPCRTestDate'] = DateUtility::isoDateFormat($data['previousPCRTestDate']);
        } else {
            $data['previousPCRTestDate'] = null;
        }

        if (isset($data['motherViralLoadCopiesPerMl']) && $data['motherViralLoadCopiesPerMl'] != "") {
            $motherVlResult = $data['motherViralLoadCopiesPerMl'];
        } elseif (isset($data['motherViralLoadText']) && $data['motherViralLoadText'] != "") {
            $motherVlResult = $data['motherViralLoadText'];
        } else {
            $motherVlResult = null;
        }
        if (isset($data['reviewedOn']) && trim((string) $data['reviewedOn']) != "") {
            $data['reviewedOn'] = DateUtility::isoDateFormat($data['reviewedOn']);
        } else {
            $data['reviewedOn'] = null;
        }

        if (isset($data['resultDispatchedOn']) && trim((string) $data['resultDispatchedOn']) != "") {
            $data['resultDispatchedOn'] = DateUtility::isoDateFormat($data['resultDispatchedOn'], true);
        } else {
            $data['resultDispatchedOn'] = null;
        }

        if (isset($data['sampleDispatchedOn']) && trim((string) $data['sampleDispatchedOn']) != "") {
            $data['sampleDispatchedOn'] = DateUtility::isoDateFormat($data['sampleDispatchedOn'], true);
        } else {
            $data['sampleDispatchedOn'] = null;
        }

        if (!empty($data['revisedOn']) && trim((string) $data['revisedOn']) != "") {
            $data['revisedOn'] = DateUtility::isoDateFormat($data['revisedOn'], true);
        } else {
            $data['revisedOn'] = null;
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

        $formAttributes = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');
        /* New API changes start */
        foreach (array("motherTreatment", "childTreatment", "childTreatmentOther") as $key) {
            if (is_array($data[$key])) {
                $data[$key] = implode(",", $data[$key]);
            } else {
                if (str_contains($data[$key], '##')) {
                    $data[$key] = str_replace("##", ",", $data[$key]);
                }
            }
        }
        /* New API changes end */
        $eidData = [
            'vlsm_instance_id' => $instanceId,
            'external_sample_code' => $data['externalSampleCode'] ?? $data['appSampleCode'] ?? null,
            'app_sample_code' => $data['appSampleCode'] ?? $data['externalSampleCode'] ?? null,
            'facility_id' => $data['facilityId'] ?? null,
            'province_id' => $data['provinceId'] ?? null,
            'lab_id' => $data['labId'] ?? null,
            'implementing_partner' => $data['implementingPartner'] ?? null,
            'funding_source' => $data['fundingSource'] ?? null,
            'mother_id' => $data['mothersId'] ?? null,
            'caretaker_contact_consent' => $data['caretakerConsentForContact'] ?? null,
            'caretaker_phone_number' => $data['caretakerPhoneNumber'] ?? null,
            'caretaker_address' => $data['caretakerAddress'] ?? null,
            'mother_name' => (!empty($data['mothersName']) && $data['mothersName'] != 'undefined') ? $data['mothersName'] : null,
            'mother_dob' => $data['mothersDob'] ?? null,
            'mother_marital_status' => $data['mothersMaritalStatus'] ?? null,
            'mother_treatment' => $data['motherTreatment'] ?? null,
            'mother_treatment_other' => $data['motherTreatmentOther'] ?? null,
            'mother_treatment_initiation_date' => $data['motherTreatmentInitiationDate'] ?? null,
            'child_id' => $data['childId'] ?? null,
            'child_name' => $data['childName'] ?? null,
            'child_surname' => $data['childSurName'] ?? null,
            'child_dob' => $data['childDob'] ?? null,
            'child_gender' => $data['childGender'] ?? null,
            'health_insurance_code' => $data['healthInsuranceCode'] ?? null,
            'child_age' => $data['childAge'] ?? null,
            'child_age_in_weeks' => $data['childAgeInWeeks'] ?? null,
            'child_treatment' => $data['childTreatment'] ?? null,
            'child_treatment_other' => $data['childTreatmentOther'] ?? null,
            'mother_cd4' => $data['mothercd4'] ?? null,
            'mother_vl_result' => $motherVlResult,
            'mother_hiv_status' => $data['mothersHIVStatus'] ?? null,
            'pcr_test_performed_before' => $data['pcrTestPerformedBefore'] ?? null,
            'previous_pcr_result' => $data['prePcrTestResult'] ?? null,
            'last_pcr_date' => $data['previousPCRTestDate'] ?? null,
            'reason_for_pcr' => $data['pcrTestReason'] ?? null,
            'has_infant_stopped_breastfeeding' => $data['hasInfantStoppedBreastfeeding'] ?? null,
            'age_breastfeeding_stopped_in_months' => $data['ageBreastfeedingStopped'] ?? null,
            'choice_of_feeding' => $data['choiceOfFeeding'] ?? null,
            'is_cotrimoxazole_being_administered_to_the_infant' => $data['isCotrimoxazoleBeingAdministered'] ?? null,
            'specimen_type' => $data['specimenType'] ?? null,
            'sample_collection_date' => $sampleCollectionDate,
            'sample_dispatched_datetime' => $data['sampleDispatchedOn'],
            'result_dispatched_datetime' => $data['resultDispatchedOn'],
            'sample_requestor_phone' => $data['sampleRequestorPhone'] ?? null,
            'sample_requestor_name' => $data['sampleRequestorName'] ?? null,
            'rapid_test_performed' => $data['rapidTestPerformed'] ?? null,
            'rapid_test_date' => $data['rapidtestDate'] ?? null,
            'rapid_test_result' => $data['rapidTestResult'] ?? null,
            'lab_reception_person' => $data['labReceptionPerson'] ?? null,
            'sample_received_at_lab_datetime' => $data['sampleReceivedDate'] ?? null,
            'eid_test_platform' => $data['eidPlatform'] ?? null,
            'import_machine_name' => $data['machineName'] ?? null,
            'sample_tested_datetime' => $data['sampleTestedDateTime'] ?? null,
            'is_sample_rejected' => $data['isSampleRejected'] ?? null,
            'result' => $data['result'] ?? null,
            'tested_by' => (isset($data['testedBy']) && $data['testedBy'] != '') ? $data['testedBy'] : $user['user_id'],
            'result_approved_by' => (isset($data['approvedBy']) && $data['approvedBy'] != '') ? $data['approvedBy'] : null,
            'result_approved_datetime' => (isset($data['approvedOn']) && $data['approvedOn'] != '') ? $data['approvedOn'] : null,
            'lab_tech_comments' => !empty($data['approverComments']) ? $data['approverComments'] : null,
            'result_reviewed_by' => (isset($data['reviewedBy']) && $data['reviewedBy'] != "") ? $data['reviewedBy'] : null,
            'result_reviewed_datetime' => (isset($data['reviewedOn']) && $data['reviewedOn'] != "") ? $data['reviewedOn'] : null,
            'revised_by' => (isset($data['revisedBy']) && $data['revisedBy'] != "") ? $data['revisedBy'] : "",
            'revised_on' => (isset($data['revisedOn']) && $data['revisedOn'] != "") ? $data['revisedOn'] : "",
            'reason_for_changing' => $reasonForChanges ?? null,
            // 'reason_for_changing' => (!empty($data['reasonForEidResultChanges'])) ? $data['reasonForEidResultChanges'] : null,
            'result_status' => $status,
            'data_sync' => 0,
            'reason_for_sample_rejection' => $data['sampleRejectionReason'] ?? null,
            'rejection_on' => (isset($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($data['rejectionDate']) : null,
            'source_of_request' => $data['sourceOfRequest'] ?? "API",
            'form_attributes' => !empty($formAttributes) ? $db->func($formAttributes) : null
        ];

        if (!empty($rowData)) {
            $eidData['last_modified_datetime'] = (!empty($data['updatedOn'])) ? DateUtility::isoDateFormat($data['updatedOn'], true) : DateUtility::getCurrentDateTime();
            $eidData['last_modified_by'] = $user['user_id'];
        } else {
            $eidData['request_created_datetime'] = DateUtility::isoDateFormat($data['createdOn'] ?? date('Y-m-d'), true);
            $eidData['request_created_by'] = $user['user_id'];
        }

        $id = false;
        $eidData = MiscUtility::arrayEmptyStringsToNull($eidData);
        if (!empty($data['eidSampleId'])) {
            $db->where('eid_id', $data['eidSampleId']);
            $id = $db->update('form_eid', $eidData);
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

    if (!empty($data['lab_id'])) {
        $updatedLabs[] = $data['lab_id'];
    }

    $payload = [
        'status' => $payloadStatus,
        'timestamp' => time(),
        'transactionId' => $transactionId,
        'data' => $responseData ?? []
    ];
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
$general->addApiTracking($transactionId, $user['user_id'], $dataCounter, 'save-request', 'eid', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');

$general->updateResultSyncDateTime('eid', null, $updatedLabs);

//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
