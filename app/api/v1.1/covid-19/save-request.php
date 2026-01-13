<?php

use Slim\Psr7\Request;
use const COUNTRY\PNG;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use JsonMachine\Items;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\Covid19Service;
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

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

try {

    $db->beginTransaction();
    /** @var Request $request */
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
    $primaryKey = 'covid19_id';
    $tableName = "form_covid19";
    $tableName1 = "activity_log";
    $testTableName = 'covid19_tests';
    $transactionId = MiscUtility::generateULID();
    $globalConfig = $general->getGlobalConfig();
    $vlsmSystemConfig = $general->getSystemConfig();
    $user = null;


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
        if (isset($data['patientNationality']) && !is_numeric($data['patientNationality'])) {
            $iso = explode("(", (string) $data['patientNationality']);
            if ($iso !== []) {
                $data['patientNationality'] = trim($iso[0]);
            }
            $data['patientNationality'] = $general->getValueByName($data['patientNationality'], 'iso_name', 'r_countries', 'id');
        }
        $pprovince = explode("##", (string) $data['patientProvince']);
        if ($pprovince !== []) {
            $data['patientProvince'] = $pprovince[0];
        }

        $data['api'] = "yes";
        $provinceCode = (empty($data['provinceCode'])) ? null : $data['provinceCode'];
        $provinceId = (empty($data['provinceId'])) ? null : $data['provinceId'];
        $sampleCollectionDate = $data['sampleCollectionDate'] = DateUtility::isoDateFormat($data['sampleCollectionDate'], true);

        $update = "no";
        $rowData = null;
        $uniqueId = null;
        if (!empty($data['labId']) && !empty($data['appSampleCode'])) {

            $sQuery = "SELECT covid19_id,
                            sample_code,
                            unique_id,
                            sample_code_format,
                            sample_code_key,
                            remote_sample_code,
                            remote_sample_code_format,
                            remote_sample_code_key,
                            result_status,
                            locked
                            FROM form_covid19 ";

            $sQueryWhere = [];

            if (!empty($data['appSampleCode']) && !empty($data['labId'])) {
                $sQueryWhere[] = " (app_sample_code like '" . $data['appSampleCode'] . "' AND lab_id = '" . $data['labId'] . "') ";
            }

            if ($sQueryWhere !== []) {
                $sQuery .= " WHERE " . implode(" OR ", $sQueryWhere);
            }

            $rowData = $db->rawQueryOne($sQuery);

            if (!empty($rowData)) {
                if ($rowData['result_status'] == ACCEPTED || $rowData['locked'] == 'yes') {
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
                $uniqueId = $rowData['unique_id'];
            } else {
                $uniqueId = MiscUtility::generateULID();
            }
        }


        $currentSampleData = [];
        if (!empty($rowData)) {
            $data['covid19SampleId'] = $rowData['covid19_id'];
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
            $currentSampleData = $covid19Service->insertSample($params, returnSampleData: true);
            $uniqueIdsForSampleCodeGeneration[] = $currentSampleData['uniqueId'] = $uniqueId;
            $currentSampleData['action'] = 'inserted';
            $data['covid19SampleId'] = (int) $currentSampleData['id'];
            if ($data['covid19SampleId'] == 0) {
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
            isset($globalConfig['covid19_auto_approve_api_results']) &&
            $globalConfig['covid19_auto_approve_api_results'] == "yes" &&
            (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") &&
            (!empty($data['result']))
        ) {
            $status = ACCEPTED;
        } elseif ((isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") && (!empty($data['result']))) {
            $status = PENDING_APPROVAL;
        }

        //Set sample received date
        if (!empty($data['sampleReceivedDate']) && trim((string) $data['sampleReceivedDate']) !== "") {
            $data['sampleReceivedDate'] = DateUtility::isoDateFormat($data['sampleReceivedDate'], true);
        } else {
            $data['sampleReceivedDate'] = null;
        }
        if (!empty($data['sampleTestedDateTime']) && trim((string) $data['sampleTestedDateTime']) !== "") {
            $data['sampleTestedDateTime'] = DateUtility::isoDateFormat($data['sampleTestedDateTime'], true);
        } else {
            $data['sampleTestedDateTime'] = null;
        }

        if (!empty($data['arrivalDateTime']) && trim((string) $data['arrivalDateTime']) !== "") {
            $data['arrivalDateTime'] = DateUtility::isoDateFormat($data['arrivalDateTime'], true);
        } else {
            $data['arrivalDateTime'] = null;
        }

        if (!empty($data['revisedOn']) && trim((string) $data['revisedOn']) !== "") {
            $data['revisedOn'] = DateUtility::isoDateFormat($data['revisedOn'], true);
        } else {
            $data['revisedOn'] = null;
        }

        if (isset($data['approvedOn']) && trim((string) $data['approvedOn']) !== "") {
            $data['approvedOn'] = DateUtility::isoDateFormat($data['approvedOn'], true);
        } else {
            $data['approvedOn'] = null;
        }

        if (isset($data['reviewedOn']) && trim((string) $data['reviewedOn']) !== "") {
            $data['reviewedOn'] = DateUtility::isoDateFormat($data['reviewedOn'], true);
        } else {
            $data['reviewedOn'] = null;
        }
        if (isset($data['resultDispatchedOn']) && trim((string) $data['resultDispatchedOn']) !== "") {
            $data['resultDispatchedOn'] = DateUtility::isoDateFormat($data['resultDispatchedOn'], true);
        } else {
            $data['resultDispatchedOn'] = null;
        }

        if (isset($data['sampleDispatchedOn']) && trim((string) $data['sampleDispatchedOn']) !== "") {
            $data['sampleDispatchedOn'] = DateUtility::isoDateFormat($data['sampleDispatchedOn'], true);
        } else {
            $data['sampleDispatchedOn'] = null;
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
        /* New API changes start */
        if (isset($data['patientDob']) && !empty($data['patientDob'])) {
            $data['dob'] = $data['patientDob'];
        }
        if (isset($data['rejectionReasonId']) && !empty($data['rejectionReasonId'])) {
            $data['sampleRejectionReason'] = $data['rejectionReasonId'];
        }
        /* New API changes end */
        $formAttributes = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');

        $covid19Data = [
            'vlsm_instance_id' => $data['instanceId'],
            'external_sample_code' => $data['externalSampleCode'] ?? $data['appSampleCode'] ?? null,
            'app_sample_code' => $data['appSampleCode'] ?? $data['externalSampleCode'] ?? null,
            'lab_sample_code' => $data['labSampleCode'] ?? null,
            'facility_id' => empty($data['facilityId']) ? null : $data['facilityId'],
            'investigator_name' => empty($data['investigatorName']) ? null : $data['investigatorName'],
            'investigator_phone' => empty($data['investigatorPhone']) ? null : $data['investigatorPhone'],
            'investigator_email' => empty($data['investigatorEmail']) ? null : $data['investigatorEmail'],
            'clinician_name' => empty($data['clinicianName']) ? null : $data['clinicianName'],
            'clinician_phone' => empty($data['clinicianPhone']) ? null : $data['clinicianPhone'],
            'clinician_email' => empty($data['clinicianEmail']) ? null : $data['clinicianEmail'],
            'test_number' => empty($data['testNumber']) ? null : $data['testNumber'],
            'province_id' => empty($data['provinceId']) ? null : $data['provinceId'],
            'lab_id' => empty($data['labId']) ? null : $data['labId'],
            'testing_point' => empty($data['testingPoint']) ? null : $data['testingPoint'],
            'implementing_partner' => empty($data['implementingPartner']) ? null : $data['implementingPartner'],
            'source_of_alert' => empty($data['sourceOfAlertPOE']) ? null : strtolower(str_replace(" ", "-", (string) $data['sourceOfAlertPOE'])),
            'source_of_alert_other' => (!empty($data['sourceOfAlertPOE']) && $data['sourceOfAlertPOE'] == 'others') ? $data['alertPoeOthers'] : null,
            'funding_source' => empty($data['fundingSource']) ? null : $data['fundingSource'],
            'patient_id' => empty($data['patientId']) ? null : $data['patientId'],
            'patient_name' => empty($data['firstName']) ? null : trim((string) $data['firstName']),
            'patient_surname' => empty($data['lastName']) ? null : $data['lastName'],
            'patient_dob' => empty($data['dob']) ? null : DateUtility::isoDateFormat($data['dob']),
            'patient_gender' => empty($data['patientGender']) ? null : $data['patientGender'],
            'health_insurance_code' => $data['healthInsuranceCode'] ?? null,
            'patient_email' => empty($data['patientEmail']) ? null : $data['patientEmail'],
            'is_patient_pregnant' => empty($data['isPatientPregnant']) ? null : $data['isPatientPregnant'],
            'patient_age' => empty($data['patientAge']) ? null : $data['patientAge'],
            'patient_phone_number' => empty($data['patientPhoneNumber']) ? null : $data['patientPhoneNumber'],
            'patient_address' => empty($data['patientAddress']) ? null : $data['patientAddress'],
            'patient_province' => empty($data['patientProvince']) ? null : $data['patientProvince'],
            'patient_district' => empty($data['patientDistrict']) ? null : $data['patientDistrict'],
            'patient_city' => empty($data['patientCity']) ? null : $data['patientCity'],
            'patient_zone' => empty($data['patientZone']) ? null : $data['patientZone'],
            'patient_occupation' => empty($data['patientOccupation']) ? null : $data['patientOccupation'],
            'does_patient_smoke' => empty($data['doesPatientSmoke']) ? null : $data['doesPatientSmoke'],
            'patient_nationality' => empty($data['patientNationality']) ? null : $data['patientNationality'],
            'patient_passport_number' => empty($data['patientPassportNumber']) ? null : $data['patientPassportNumber'],
            'flight_airline' => empty($data['airline']) ? null : $data['airline'],
            'flight_seat_no' => empty($data['seatNo']) ? null : $data['seatNo'],
            'flight_arrival_datetime' => DateUtility::isoDateFormat($data['arrivalDateTime'] ?? '', true),
            'flight_airport_of_departure' => empty($data['airportOfDeparture']) ? null : $data['airportOfDeparture'],
            'flight_transit' => empty($data['transit']) ? null : $data['transit'],
            'reason_of_visit' => empty($data['reasonOfVisit']) ? null : $data['reasonOfVisit'],
            'is_sample_collected' => empty($data['isSampleCollected']) ? null : $data['isSampleCollected'],
            'reason_for_covid19_test' => empty($data['reasonForCovid19Test']) ? null : $data['reasonForCovid19Test'],
            'type_of_test_requested' => empty($data['testTypeRequested']) ? null : $data['testTypeRequested'],
            'specimen_type' => empty($data['specimenType']) ? null : $data['specimenType'],
            'sample_dispatched_datetime' => $data['sampleDispatchedOn'],
            'result_dispatched_datetime' => $data['resultDispatchedOn'],
            'sample_collection_date' => $sampleCollectionDate,
            'health_outcome' => empty($data['healthOutcome']) ? null : $data['healthOutcome'],
            'health_outcome_date' => empty($data['outcomeDate']) ? null : DateUtility::isoDateFormat($data['outcomeDate']),
            // 'is_sampledata_mortem'                => !empty($data['isSamplePostMortem']) ? $data['isSamplePostMortem'] : null,
            'priority_status' => empty($data['priorityStatus']) ? null : $data['priorityStatus'],
            'number_of_days_sick' => empty($data['numberOfDaysSick']) ? null : $data['numberOfDaysSick'],
            'suspected_case' => empty($data['suspectedCase']) ? null : $data['suspectedCase'],
            'date_of_symptom_onset' => empty($data['dateOfSymptomOnset']) ? null : DateUtility::isoDateFormat($data['dateOfSymptomOnset']),
            'date_of_initial_consultation' => empty($data['dateOfInitialConsultation']) ? null : DateUtility::isoDateFormat($data['dateOfInitialConsultation']),
            'fever_temp' => empty($data['feverTemp']) ? null : $data['feverTemp'],
            'medical_history' => empty($data['medicalHistory']) ? null : $data['medicalHistory'],
            'recent_hospitalization' => empty($data['recentHospitalization']) ? null : $data['recentHospitalization'],
            'patient_lives_with_children' => empty($data['patientLivesWithChildren']) ? null : $data['patientLivesWithChildren'],
            'patient_cares_for_children' => empty($data['patientCaresForChildren']) ? null : $data['patientCaresForChildren'],
            'temperature_measurement_method' => empty($data['temperatureMeasurementMethod']) ? null : $data['temperatureMeasurementMethod'],
            'respiratory_rate' => empty($data['respiratoryRate']) ? null : $data['respiratoryRate'],
            'oxygen_saturation' => empty($data['oxygenSaturation']) ? null : $data['oxygenSaturation'],
            'close_contacts' => empty($data['closeContacts']) ? null : $data['closeContacts'],
            'contact_with_confirmed_case' => empty($data['contactWithConfirmedCase']) ? null : $data['contactWithConfirmedCase'],
            'has_recent_travel_history' => empty($data['hasRecentTravelHistory']) ? null : $data['hasRecentTravelHistory'],
            'travel_country_names' => empty($data['countryName']) ? null : $data['countryName'],
            'travel_return_date' => empty($data['returnDate']) ? null : DateUtility::isoDateFormat($data['returnDate']),
            'sample_received_at_lab_datetime' => empty($data['sampleReceivedDate']) ? null : $data['sampleReceivedDate'],
            'sample_condition' => empty($data['sampleCondition']) ? $data['specimenQuality'] ?? null : ($data['sampleCondition']),
            'asymptomatic' => empty($data['asymptomatic']) ? null : $data['asymptomatic'],
            'lab_technician' => (!empty($data['labTechnician']) && $data['labTechnician'] != '') ? $data['labTechnician'] : $user['user_id'],
            'is_sample_rejected' => empty($data['isSampleRejected']) ? null : $data['isSampleRejected'],
            'result' => empty($data['result']) ? null : $data['result'],
            'if_have_other_diseases' => (empty($data['ifOtherDiseases'])) ? null : $data['ifOtherDiseases'],
            'other_diseases' => (!empty($data['otherDiseases']) && $data['result'] != 'positive') ? $data['otherDiseases'] : null,
            'tested_by' => empty($data['testedBy']) ? null : $data['testedBy'],
            'is_result_authorised' => empty($data['isResultAuthorized']) ? null : $data['isResultAuthorized'],
            'lab_tech_comments' => empty($data['approverComments']) ? null : $data['approverComments'],
            'authorized_by' => empty($data['authorizedBy']) ? null : $data['authorizedBy'],
            'authorized_on' => empty($data['authorizedOn']) ? null : DateUtility::isoDateFormat($data['authorizedOn']),
            'revised_by' => (isset($_POST['revisedBy']) && $_POST['revisedBy'] != "") ? $_POST['revisedBy'] : "",
            'revised_on' => (isset($_POST['revisedOn']) && $_POST['revisedOn'] != "") ? $_POST['revisedOn'] : null,
            'result_reviewed_by' => (isset($data['reviewedBy']) && $data['reviewedBy'] != "") ? $data['reviewedBy'] : "",
            'result_reviewed_datetime' => (isset($data['reviewedOn']) && $data['reviewedOn'] != "") ? $data['reviewedOn'] : null,
            'result_approved_by' => (isset($data['approvedBy']) && $data['approvedBy'] != '') ? $data['approvedBy'] : null,
            'result_approved_datetime' => (isset($data['approvedOn']) && $data['approvedOn'] != '') ? $data['approvedOn'] : null,
            'reason_for_changing' => $reasonForChanges ?? null,
            // 'reason_for_changing' => (!empty($_POST['reasonForCovid19ResultChanges'])) ? $_POST['reasonForCovid19ResultChanges'] : null,
            'rejection_on' => (!empty($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($data['rejectionDate']) : null,
            'result_status' => $status,
            'data_sync' => 0,
            'reason_for_sample_rejection' => (isset($data['sampleRejectionReason']) && $data['isSampleRejected'] == 'yes') ? $data['sampleRejectionReason'] : null,
            'source_of_request' => $data['sourceOfRequest'] ?? "API",
            'form_attributes' => $formAttributes === null || $formAttributes === '' || $formAttributes === '0' ? null : $db->func($formAttributes)
        ];
        if (!empty($rowData)) {
            $covid19Data['last_modified_datetime'] = (empty($data['updatedOn'])) ? DateUtility::getCurrentDateTime() : DateUtility::isoDateFormat($data['updatedOn'], true);
            $covid19Data['last_modified_by'] = $user['user_id'];
        } else {
            $covid19Data['request_created_datetime'] = DateUtility::isoDateFormat($data['createdOn'] ?? date('Y-m-d'), true);
            $covid19Data['request_created_by'] = $user['user_id'];
        }

        $covid19Data['request_created_by'] = $user['user_id'];
        $covid19Data['last_modified_by'] = $user['user_id'];
        if (isset($data['asymptomatic']) && $data['asymptomatic'] != "yes") {
            $db->where('covid19_id', $data['covid19SampleId']);
            $db->delete("covid19_patient_symptoms");
            $syptomDetections = $data['covid19PatientSymptomsArray'] ?? $data['symptom'];
            if (!empty($syptomDetections)) {
                $counter = count($syptomDetections);
                for ($i = 0; $i < $counter; $i++) {

                    $data['symptomId'][$i] ??= $syptomDetections[$i]['id'];
                    $data['symptomDetected'][$i] ??= $syptomDetections[$i]['symptom'];
                    $data['symptomDetails'][$i] ??= $syptomDetections[$i]['detail'];

                    $symptomData = [];
                    $symptomData["covid19_id"] = $data['covid19SampleId'];
                    $symptomData["symptom_id"] = $data['symptomId'][$i];
                    $symptomData["symptom_detected"] = $data['symptomDetected'][$i] ?? 'no';
                    if (isset($data['covid19PatientSymptomsArray']) && !empty($data['covid19PatientSymptomsArray'])) {
                        $symptomData["symptom_details"] = $syptomDetections[$i]['detail'] ?? null;
                    } else {
                        $symptomData["symptom_details"] = (empty($data['symptomDetails'][$data['symptomId'][$i]])) ? null : json_encode($data['symptomDetails'][$data['symptomId'][$i]]);
                    }
                    $db->insert("covid19_patient_symptoms", $symptomData);
                }
            }
        }

        if (isset($data['reasonDetails']) && !empty($data['reasonDetails'])) {
            /* For Rest API service data came as multiple index so rechange and remove unwanted index */
            if (array_key_exists('reason_details', (array) $data['reasonDetails'][0])) {
                $res = [];
                foreach ((array) $data['reasonDetails'] as $row) {
                    $res[] = $row['reason_details'];
                }
                $data['reasonDetails'] = $res;
            }
            $db->where('covid19_id', $data['covid19SampleId']);
            $db->delete("covid19_reasons_for_testing");

            $reasonData = [];
            $reasonData["covid19_id"] = $data['covid19SampleId'];
            $reasonData["reasons_id"] = $data['reasonForCovid19Test'];
            $reasonData["reasons_detected"] = "yes";
            $reasonData["reason_details"] = json_encode($data['reasonDetails']);
            $db->insert("covid19_reasons_for_testing", $reasonData);
        }

        $db->where('covid19_id', $data['covid19SampleId']);
        $db->delete("covid19_patient_comorbidities");
        if (!empty($data['comorbidityDetected'])) {
            $counter = count($data['comorbidityDetected']);
            for ($i = 0; $i < $counter; $i++) {
                $comorbidityData = [];
                $comorbidityData["covid19_id"] = $data['covid19SampleId'];
                $comorbidityData["comorbidity_id"] = $data['comorbidityId'][$i];
                $comorbidityData["comorbidity_detected"] = $data['comorbidityDetected'][$i];
                $db->insert("covid19_patient_comorbidities", $comorbidityData);
            }
        }
        if (isset($data['covid19SampleId']) && $data['covid19SampleId'] != '' && ($data['isSampleRejected'] == 'no' || $data['isSampleRejected'] == '')) {
            if (!empty($data['c19Tests'])) {
                $db->where('covid19_id', $data['covid19SampleId']);
                $db->delete($testTableName);
                foreach ($data['c19Tests'] as $testKey => $test) {
                    if (!empty($test['testName'])) {
                        if (isset($test['testDate']) && trim((string) $test['testDate']) !== "") {
                            $data['testDate'] = DateUtility::isoDateFormat($data['testDate'], true);
                        } else {
                            $test['testDate'] = null;
                        }
                        $covid19TestData = [
                            'covid19_id' => $data['covid19SampleId'],
                            'test_name' => ($test['testName'] == 'other') ? $test['testNameOther'] : $test['testName'],
                            'facility_id' => $data['labId'] ?? null,
                            'sample_tested_datetime' => DateUtility::isoDateFormat($test['testDate'], true),
                            'testing_platform' => $test['testingPlatform'] ?? null,
                            'kit_lot_no' => (str_contains((string) $test['testName'], 'RDT')) ? $test['kitLotNo'] : null,
                            'kit_expiry_date' => (str_contains((string) $test['testName'], 'RDT')) ? DateUtility::isoDateFormat($test['kitExpiryDate']) : null,
                            'result' => $test['testResult'],
                        ];
                        $db->insert($testTableName, $covid19TestData);
                        $covid19Data['sample_tested_datetime'] = DateUtility::isoDateFormat($test['testDate'], true);
                    }
                }
            }
        } else {
            $db->where('covid19_id', $data['covid19SampleId']);
            $db->delete($testTableName);
            $covid19Data['sample_tested_datetime'] = null;
        }
        $id = false;
        $covid19Data = MiscUtility::arrayEmptyStringsToNull($covid19Data);
        if (!empty($data['covid19SampleId'])) {
            $db->where('covid19_id', $data['covid19SampleId']);
            $id = $db->update($tableName, $covid19Data);
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

    if ($noOfFailedRecords > 0 && $noOfFailedRecords == iterator_count($input)) {
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
        'trace' => $exc->getTraceAsString(),
    ]);
}


$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], iterator_count($input), 'save-request', 'covid19', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json', null, null, $authToken);

$general->updateResultSyncDateTime('covid19', null, $updatedLabs);


//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
