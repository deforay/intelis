<?php

///  if you change anyting in this file make sure Api file for covid 19 update also
// Path   /vlsm/api/covid-19/v1/update-request.php

use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\PatientsService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);


/** @var PatientsService $patientsService */
$patientsService = ContainerRegistry::get(PatientsService::class);


$tableName = "form_covid19";
$tableName1 = "activity_log";
$testTableName = 'covid19_tests';

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);


try {
	$sarr = $general->getSystemConfig();
	$instanceId = '';
	if (isset($_SESSION['instanceId'])) {
		$instanceId = $_SESSION['instanceId'];
	}

	if (isset($_POST['sampleCollectionDate']) && trim((string) $_POST['sampleCollectionDate']) != "") {
		$_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($_POST['sampleCollectionDate'], true);
	} else {
		$_POST['sampleCollectionDate'] = null;
	}

	if (isset($_POST['sampleDispatchedDate']) && trim((string) $_POST['sampleDispatchedDate']) != "") {
		$_POST['sampleDispatchedDate'] = DateUtility::isoDateFormat($_POST['sampleDispatchedDate'], true);
	} else {
		$_POST['sampleDispatchedDate'] = null;
	}

	//Set sample received date
	if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) != "") {
		$_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($_POST['sampleReceivedDate'], true);
	} else {
		$_POST['sampleReceivedDate'] = null;
	}

	if (isset($_POST['sampleTestedDateTime']) && trim((string) $_POST['sampleTestedDateTime']) != "") {
		$_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($_POST['sampleTestedDateTime'], true);
	} else {
		$_POST['sampleTestedDateTime'] = null;
	}


	if (isset($_POST['arrivalDateTime']) && trim((string) $_POST['arrivalDateTime']) != "") {
		$_POST['arrivalDateTime'] = DateUtility::isoDateFormat($_POST['arrivalDateTime'], true);
	} else {
		$_POST['arrivalDateTime'] = null;
	}


	if ($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site') {
		$sampleCode = 'remote_sample_code';
		$sampleCodeKey = 'remote_sample_code_key';
	} else {
		$sampleCode = 'sample_code';
		$sampleCodeKey = 'sample_code_key';
	}

	if (!empty($_POST['newRejectionReason'])) {
		$rejectionReasonQuery = "SELECT rejection_reason_id
					FROM r_covid19_sample_rejection_reasons
					WHERE rejection_reason_name like ?";
		$rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
		if (empty($rejectionResult)) {
			$data = array(
				'rejection_reason_name' => $_POST['newRejectionReason'],
				'rejection_type' => 'general',
				'rejection_reason_status' => 'active',
				'updated_datetime' => DateUtility::getCurrentDateTime()
			);
			$id = $db->insert('r_covid19_sample_rejection_reasons', $data);
			$_POST['sampleRejectionReason'] = $id;
		} else {
			$_POST['sampleRejectionReason'] = $rejectionResult['rejection_reason_id'];
		}
	}



	if ($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site') {
		$status = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
	}

	if (!empty($_POST['oldStatus'])) {
		$status = $_POST['oldStatus'];
	}

	if ($general->isLISInstance() && $_POST['oldStatus'] == SAMPLE_STATUS\RECEIVED_AT_CLINIC) {
		$status = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
	}

	$resultSentToSource = null;

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['result'] = null;
		$status = SAMPLE_STATUS\REJECTED;
		$resultSentToSource = 'pending';
	}

	if (!empty($_POST['result'])) {
		$resultSentToSource = 'pending';
	}


	if ($general->isSTSInstance() && $_POST['oldStatus'] == SAMPLE_STATUS\RECEIVED_AT_CLINIC) {
		$_POST['status'] = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
	} elseif ($general->isLISInstance() && $_POST['oldStatus'] == SAMPLE_STATUS\RECEIVED_AT_CLINIC) {
		$_POST['status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
	}
	if (isset($_POST['status']) && $_POST['status'] == '') {
		$_POST['status'] = $_POST['oldStatus'];
	}

	$_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true);
	$_POST['approvedOn'] = DateUtility::isoDateFormat($_POST['approvedOn'] ?? '', true);

	//Update patient Information in Patients Table
	//$systemPatientCode = $patientsService->savePatient($_POST, 'form_covid19');


	$covid19Data = array(
		'external_sample_code' => !empty($_POST['externalSampleCode']) ? $_POST['externalSampleCode'] : null,
		'facility_id' => !empty($_POST['facilityId']) ? $_POST['facilityId'] : null,
		'investigator_name' => !empty($_POST['investigatorName']) ? $_POST['investigatorName'] : null,
		'investigator_phone' => !empty($_POST['investigatorPhone']) ? $_POST['investigatorPhone'] : null,
		'investigator_email' => !empty($_POST['investigatorEmail']) ? $_POST['investigatorEmail'] : null,
		'clinician_name' => !empty($_POST['clinicianName']) ? $_POST['clinicianName'] : null,
		'clinician_phone' => !empty($_POST['clinicianPhone']) ? $_POST['clinicianPhone'] : null,
		'clinician_email' => !empty($_POST['clinicianEmail']) ? $_POST['clinicianEmail'] : null,
		'test_number' => !empty($_POST['testNumber']) ? $_POST['testNumber'] : null,
		'province_id' => !empty($_POST['provinceId']) ? $_POST['provinceId'] : null,
		'lab_id' => !empty($_POST['labId']) ? $_POST['labId'] : null,
		//'system_patient_code' => $systemPatientCode,
		'testing_point' => !empty($_POST['testingPoint']) ? $_POST['testingPoint'] : null,
		'funding_source' => (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) != '') ? base64_decode((string) $_POST['fundingSource']) : null,
		'implementing_partner' => (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) != '') ? base64_decode((string) $_POST['implementingPartner']) : null,
		'source_of_alert' => !empty($_POST['sourceOfAlertPOE']) ? $_POST['sourceOfAlertPOE'] : null,
		'source_of_alert_other' => (!empty($_POST['sourceOfAlertPOE']) && $_POST['sourceOfAlertPOE'] == 'others') ? $_POST['alertPoeOthers'] : null,
		'patient_id' => !empty($_POST['patientId']) ? $_POST['patientId'] : null,
		'patient_name' => !empty($_POST['firstName']) ? $_POST['firstName'] : null,
		'patient_surname' => !empty($_POST['lastName']) ? $_POST['lastName'] : null,
		'patient_dob' => !empty($_POST['dob']) ? DateUtility::isoDateFormat($_POST['dob']) : null,
		'patient_gender' => !empty($_POST['patientGender']) ? $_POST['patientGender'] : null,
		'health_insurance_code' => $_POST['healthInsuranceCode'] ?? null,
		'is_patient_pregnant' => !empty($_POST['isPatientPregnant']) ? $_POST['isPatientPregnant'] : null,
		'patient_age' => !empty($_POST['ageInYears']) ? $_POST['ageInYears'] : null,
		'patient_phone_number' => !empty($_POST['patientPhoneNumber']) ? $_POST['patientPhoneNumber'] : null,
		'patient_email' => !empty($_POST['patientEmail']) ? $_POST['patientEmail'] : null,
		'patient_address' => !empty($_POST['patientAddress']) ? $_POST['patientAddress'] : null,
		'patient_province' => !empty($_POST['patientProvince']) ? $_POST['patientProvince'] : null,
		'patient_district' => !empty($_POST['patientDistrict']) ? $_POST['patientDistrict'] : null,
		'patient_city' => !empty($_POST['patientCity']) ? $_POST['patientCity'] : null,
		'patient_zone' => !empty($_POST['patientZone']) ? $_POST['patientZone'] : null,
		'patient_occupation' => !empty($_POST['patientOccupation']) ? $_POST['patientOccupation'] : null,
		'does_patient_smoke' => !empty($_POST['doesPatientSmoke']) ? $_POST['doesPatientSmoke'] : null,
		'patient_nationality' => !empty($_POST['patientNationality']) ? $_POST['patientNationality'] : null,
		'patient_passport_number' => !empty($_POST['patientPassportNumber']) ? $_POST['patientPassportNumber'] : null,
		'vaccination_status' => !empty($_POST['vaccinationStatus']) ? $_POST['vaccinationStatus'] : null,
		'vaccination_dosage' => !empty($_POST['vaccinationDosage']) ? $_POST['vaccinationDosage'] : null,
		'vaccination_type' => !empty($_POST['vaccinationType']) ? $_POST['vaccinationType'] : null,
		'vaccination_type_other' => !empty($_POST['vaccinationTypeOther']) ? $_POST['vaccinationTypeOther'] : null,
		'flight_airline' => !empty($_POST['airline']) ? $_POST['airline'] : null,
		'flight_seat_no' => !empty($_POST['seatNo']) ? $_POST['seatNo'] : null,
		'flight_arrival_datetime' => !empty($_POST['arrivalDateTime']) ? $_POST['arrivalDateTime'] : null,
		'flight_airport_of_departure' => !empty($_POST['airportOfDeparture']) ? $_POST['airportOfDeparture'] : null,
		'flight_transit' => !empty($_POST['transit']) ? $_POST['transit'] : null,
		'reason_of_visit' => !empty($_POST['reasonOfVisit']) ? $_POST['reasonOfVisit'] : null,
		'is_sample_collected' => !empty($_POST['isSampleCollected']) ? $_POST['isSampleCollected'] : null,
		'reason_for_covid19_test' => !empty($_POST['reasonForCovid19Test']) ? $_POST['reasonForCovid19Test'] : null,
		'type_of_test_requested' => !empty($_POST['testTypeRequested']) ? $_POST['testTypeRequested'] : null,
		'specimen_type' => !empty($_POST['specimenType']) ? $_POST['specimenType'] : null,
		'specimen_taken_before_antibiotics' => !empty($_POST['specimenTakenBeforeAntibiotics']) ? $_POST['specimenTakenBeforeAntibiotics'] : null,
		'sample_collection_date' => !empty($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
		'sample_dispatched_datetime' => !empty($_POST['sampleDispatchedDate']) ? $_POST['sampleDispatchedDate'] : null,
		'health_outcome' => !empty($_POST['healthOutcome']) ? $_POST['healthOutcome'] : null,
		'health_outcome_date' => !empty($_POST['outcomeDate']) ? DateUtility::isoDateFormat($_POST['outcomeDate']) : null,
		'is_sample_post_mortem' => !empty($_POST['isSamplePostMortem']) ? $_POST['isSamplePostMortem'] : null,
		'priority_status' => !empty($_POST['priorityStatus']) ? $_POST['priorityStatus'] : null,
		'number_of_days_sick' => !empty($_POST['numberOfDaysSick']) ? $_POST['numberOfDaysSick'] : null,
		'suspected_case' => !empty($_POST['suspectedCase']) ? $_POST['suspectedCase'] : null,
		'asymptomatic' => !empty($_POST['asymptomatic']) ? $_POST['asymptomatic'] : null,
		'date_of_symptom_onset' => !empty($_POST['dateOfSymptomOnset']) ? DateUtility::isoDateFormat($_POST['dateOfSymptomOnset']) : null,
		'date_of_initial_consultation' => !empty($_POST['dateOfInitialConsultation']) ? DateUtility::isoDateFormat($_POST['dateOfInitialConsultation']) : null,
		'fever_temp' => !empty($_POST['feverTemp']) ? $_POST['feverTemp'] : null,
		'medical_history' => !empty($_POST['medicalHistory']) ? $_POST['medicalHistory'] : null,
		'recent_hospitalization' => !empty($_POST['recentHospitalization']) ? $_POST['recentHospitalization'] : null,
		'patient_lives_with_children' => !empty($_POST['patientLivesWithChildren']) ? $_POST['patientLivesWithChildren'] : null,
		'patient_cares_for_children' => !empty($_POST['patientCaresForChildren']) ? $_POST['patientCaresForChildren'] : null,
		'temperature_measurement_method' => !empty($_POST['temperatureMeasurementMethod']) ? $_POST['temperatureMeasurementMethod'] : null,
		'respiratory_rate' => !empty($_POST['respiratoryRate']) ? $_POST['respiratoryRate'] : null,
		'oxygen_saturation' => !empty($_POST['oxygenSaturation']) ? $_POST['oxygenSaturation'] : null,
		'close_contacts' => !empty($_POST['closeContacts']) ? $_POST['closeContacts'] : null,
		'contact_with_confirmed_case' => !empty($_POST['contactWithConfirmedCase']) ? $_POST['contactWithConfirmedCase'] : null,
		'has_recent_travel_history' => !empty($_POST['hasRecentTravelHistory']) ? $_POST['hasRecentTravelHistory'] : null,
		'travel_country_names' => !empty($_POST['countryName']) ? $_POST['countryName'] : null,
		'travel_return_date' => !empty($_POST['returnDate']) ? DateUtility::isoDateFormat($_POST['returnDate']) : null,
		'sample_received_at_lab_datetime' => !empty($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
		'sample_condition' => !empty($_POST['sampleCondition']) ? $_POST['sampleCondition'] : (!empty($_POST['specimenQuality']) ? $_POST['specimenQuality'] : null),
		// 'lab_technician'                       => (!empty($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  $_SESSION['userId'],
		'is_sample_rejected' => !empty($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,
		'result' => !empty($_POST['result']) ? $_POST['result'] : null,
		'result_sent_to_source' => $resultSentToSource,
		'if_have_other_diseases' => (!empty($_POST['ifOtherDiseases'])) ? $_POST['ifOtherDiseases'] : null,
		'other_diseases' => (!empty($_POST['otherDiseases']) && $_POST['result'] != 'positive') ? $_POST['otherDiseases'] : null,
		'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
		'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] : null,
		'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] : null,
		'tested_by' => !empty($_POST['testedBy']) ? $_POST['testedBy'] : null,
		'is_result_authorised' => !empty($_POST['isResultAuthorized']) ? $_POST['isResultAuthorized'] : null,
		'authorized_by' => !empty($_POST['authorizedBy']) ? $_POST['authorizedBy'] : null,
		'authorized_on' => !empty($_POST['authorizedOn']) ? DateUtility::isoDateFormat($_POST['authorizedOn']) : null,
		'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
		'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
		'rejection_on' => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
		//'reason_for_changing' => (!empty($_POST['reasonForChanging'])) ? $_POST['reasonForChanging'] : null,
		'result_status' => $status,
		'data_sync' => 0,
		'reason_for_sample_rejection' => (!empty($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
		'recommended_corrective_action' => (isset($_POST['correctiveAction']) && trim((string) $_POST['correctiveAction']) != '') ? $_POST['correctiveAction'] : null,
		
		'last_modified_by' => $_SESSION['userId'],
		'last_modified_datetime' => DateUtility::getCurrentDateTime()
	);

	$db->where('covid19_id', $_POST['covid19SampleId']);
	$getPrevResult = $db->getOne('form_covid19');
	if ($getPrevResult['result'] != "" && $getPrevResult['result'] != $_POST['result']) {
		$covid19Data['result_modified'] = "yes";

		$reasonForChangesArr = array(
			'user' => $_SESSION['userId'] ?? $_POST['userId'],
			'dateOfChange' => DateUtility::getCurrentDateTime(),
			'previousResult' => $getPrevResult['result'],
			'previousResultStatus' => $getPrevResult['result_status'],
			'reasonForChange' => $_POST['reasonForChanging']
		);

		$reasonForChanges = json_encode($reasonForChangesArr);
	} else {
		$covid19Data['result_modified'] = "no";
	}

	$covid19Data['reason_for_changing'] = $reasonForChanges ?? null;


	if (!empty($_POST['labId'])) {
		$facility = $facilitiesService->getFacilityById($_POST['labId']);
		if (isset($facility['contact_person']) && $facility['contact_person'] != "") {
			$covid19Data['lab_manager'] = $facility['contact_person'];
		}
	}

	$covid19Data['last_modified_by'] = $_SESSION['userId'];
	$covid19Data['lab_technician'] = (!empty($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] : $_SESSION['userId'];

	if (isset($_POST['deletedRow']) && trim((string) $_POST['deletedRow']) != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
		$deleteRows = explode(',', (string) $_POST['deletedRow']);
		foreach ($deleteRows as $delete) {
			$db->where('test_id', base64_decode($delete));
			$db->delete($testTableName);
		}
	}
	$db->where('covid19_id', $_POST['covid19SampleId']);
	$sid = $db->delete("covid19_patient_symptoms");
	//if (isset($_POST['asymptomatic']) && $_POST['asymptomatic'] != "yes") {
	if (!empty($_POST['symptomDetected']) || (!empty($_POST['symptom']))) {
		for ($i = 0; $i < count($_POST['symptomDetected']); $i++) {
			$symptomData = [];
			$symptomData["covid19_id"] = $_POST['covid19SampleId'];
			$symptomData["symptom_id"] = $_POST['symptomId'][$i];
			$symptomData["symptom_detected"] = $_POST['symptomDetected'][$i];
			$symptomData["symptom_details"] = (!empty($_POST['symptomDetails'][$_POST['symptomId'][$i]])) ? json_encode($_POST['symptomDetails'][$_POST['symptomId'][$i]]) : null;
			//var_dump($symptomData);
			$db->insert("covid19_patient_symptoms", $symptomData);
		}
	}
	//}

	$db->where('covid19_id', $_POST['covid19SampleId']);
	$db->delete("covid19_reasons_for_testing");
	if (!empty($_POST['reasonDetails'])) {
		$reasonData = [];
		$reasonData["covid19_id"] = $_POST['covid19SampleId'];
		$reasonData["reasons_id"] = $_POST['reasonForCovid19Test'];
		$reasonData["reasons_detected"] = "yes";
		$reasonData["reason_details"] = json_encode($_POST['reasonDetails']);
		$db->insert("covid19_reasons_for_testing", $reasonData);
	} else {
		if (!empty($_POST['reasonForCovid19Test'])) {
			$reasonData = [];
			$reasonData["covid19_id"] = $_POST['covid19SampleId'];
			$reasonData["reasons_id"] = $_POST['reasonForCovid19Test'];
			$reasonData["reasons_detected"] = "yes";
			$reasonData["reason_details"] = null;
			$db->insert("covid19_reasons_for_testing", $reasonData);
		}
	}

	$db->where('covid19_id', $_POST['covid19SampleId']);
	$pid = $db->delete("covid19_patient_comorbidities");
	if (!empty($_POST['comorbidityDetected'])) {

		for ($i = 0; $i < count($_POST['comorbidityDetected']); $i++) {
			$comorbidityData = [];
			$comorbidityData["covid19_id"] = $_POST['covid19SampleId'];
			$comorbidityData["comorbidity_id"] = $_POST['comorbidityId'][$i];
			$comorbidityData["comorbidity_detected"] = $_POST['comorbidityDetected'][$i];
			$db->insert("covid19_patient_comorbidities", $comorbidityData);
		}
	}


	if (isset($_POST['covid19SampleId']) && $_POST['covid19SampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {

		if (!empty($_POST['testName'])) {
			foreach ($_POST['testName'] as $testKey => $testName) {
				if (trim((string) $_POST['testName'][$testKey]) != "") {
					$testingPlatform = null;
					$instrumentId = null;
					if (isset($_POST['testingPlatform'][$testKey]) && trim((string) $_POST['testingPlatform'][$testKey]) != '') {
						$platForm = explode("##", (string) $_POST['testingPlatform'][$testKey]);
						$testingPlatform = $platForm[0];
						$instrumentId = $platForm[1];
					}

					$covid19TestData = array(
						'covid19_id' => $_POST['covid19SampleId'],
						'test_name' => ($testName == 'other') ? $_POST['testNameOther'][$testKey] : $testName,
						'facility_id' => $_POST['labId'] ?? null,
						'sample_tested_datetime' => DateUtility::isoDateFormat($_POST['testDate'][$testKey] ?? '', true),
						'testing_platform' => $testingPlatform ?? null,
						'instrument_id' => $instrumentId ?? null,
						'kit_lot_no' => (str_contains((string)$testName, 'RDT')) ? $_POST['lotNo'][$testKey] : null,
						'kit_expiry_date' => (str_contains((string)$testName, 'RDT')) ? DateUtility::isoDateFormat($_POST['expDate'][$testKey]) : null,
						'result' => $_POST['testResult'][$testKey],
					);
					if (isset($_POST['testId'][$testKey]) && $_POST['testId'][$testKey] != '') {
						$db->where('test_id', base64_decode((string) $_POST['testId'][$testKey]));
						$db->update($testTableName, $covid19TestData);
					} else {
						$db->insert($testTableName, $covid19TestData);
					}
					$covid19Data['sample_tested_datetime'] = DateUtility::isoDateFormat($_POST['testDate'][$testKey] ?? '', true);
					$covid19Data['covid19_test_platform'] = $_POST['testingPlatform'][$testKey];
					$covid19Data['covid19_test_name'] = $_POST['testName'][$testKey];
				}
			}
		}
	} else {
		$db->where('covid19_id', $_POST['covid19SampleId']);
		$db->delete($testTableName);
		$covid19Data['sample_tested_datetime'] = null;
	}
	//echo "<pre>";print_r($covid19Data);die;

	$covid19Data['is_encrypted'] = 'no';
	if (isset($_POST['encryptPII']) && $_POST['encryptPII'] == 'yes') {
		$key = (string) $general->getGlobalConfig('key');
		$encryptedPatientId = $general->crypto('encrypt', $covid19Data['patient_id'], $key);
		$encryptedPatientName = $general->crypto('encrypt', $covid19Data['patient_name'], $key);
		$encryptedPatientSurName = $general->crypto('encrypt', $covid19Data['patient_surname'], $key);

		$covid19Data['patient_id'] = $encryptedPatientId;
		$covid19Data['patient_name'] = $encryptedPatientName;
		$covid19Data['patient_surname'] = $encryptedPatientSurName;
		$covid19Data['is_encrypted'] = 'yes';
	}

	$id = 0;
	if (isset($_POST['covid19SampleId']) && $_POST['covid19SampleId'] != '') {
		$db->where('covid19_id', $_POST['covid19SampleId']);
		$id = $db->update($tableName, $covid19Data);
		error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
	}

	if ($id > 0 || $sid > 0 || $pid > 0) {
		$_SESSION['alertMsg'] = _translate("Covid-19 request updated successfully");
		//Add event log
		$eventType = 'update-covid-19-request';
		$action = $_SESSION['userName'] . ' updated Covid-19 request with the sample id  ' . $_POST['sampleCode'] . ' and patientId ' . $_POST['patientId'];
		$resource = 'covid-19-edit-request';

		$general->activityLog($eventType, $action, $resource);
	} else {
		$_SESSION['alertMsg'] = _translate("Please try again later");
	}
	error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
	header("Location:/covid-19/requests/covid-19-requests.php");
} catch (Exception $exc) {
	throw new SystemException($exc->getMessage(), $exc->getCode(), $exc);
}
