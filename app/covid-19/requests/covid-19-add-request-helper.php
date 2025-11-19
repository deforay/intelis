<?php


use Laminas\Diactoros\ServerRequest;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\REJECTED;
use App\Services\ApiService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\PatientsService;
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

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

$tableName = "form_covid19";
$tableName1 = "activity_log";
$testTableName = 'covid19_tests';

try {

	$instanceId = '';
	if (!empty($_SESSION['instanceId'])) {
		$instanceId = $_SESSION['instanceId'];
	}

	if (empty($instanceId) && $_POST['instanceId']) {
		$instanceId = $_POST['instanceId'];
	}
	if (!empty($_POST['sampleCollectionDate']) && trim((string) $_POST['sampleCollectionDate']) !== "") {
		$sampleCollectionDate = explode(" ", (string) $_POST['sampleCollectionDate']);
		$_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
	} else {
		$_POST['sampleCollectionDate'] = null;
	}

	if (isset($_POST['sampleDispatchedDate']) && trim((string) $_POST['sampleDispatchedDate']) !== "") {
		$sampleDispatchedDate = explode(" ", (string) $_POST['sampleDispatchedDate']);
		$_POST['sampleDispatchedDate'] = DateUtility::isoDateFormat($sampleDispatchedDate[0]) . " " . $sampleDispatchedDate[1];
	} else {
		$_POST['sampleDispatchedDate'] = null;
	}

	//Set sample received date
	if (!empty($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) !== "") {
		$sampleReceivedDate = explode(" ", (string) $_POST['sampleReceivedDate']);
		$_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
	} else {
		$_POST['sampleReceivedDate'] = null;
	}
	if (!empty($_POST['sampleTestedDateTime']) && trim((string) $_POST['sampleTestedDateTime']) !== "") {
		$sampleTestedDate = explode(" ", (string) $_POST['sampleTestedDateTime']);
		$_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
	} else {
		$_POST['sampleTestedDateTime'] = null;
	}

	if (!empty($_POST['arrivalDateTime']) && trim((string) $_POST['arrivalDateTime']) !== "") {
		$arrivalDate = explode(" ", (string) $_POST['arrivalDateTime']);
		$_POST['arrivalDateTime'] = DateUtility::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
	} else {
		$_POST['arrivalDateTime'] = null;
	}

	if (!empty($_POST['newRejectionReason'])) {
		$rejectionReasonQuery = "SELECT rejection_reason_id
					FROM r_covid19_sample_rejection_reasons
					WHERE rejection_reason_name like ?";
		$rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
		if (empty($rejectionResult)) {
			$data = ['rejection_reason_name' => $_POST['newRejectionReason'], 'rejection_type' => 'general', 'rejection_reason_status' => 'active', 'updated_datetime' => DateUtility::getCurrentDateTime()];
			$id = $db->insert('r_covid19_sample_rejection_reasons', $data);
			$_POST['sampleRejectionReason'] = $id;
		} else {
			$_POST['sampleRejectionReason'] = $rejectionResult['rejection_reason_id'];
		}
	}


	if (in_array(trim((string) $_POST['sampleCode']), ['', '0'], true)) {
		$_POST['sampleCode'] = null;
	}

	if ($general->isSTSInstance()) {
		$sampleCode = 'remote_sample_code';
		$sampleCodeKey = 'remote_sample_code_key';
	} else {
		$sampleCode = 'sample_code';
		$sampleCodeKey = 'sample_code_key';
	}

	$status = RECEIVED_AT_TESTING_LAB;
	if ($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site') {
		$status = RECEIVED_AT_CLINIC;
	}

	$resultSentToSource = null;

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['result'] = null;
		$status = REJECTED;
		$resultSentToSource = 'pending';
	}
	if (!empty($_POST['dob'])) {
		$_POST['dob'] = DateUtility::isoDateFormat($_POST['dob']);
	}

	if (!empty($_POST['result'])) {
		$resultSentToSource = 'pending';
	}

	$_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true);
	$_POST['approvedOn'] = DateUtility::isoDateFormat($_POST['approvedOn'] ?? '', true);

	if (!empty($_POST['patientProvince'])) {
		$pprovince = explode("##", (string) $_POST['patientProvince']);
		if ($pprovince !== []) {
			$_POST['patientProvince'] = $pprovince[0];
		}
	}

	// Update patient Information in Patients Table
	// $patientsService->savePatient($_POST, 'form_covid19');

	// $systemGeneratedCode = $patientsService->getSystemPatientId($_POST['patientId'], $_POST['patientGender'], DateUtility::isoDateFormat($_POST['dob'] ?? ''));

	$covid19Data = [
		'vlsm_instance_id' => $instanceId,
		'vlsm_country_id' => $_POST['formId'],
		'external_sample_code' => empty($_POST['externalSampleCode']) ? null : $_POST['externalSampleCode'],
		'facility_id' => empty($_POST['facilityId']) ? null : $_POST['facilityId'],
		//'system_patient_code' => $systemGeneratedCode,
		'investigator_name' => empty($_POST['investigatorName']) ? null : $_POST['investigatorName'],
		'investigator_phone' => empty($_POST['investigatorPhone']) ? null : $_POST['investigatorPhone'],
		'investigator_email' => empty($_POST['investigatorEmail']) ? null : $_POST['investigatorEmail'],
		'clinician_name' => empty($_POST['clinicianName']) ? null : $_POST['clinicianName'],
		'clinician_phone' => empty($_POST['clinicianPhone']) ? null : $_POST['clinicianPhone'],
		'clinician_email' => empty($_POST['clinicianEmail']) ? null : $_POST['clinicianEmail'],
		'test_number' => empty($_POST['testNumber']) ? null : $_POST['testNumber'],
		'province_id' => empty($_POST['provinceId']) ? null : $_POST['provinceId'],
		'lab_id' => empty($_POST['labId']) ? null : $_POST['labId'],
		'testing_point' => empty($_POST['testingPoint']) ? null : $_POST['testingPoint'],
		'implementing_partner' => (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) !== '') ? base64_decode((string) $_POST['implementingPartner']) : null,
		'source_of_alert' => empty($_POST['sourceOfAlertPOE']) ? null : $_POST['sourceOfAlertPOE'],
		'source_of_alert_other' => (!empty($_POST['sourceOfAlertPOE']) && $_POST['sourceOfAlertPOE'] == 'others') ? $_POST['alertPoeOthers'] : null,
		'funding_source' => (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) !== '') ? base64_decode((string) $_POST['fundingSource']) : null,
		'patient_id' => empty($_POST['patientId']) ? null : $_POST['patientId'],
		'patient_name' => empty($_POST['firstName']) ? null : $_POST['firstName'],
		'patient_surname' => empty($_POST['lastName']) ? null : $_POST['lastName'],
		'patient_dob' => empty($_POST['dob']) ? null : $_POST['dob'],
		'patient_gender' => empty($_POST['patientGender']) ? null : $_POST['patientGender'],
		'health_insurance_code' => $_POST['healthInsuranceCode'] ?? null,
		'is_patient_pregnant' => empty($_POST['isPatientPregnant']) ? null : $_POST['isPatientPregnant'],
		'patient_age' => empty($_POST['ageInYears']) ? null : $_POST['ageInYears'],
		'patient_phone_number' => empty($_POST['patientPhoneNumber']) ? null : $_POST['patientPhoneNumber'],
		'patient_email' => empty($_POST['patientEmail']) ? null : $_POST['patientEmail'],
		'patient_address' => empty($_POST['patientAddress']) ? null : $_POST['patientAddress'],
		'patient_province' => empty($_POST['patientProvince']) ? null : $_POST['patientProvince'],
		'patient_district' => empty($_POST['patientDistrict']) ? null : $_POST['patientDistrict'],
		'patient_city' => empty($_POST['patientCity']) ? null : $_POST['patientCity'],
		'patient_zone' => empty($_POST['patientZone']) ? null : $_POST['patientZone'],
		'patient_occupation' => empty($_POST['patientOccupation']) ? null : $_POST['patientOccupation'],
		'does_patient_smoke' => empty($_POST['doesPatientSmoke']) ? null : $_POST['doesPatientSmoke'],
		'patient_nationality' => empty($_POST['patientNationality']) ? null : $_POST['patientNationality'],
		'patient_passport_number' => empty($_POST['patientPassportNumber']) ? null : $_POST['patientPassportNumber'],
		'vaccination_status' => empty($_POST['vaccinationStatus']) ? null : $_POST['vaccinationStatus'],
		'vaccination_dosage' => empty($_POST['vaccinationDosage']) ? null : $_POST['vaccinationDosage'],
		'vaccination_type' => empty($_POST['vaccinationType']) ? null : $_POST['vaccinationType'],
		'vaccination_type_other' => empty($_POST['vaccinationTypeOther']) ? null : $_POST['vaccinationTypeOther'],
		'flight_airline' => empty($_POST['airline']) ? null : $_POST['airline'],
		'flight_seat_no' => empty($_POST['seatNo']) ? null : $_POST['seatNo'],
		'flight_arrival_datetime' => empty($_POST['arrivalDateTime']) ? null : $_POST['arrivalDateTime'],
		'flight_airport_of_departure' => empty($_POST['airportOfDeparture']) ? null : $_POST['airportOfDeparture'],
		'flight_transit' => empty($_POST['transit']) ? null : $_POST['transit'],
		'reason_of_visit' => empty($_POST['reasonOfVisit']) ? null : $_POST['reasonOfVisit'],
		'is_sample_collected' => empty($_POST['isSampleCollected']) ? null : $_POST['isSampleCollected'],
		'reason_for_covid19_test' => empty($_POST['reasonForCovid19Test']) ? null : $_POST['reasonForCovid19Test'],
		'type_of_test_requested' => empty($_POST['testTypeRequested']) ? null : $_POST['testTypeRequested'],
		'specimen_type' => empty($_POST['specimenType']) ? null : $_POST['specimenType'],
		'specimen_taken_before_antibiotics' => empty($_POST['specimenTakenBeforeAntibiotics']) ? null : $_POST['specimenTakenBeforeAntibiotics'],
		'sample_collection_date' => empty($_POST['sampleCollectionDate']) ? null : $_POST['sampleCollectionDate'],
		'sample_dispatched_datetime' => empty($_POST['sampleDispatchedDate']) ? null : $_POST['sampleDispatchedDate'],
		'health_outcome' => empty($_POST['healthOutcome']) ? null : $_POST['healthOutcome'],
		'health_outcome_date' => empty($_POST['outcomeDate']) ? null : DateUtility::isoDateFormat($_POST['outcomeDate']),
		'is_sample_post_mortem' => empty($_POST['isSamplePostMortem']) ? null : $_POST['isSamplePostMortem'],
		'priority_status' => empty($_POST['priorityStatus']) ? null : $_POST['priorityStatus'],
		'number_of_days_sick' => empty($_POST['numberOfDaysSick']) ? null : $_POST['numberOfDaysSick'],
		'suspected_case' => empty($_POST['suspectedCase']) ? null : $_POST['suspectedCase'],
		'asymptomatic' => empty($_POST['asymptomatic']) ? null : $_POST['asymptomatic'],
		'date_of_symptom_onset' => empty($_POST['dateOfSymptomOnset']) ? null : DateUtility::isoDateFormat($_POST['dateOfSymptomOnset']),
		'date_of_initial_consultation' => empty($_POST['dateOfInitialConsultation']) ? null : DateUtility::isoDateFormat($_POST['dateOfInitialConsultation']),
		'fever_temp' => empty($_POST['feverTemp']) ? null : $_POST['feverTemp'],
		'medical_history' => empty($_POST['medicalHistory']) ? null : $_POST['medicalHistory'],
		'recent_hospitalization' => empty($_POST['recentHospitalization']) ? null : $_POST['recentHospitalization'],
		'patient_lives_with_children' => empty($_POST['patientLivesWithChildren']) ? null : $_POST['patientLivesWithChildren'],
		'patient_cares_for_children' => empty($_POST['patientCaresForChildren']) ? null : $_POST['patientCaresForChildren'],
		'temperature_measurement_method' => empty($_POST['temperatureMeasurementMethod']) ? null : $_POST['temperatureMeasurementMethod'],
		'respiratory_rate' => empty($_POST['respiratoryRate']) ? null : $_POST['respiratoryRate'],
		'oxygen_saturation' => empty($_POST['oxygenSaturation']) ? null : $_POST['oxygenSaturation'],
		'close_contacts' => empty($_POST['closeContacts']) ? null : $_POST['closeContacts'],
		'contact_with_confirmed_case' => empty($_POST['contactWithConfirmedCase']) ? null : $_POST['contactWithConfirmedCase'],
		'has_recent_travel_history' => empty($_POST['hasRecentTravelHistory']) ? null : $_POST['hasRecentTravelHistory'],
		'travel_country_names' => empty($_POST['countryName']) ? null : $_POST['countryName'],
		'travel_return_date' => empty($_POST['returnDate']) ? null : DateUtility::isoDateFormat($_POST['returnDate']),
		'sample_received_at_lab_datetime' => empty($_POST['sampleReceivedDate']) ? null : $_POST['sampleReceivedDate'],
		'sample_condition' => empty($_POST['sampleCondition']) ? $_POST['specimenQuality'] ?? null : ($_POST['sampleCondition']),
		'is_sample_rejected' => empty($_POST['isSampleRejected']) ? null : $_POST['isSampleRejected'],
		'result' => empty($_POST['result']) ? null : $_POST['result'],
		'result_sent_to_source' => $resultSentToSource,
		'if_have_other_diseases' => (empty($_POST['ifOtherDiseases'])) ? null : $_POST['ifOtherDiseases'],
		'other_diseases' => (!empty($_POST['otherDiseases']) && $_POST['result'] != 'positive') ? $_POST['otherDiseases'] : null,
		'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
		'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] : null,
		'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] : null,
		'tested_by' => empty($_POST['testedBy']) ? null : $_POST['testedBy'],
		'is_result_authorised' => empty($_POST['isResultAuthorized']) ? null : $_POST['isResultAuthorized'],
		'authorized_by' => empty($_POST['authorizedBy']) ? null : $_POST['authorizedBy'],
		'authorized_on' => empty($_POST['authorizedOn']) ? null : DateUtility::isoDateFormat($_POST['authorizedOn'], true),
		'rejection_on' => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
		'result_status' => $status,
		'data_sync' => 0,
		'reason_for_sample_rejection' => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
		'recommended_corrective_action' => (isset($_POST['correctiveAction']) && trim((string) $_POST['correctiveAction']) !== '') ? $_POST['correctiveAction'] : null,
		'request_created_datetime' => DateUtility::getCurrentDateTime(),
		'last_modified_datetime' => DateUtility::getCurrentDateTime(),
		'request_created_by' => $_SESSION['userId'],
		'last_modified_by' => $_SESSION['userId'],
		'result_modified' => 'no',
		'lab_technician' => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] : $_SESSION['userId'],
	];

	if ($general->isLISInstance() || $general->isStandaloneInstance()) {
		$covid19Data['source_of_request'] = 'vlsm';
	} elseif ($general->isSTSInstance()) {
		$covid19Data['source_of_request'] = 'vlsts';
	}

	if (!empty($_POST['labId'])) {
		$facility = $facilitiesService->getFacilityById($_POST['labId']);
		if (isset($facility['contact_person']) && $facility['contact_person'] != "") {
			$covid19Data['lab_manager'] = $facility['contact_person'];
		}
	}



	//if (isset($_POST['asymptomatic']) && $_POST['asymptomatic'] != "yes") {
	$db->where('covid19_id', $_POST['covid19SampleId']);
	$db->delete("covid19_patient_symptoms");
	if (!empty($_POST['symptomDetected']) || (!empty($_POST['symptom']))) {
		$counter = count($_POST['symptomDetected']);
		for ($i = 0; $i < $counter; $i++) {
			$symptomData = [];
			$symptomData["covid19_id"] = $_POST['covid19SampleId'];
			$symptomData["symptom_id"] = $_POST['symptomId'][$i];
			$symptomData["symptom_detected"] = $_POST['symptomDetected'][$i];
			$symptomData["symptom_details"] = (empty($_POST['symptomDetails'][$_POST['symptomId'][$i]])) ? null : json_encode($_POST['symptomDetails'][$_POST['symptomId'][$i]]);
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
		//var_dump($reasonData);
		$db->insert("covid19_reasons_for_testing", $reasonData);
	} elseif (!empty($_POST['reasonForCovid19Test'])) {
		$reasonData = [];
		$reasonData["covid19_id"] = $_POST['covid19SampleId'];
		$reasonData["reasons_id"] = $_POST['reasonForCovid19Test'];
		$reasonData["reasons_detected"] = "yes";
		$reasonData["reason_details"] = null;
		$db->insert("covid19_reasons_for_testing", $reasonData);
	}

	//die;
	$db->where('covid19_id', $_POST['covid19SampleId']);
	$db->delete("covid19_patient_comorbidities");
	if (!empty($_POST['comorbidityDetected'])) {
		$counter = count($_POST['comorbidityDetected']);
		for ($i = 0; $i < $counter; $i++) {
			$comorbidityData = [];
			$comorbidityData["covid19_id"] = $_POST['covid19SampleId'];
			$comorbidityData["comorbidity_id"] = $_POST['comorbidityId'][$i];
			$comorbidityData["comorbidity_detected"] = $_POST['comorbidityDetected'][$i];
			$db->insert("covid19_patient_comorbidities", $comorbidityData);
		}
	}

	if (isset($_POST['covid19SampleId']) && $_POST['covid19SampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
		if (!empty($_POST['testName'])) {
			foreach ($_POST['testName'] as $testKey => $testKitName) {
				if (!empty($testKitName)) {
					$testingPlatform = null;
					$instrumentId = null;
					if (isset($_POST['testingPlatform'][$testKey]) && trim((string) $_POST['testingPlatform'][$testKey]) !== '') {
						$platForm = explode("##", (string) $_POST['testingPlatform'][$testKey]);
						$testingPlatform = $platForm[0];
						$instrumentId = $platForm[1];
					}

					$covid19TestData = ['covid19_id' => $_POST['covid19SampleId'], 'test_name' => ($testKitName == 'other') ? $_POST['testNameOther'][$testKey] : $testKitName, 'facility_id' => $_POST['labId'] ?? null, 'sample_tested_datetime' => DateUtility::isoDateFormat($_POST['testDate'][$testKey] ?? '', true), 'testing_platform' => $testingPlatform ?? null, 'instrument_id' => $instrumentId ?? null, 'kit_lot_no' => (str_contains((string) $testKitName, 'RDT')) ? $_POST['lotNo'][$testKey] : null, 'kit_expiry_date' => (str_contains((string) $testKitName, 'RDT')) ? DateUtility::isoDateFormat($_POST['expDate'][$testKey]) : null, 'result' => $_POST['testResult'][$testKey]];
					$db->insert($testTableName, $covid19TestData);
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

	if (!empty($_POST['covid19SampleId'])) {
		$db->where('covid19_id', $_POST['covid19SampleId']);
		$id = $db->update($tableName, $covid19Data);
	}

	if ($id === true) {
		$_SESSION['alertMsg'] = _translate("Covid-19 test request added successfully");
		//Add event log
		$eventType = 'add-covid-19-request';
		$action = $_SESSION['userName'] . ' added a new Covid-19 request with the sample id ' . $_POST['sampleCode'] . ' and patientId ' . $_POST['patientId'];
		$resource = 'covid-19-add-request';

		$general->activityLog($eventType, $action, $resource);
	} else {
		$_SESSION['alertMsg'] = _translate("Unable to add this Covid-19 sample. Please try again later");
	}
	if (!empty($_POST['quickForm']) && $_POST['quickForm'] == "quick") {
		header("Location:/covid-19/requests/covid-19-quick-add.php");
	} elseif (isset($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
		$cpyReq = $general->getGlobalConfig('covid19_copy_request_save_and_next');
		if (isset($cpyReq) && !empty($cpyReq) && $cpyReq == 'yes') {
			$_SESSION['covid19Data'] = $covid19Data;
		}
		header("Location:/covid-19/requests/covid-19-add-request.php");
	} else {
		header("Location:/covid-19/requests/covid-19-requests.php");
	}
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
