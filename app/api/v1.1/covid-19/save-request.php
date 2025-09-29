<?php

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
    foreach (array('deviceId', 'osVersion', 'ipAddress') as $header) {
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
        if (isset($data['patientNationality']) && !is_numeric($data['patientNationality'])) {
            $iso = explode("(", (string) $data['patientNationality']);
            if (!empty($iso)) {
                $data['patientNationality'] = trim($iso[0]);
            }
            $data['patientNationality'] = $general->getValueByName($data['patientNationality'], 'iso_name', 'r_countries', 'id');
        }
        $pprovince = explode("##", (string) $data['patientProvince']);
        if (!empty($pprovince)) {
            $data['patientProvince'] = $pprovince[0];
        }

        $data['api'] = "yes";
        $provinceCode = (!empty($data['provinceCode'])) ? $data['provinceCode'] : null;
        $provinceId = (!empty($data['provinceId'])) ? $data['provinceId'] : null;
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

            if (!empty($sQueryWhere)) {
                $sQuery .= " WHERE " . implode(" OR ", $sQueryWhere);
            }

            $rowData = $db->rawQueryOne($sQuery);

            if (!empty($rowData)) {
                if ($rowData['result_status'] == SAMPLE_STATUS\ACCEPTED || $rowData['locked'] == 'yes') {
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
        $status = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
        if ($roleUser['access_type'] != 'testing-lab') {
            $status = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
        }

        if (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "yes") {
            $data['result'] = null;
            $status = SAMPLE_STATUS\REJECTED;
        } elseif (
            isset($globalConfig['covid19_auto_approve_api_results']) &&
            $globalConfig['covid19_auto_approve_api_results'] == "yes" &&
            (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") &&
            (!empty($data['result']))
        ) {
            $status = SAMPLE_STATUS\ACCEPTED;
        } elseif ((isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") && (!empty($data['result']))) {
            $status = SAMPLE_STATUS\PENDING_APPROVAL;
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

        if (!empty($data['arrivalDateTime']) && trim((string) $data['arrivalDateTime']) != "") {
            $data['arrivalDateTime'] = DateUtility::isoDateFormat($data['arrivalDateTime'], true);
        } else {
            $data['arrivalDateTime'] = null;
        }

        if (!empty($data['revisedOn']) && trim((string) $data['revisedOn']) != "") {
            $data['revisedOn'] = DateUtility::isoDateFormat($data['revisedOn'], true);
        } else {
            $data['revisedOn'] = null;
        }

        if (isset($data['approvedOn']) && trim((string) $data['approvedOn']) != "") {
            $data['approvedOn'] = DateUtility::isoDateFormat($data['approvedOn'], true);
        } else {
            $data['approvedOn'] = null;
        }

        if (isset($data['reviewedOn']) && trim((string) $data['reviewedOn']) != "") {
            $data['reviewedOn'] = DateUtility::isoDateFormat($data['reviewedOn'], true);
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
        if (!empty($allChange)) {
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
            'facility_id' => !empty($data['facilityId']) ? $data['facilityId'] : null,
            'investigator_name' => !empty($data['investigatorName']) ? $data['investigatorName'] : null,
            'investigator_phone' => !empty($data['investigatorPhone']) ? $data['investigatorPhone'] : null,
            'investigator_email' => !empty($data['investigatorEmail']) ? $data['investigatorEmail'] : null,
            'clinician_name' => !empty($data['clinicianName']) ? $data['clinicianName'] : null,
            'clinician_phone' => !empty($data['clinicianPhone']) ? $data['clinicianPhone'] : null,
            'clinician_email' => !empty($data['clinicianEmail']) ? $data['clinicianEmail'] : null,
            'test_number' => !empty($data['testNumber']) ? $data['testNumber'] : null,
            'province_id' => !empty($data['provinceId']) ? $data['provinceId'] : null,
            'lab_id' => !empty($data['labId']) ? $data['labId'] : null,
            'testing_point' => !empty($data['testingPoint']) ? $data['testingPoint'] : null,
            'implementing_partner' => !empty($data['implementingPartner']) ? $data['implementingPartner'] : null,
            'source_of_alert' => !empty($data['sourceOfAlertPOE']) ? strtolower(str_replace(" ", "-", (string) $data['sourceOfAlertPOE'])) : null,
            'source_of_alert_other' => (!empty($data['sourceOfAlertPOE']) && $data['sourceOfAlertPOE'] == 'others') ? $data['alertPoeOthers'] : null,
            'funding_source' => !empty($data['fundingSource']) ? $data['fundingSource'] : null,
            'patient_id' => !empty($data['patientId']) ? $data['patientId'] : null,
            'patient_name' => !empty($data['firstName']) ? trim((string) $data['firstName']) : null,
            'patient_surname' => !empty($data['lastName']) ? $data['lastName'] : null,
            'patient_dob' => !empty($data['dob']) ? DateUtility::isoDateFormat($data['dob']) : null,
            'patient_gender' => !empty($data['patientGender']) ? $data['patientGender'] : null,
            'health_insurance_code' => $data['healthInsuranceCode'] ?? null,
            'patient_email' => !empty($data['patientEmail']) ? $data['patientEmail'] : null,
            'is_patient_pregnant' => !empty($data['isPatientPregnant']) ? $data['isPatientPregnant'] : null,
            'patient_age' => !empty($data['patientAge']) ? $data['patientAge'] : null,
            'patient_phone_number' => !empty($data['patientPhoneNumber']) ? $data['patientPhoneNumber'] : null,
            'patient_address' => !empty($data['patientAddress']) ? $data['patientAddress'] : null,
            'patient_province' => !empty($data['patientProvince']) ? $data['patientProvince'] : null,
            'patient_district' => !empty($data['patientDistrict']) ? $data['patientDistrict'] : null,
            'patient_city' => !empty($data['patientCity']) ? $data['patientCity'] : null,
            'patient_zone' => !empty($data['patientZone']) ? $data['patientZone'] : null,
            'patient_occupation' => !empty($data['patientOccupation']) ? $data['patientOccupation'] : null,
            'does_patient_smoke' => !empty($data['doesPatientSmoke']) ? $data['doesPatientSmoke'] : null,
            'patient_nationality' => !empty($data['patientNationality']) ? $data['patientNationality'] : null,
            'patient_passport_number' => !empty($data['patientPassportNumber']) ? $data['patientPassportNumber'] : null,
            'flight_airline' => !empty($data['airline']) ? $data['airline'] : null,
            'flight_seat_no' => !empty($data['seatNo']) ? $data['seatNo'] : null,
            'flight_arrival_datetime' => DateUtility::isoDateFormat($data['arrivalDateTime'] ?? '', true),
            'flight_airport_of_departure' => !empty($data['airportOfDeparture']) ? $data['airportOfDeparture'] : null,
            'flight_transit' => !empty($data['transit']) ? $data['transit'] : null,
            'reason_of_visit' => !empty($data['reasonOfVisit']) ? $data['reasonOfVisit'] : null,
            'is_sample_collected' => !empty($data['isSampleCollected']) ? $data['isSampleCollected'] : null,
            'reason_for_covid19_test' => !empty($data['reasonForCovid19Test']) ? $data['reasonForCovid19Test'] : null,
            'type_of_test_requested' => !empty($data['testTypeRequested']) ? $data['testTypeRequested'] : null,
            'specimen_type' => !empty($data['specimenType']) ? $data['specimenType'] : null,
            'sample_dispatched_datetime' => $data['sampleDispatchedOn'],
            'result_dispatched_datetime' => $data['resultDispatchedOn'],
            'sample_collection_date' => $sampleCollectionDate,
            'health_outcome' => !empty($data['healthOutcome']) ? $data['healthOutcome'] : null,
            'health_outcome_date' => !empty($data['outcomeDate']) ? DateUtility::isoDateFormat($data['outcomeDate']) : null,
            // 'is_sampledata_mortem'                => !empty($data['isSamplePostMortem']) ? $data['isSamplePostMortem'] : null,
            'priority_status' => !empty($data['priorityStatus']) ? $data['priorityStatus'] : null,
            'number_of_days_sick' => !empty($data['numberOfDaysSick']) ? $data['numberOfDaysSick'] : null,
            'suspected_case' => !empty($data['suspectedCase']) ? $data['suspectedCase'] : null,
            'date_of_symptom_onset' => !empty($data['dateOfSymptomOnset']) ? DateUtility::isoDateFormat($data['dateOfSymptomOnset']) : null,
            'date_of_initial_consultation' => !empty($data['dateOfInitialConsultation']) ? DateUtility::isoDateFormat($data['dateOfInitialConsultation']) : null,
            'fever_temp' => !empty($data['feverTemp']) ? $data['feverTemp'] : null,
            'medical_history' => !empty($data['medicalHistory']) ? $data['medicalHistory'] : null,
            'recent_hospitalization' => !empty($data['recentHospitalization']) ? $data['recentHospitalization'] : null,
            'patient_lives_with_children' => !empty($data['patientLivesWithChildren']) ? $data['patientLivesWithChildren'] : null,
            'patient_cares_for_children' => !empty($data['patientCaresForChildren']) ? $data['patientCaresForChildren'] : null,
            'temperature_measurement_method' => !empty($data['temperatureMeasurementMethod']) ? $data['temperatureMeasurementMethod'] : null,
            'respiratory_rate' => !empty($data['respiratoryRate']) ? $data['respiratoryRate'] : null,
            'oxygen_saturation' => !empty($data['oxygenSaturation']) ? $data['oxygenSaturation'] : null,
            'close_contacts' => !empty($data['closeContacts']) ? $data['closeContacts'] : null,
            'contact_with_confirmed_case' => !empty($data['contactWithConfirmedCase']) ? $data['contactWithConfirmedCase'] : null,
            'has_recent_travel_history' => !empty($data['hasRecentTravelHistory']) ? $data['hasRecentTravelHistory'] : null,
            'travel_country_names' => !empty($data['countryName']) ? $data['countryName'] : null,
            'travel_return_date' => !empty($data['returnDate']) ? DateUtility::isoDateFormat($data['returnDate']) : null,
            'sample_received_at_lab_datetime' => !empty($data['sampleReceivedDate']) ? $data['sampleReceivedDate'] : null,
            'sample_condition' => !empty($data['sampleCondition']) ? $data['sampleCondition'] : ($data['specimenQuality'] ?? null),
            'asymptomatic' => !empty($data['asymptomatic']) ? $data['asymptomatic'] : null,
            'lab_technician' => (!empty($data['labTechnician']) && $data['labTechnician'] != '') ? $data['labTechnician'] : $user['user_id'],
            'is_sample_rejected' => !empty($data['isSampleRejected']) ? $data['isSampleRejected'] : null,
            'result' => !empty($data['result']) ? $data['result'] : null,
            'if_have_other_diseases' => (!empty($data['ifOtherDiseases'])) ? $data['ifOtherDiseases'] : null,
            'other_diseases' => (!empty($data['otherDiseases']) && $data['result'] != 'positive') ? $data['otherDiseases'] : null,
            'tested_by' => !empty($data['testedBy']) ? $data['testedBy'] : null,
            'is_result_authorised' => !empty($data['isResultAuthorized']) ? $data['isResultAuthorized'] : null,
            'lab_tech_comments' => !empty($data['approverComments']) ? $data['approverComments'] : null,
            'authorized_by' => !empty($data['authorizedBy']) ? $data['authorizedBy'] : null,
            'authorized_on' => !empty($data['authorizedOn']) ? DateUtility::isoDateFormat($data['authorizedOn']) : null,
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
            'form_attributes' => !empty($formAttributes) ? $db->func($formAttributes) : null
        ];
        if (!empty($rowData)) {
            $covid19Data['last_modified_datetime'] = (!empty($data['updatedOn'])) ? DateUtility::isoDateFormat($data['updatedOn'], true) : DateUtility::getCurrentDateTime();
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
                for ($i = 0; $i < count($syptomDetections); $i++) {

                    $data['symptomId'][$i] = $data['symptomId'][$i] ?? $syptomDetections[$i]['id'];
                    $data['symptomDetected'][$i] = $data['symptomDetected'][$i] ?? $syptomDetections[$i]['symptom'];
                    $data['symptomDetails'][$i] = $data['symptomDetails'][$i] ?? $syptomDetections[$i]['detail'];

                    $symptomData = [];
                    $symptomData["covid19_id"] = $data['covid19SampleId'];
                    $symptomData["symptom_id"] = $data['symptomId'][$i];
                    $symptomData["symptom_detected"] = $data['symptomDetected'][$i] ?? 'no';
                    if (isset($data['covid19PatientSymptomsArray']) && !empty($data['covid19PatientSymptomsArray'])) {
                        $symptomData["symptom_details"] = $syptomDetections[$i]['detail'] ?? null;
                    } else {
                        $symptomData["symptom_details"] = (!empty($data['symptomDetails'][$data['symptomId'][$i]])) ? json_encode($data['symptomDetails'][$data['symptomId'][$i]]) : null;
                    }
                    $db->insert("covid19_patient_symptoms", $symptomData);
                }
            }
        }

        if (isset($data['reasonDetails']) && !empty($data['reasonDetails'])) {
            /* For Rest API service data came as multiple index so rechange and remove unwanted index */
            if (array_key_exists('reason_details', (array)$data['reasonDetails'][0])) {
                $res = [];
                foreach ((array)$data['reasonDetails'] as $row) {
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
            for ($i = 0; $i < count($data['comorbidityDetected']); $i++) {
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
                        if (isset($test['testDate']) && trim((string) $test['testDate']) != "") {
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
                            'kit_lot_no' => (str_contains((string)$test['testName'], 'RDT')) ? $test['kitLotNo'] : null,
                            'kit_expiry_date' => (str_contains((string)$test['testName'], 'RDT')) ? DateUtility::isoDateFormat($test['kitExpiryDate']) : null,
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
                'sampleCode' => $currentSampleData['remoteSampleCode'] ?? $currentSampleData['sampleCode'] ??  null,
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
    LoggerUtility::log('error', $exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'trace' => $exc->getTraceAsString(),
    ]);
}


$payload = JsonUtility::encodeUtf8Json($payload);
$general->addApiTracking($transactionId, $user['user_id'], iterator_count($input), 'save-request', 'covid19', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');

$general->updateResultSyncDateTime('covid19', null, $updatedLabs);


//echo $payload
echo ApiService::generateJsonResponse($payload, $request);
