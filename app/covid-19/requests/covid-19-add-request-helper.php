<?php

///  if you change anyting in this file make sure Api file for covid 19 add also 
// Path   /vlsm/api/covid-19/v1/add-request.php

use App\Models\App;
use App\Models\Facilities;
use App\Models\General;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}


$general = new General();
$facilityDb = new Facilities();

/* echo "<pre>";
print_r($_POST);
die; */

$tableName = "form_covid19";
$tableName1 = "activity_log";
$testTableName = 'covid19_tests';

try {
	//system config
	$systemConfigQuery = "SELECT * FROM system_config";
	$systemConfigResult = $db->query($systemConfigQuery);
	$sarr = array();
	// now we create an associative array so that we can easily create view variables
	for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
		$sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
	}
	$instanceId = '';
	if (!empty($_SESSION['instanceId'])) {
		$instanceId = $_SESSION['instanceId'];
	}

	if (empty($instanceId) && $_POST['instanceId']) {
		$instanceId = $_POST['instanceId'];
	}
	if (!empty($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != "") {
		$sampleCollectionDate = explode(" ", $_POST['sampleCollectionDate']);
		$_POST['sampleCollectionDate'] = DateUtils::isoDateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
	} else {
		$_POST['sampleCollectionDate'] = null;
	}

	if (isset($_POST['sampleDispatchedDate']) && trim($_POST['sampleDispatchedDate']) != "") {
		$sampleDispatchedDate = explode(" ", $_POST['sampleDispatchedDate']);
		$_POST['sampleDispatchedDate'] = DateUtils::isoDateFormat($sampleDispatchedDate[0]) . " " . $sampleDispatchedDate[1];
	} else {
		$_POST['sampleDispatchedDate'] = null;
	}

	//Set sample received date
	if (!empty($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
		$sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
		$_POST['sampleReceivedDate'] = DateUtils::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
	} else {
		$_POST['sampleReceivedDate'] = null;
	}
	if (!empty($_POST['sampleTestedDateTime']) && trim($_POST['sampleTestedDateTime']) != "") {
		$sampleTestedDate = explode(" ", $_POST['sampleTestedDateTime']);
		$_POST['sampleTestedDateTime'] = DateUtils::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
	} else {
		$_POST['sampleTestedDateTime'] = null;
	}

	if (!empty($_POST['arrivalDateTime']) && trim($_POST['arrivalDateTime']) != "") {
		$arrivalDate = explode(" ", $_POST['arrivalDateTime']);
		$_POST['arrivalDateTime'] = DateUtils::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
	} else {
		$_POST['arrivalDateTime'] = null;
	}


	if (empty(trim($_POST['sampleCode']))) {
		$_POST['sampleCode'] = null;
	}

	if ($_SESSION['instanceType'] == 'remoteuser') {
		$sampleCode = 'remote_sample_code';
		$sampleCodeKey = 'remote_sample_code_key';
	} else {
		$sampleCode = 'sample_code';
		$sampleCodeKey = 'sample_code_key';
	}

	$status = 6;
	if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
		$status = 9;
	}

	$resultSentToSource = null;

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['result'] = null;
		$status = 4;
		$resultSentToSource = 'pending';
	}
	if (!empty($_POST['patientDob'])) {
		$_POST['patientDob'] = DateUtils::isoDateFormat($_POST['patientDob']);
	}

	if (!empty($_POST['result'])) {
		$resultSentToSource = 'pending';
	}

	if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
		$reviewedOn = explode(" ", $_POST['reviewedOn']);
		$_POST['reviewedOn'] = DateUtils::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
	} else {
		$_POST['reviewedOn'] = null;
	}

	if (isset($_POST['approvedOn']) && trim($_POST['approvedOn']) != "") {
		$approvedOn = explode(" ", $_POST['approvedOn']);
		$_POST['approvedOn'] = DateUtils::isoDateFormat($approvedOn[0]) . " " . $approvedOn[1];
	} else {
		$_POST['approvedOn'] = null;
	}

	$covid19Data = array(
		'vlsm_instance_id'                    => $instanceId,
		'vlsm_country_id'                     => $_POST['formId'],
		'external_sample_code'                => !empty($_POST['externalSampleCode']) ? $_POST['externalSampleCode'] : null,
		'facility_id'                         => !empty($_POST['facilityId']) ? $_POST['facilityId'] : null,
		'investigator_name'                   => !empty($_POST['investigatorName']) ? $_POST['investigatorName'] : null,
		'investigator_phone'                  => !empty($_POST['investigatorPhone']) ? $_POST['investigatorPhone'] : null,
		'investigator_email'                  => !empty($_POST['investigatorEmail']) ? $_POST['investigatorEmail'] : null,
		'clinician_name'                      => !empty($_POST['clinicianName']) ? $_POST['clinicianName'] : null,
		'clinician_phone'                     => !empty($_POST['clinicianPhone']) ? $_POST['clinicianPhone'] : null,
		'clinician_email'                     => !empty($_POST['clinicianEmail']) ? $_POST['clinicianEmail'] : null,
		'test_number'                         => !empty($_POST['testNumber']) ? $_POST['testNumber'] : null,
		'province_id'                         => !empty($_POST['provinceId']) ? $_POST['provinceId'] : null,
		'lab_id'                              => !empty($_POST['labId']) ? $_POST['labId'] : null,
		'testing_point'                       => !empty($_POST['testingPoint']) ? $_POST['testingPoint'] : null,
		'implementing_partner'                => !empty($_POST['implementingPartner']) ? $_POST['implementingPartner'] : null,
		'source_of_alert'                	  => !empty($_POST['sourceOfAlertPOE']) ? $_POST['sourceOfAlertPOE'] : null,
		'source_of_alert_other'               => (!empty($_POST['sourceOfAlertPOE']) && $_POST['sourceOfAlertPOE'] == 'others') ? $_POST['alertPoeOthers'] : null,
		'funding_source'                      => !empty($_POST['fundingSource']) ? $_POST['fundingSource'] : null,
		//'patient_id'                          => !empty($_POST['patientId']) ? $_POST['patientId'] : null,
		'patient_name'                        => !empty($_POST['firstName']) ? $_POST['firstName'] : null,
		'patient_surname'                     => !empty($_POST['lastName']) ? $_POST['lastName'] : null,
		'patient_dob'                         => !empty($_POST['patientDob']) ? $_POST['patientDob'] : null,
		'patient_gender'                      => !empty($_POST['patientGender']) ? $_POST['patientGender'] : null,
		'is_patient_pregnant'                 => !empty($_POST['isPatientPregnant']) ? $_POST['isPatientPregnant'] : null,
		'patient_age'                         => !empty($_POST['patientAge']) ? $_POST['patientAge'] : null,
		'patient_phone_number'                => !empty($_POST['patientPhoneNumber']) ? $_POST['patientPhoneNumber'] : null,
		'patient_email'                		  => !empty($_POST['patientEmail']) ? $_POST['patientEmail'] : null,
		'patient_address'                     => !empty($_POST['patientAddress']) ? $_POST['patientAddress'] : null,
		'patient_province'                    => !empty($_POST['patientProvince']) ? $_POST['patientProvince'] : null,
		'patient_district'                    => !empty($_POST['patientDistrict']) ? $_POST['patientDistrict'] : null,
		'patient_city'                    	  => !empty($_POST['patientCity']) ? $_POST['patientCity'] : null,
		'patient_zone'                    	  => !empty($_POST['patientZone']) ? $_POST['patientZone'] : null,
		'patient_occupation'                  => !empty($_POST['patientOccupation']) ? $_POST['patientOccupation'] : null,
		'does_patient_smoke'                  => !empty($_POST['doesPatientSmoke']) ? $_POST['doesPatientSmoke'] : null,
		'patient_nationality'                 => !empty($_POST['patientNationality']) ? $_POST['patientNationality'] : null,
		'patient_passport_number'             => !empty($_POST['patientPassportNumber']) ? $_POST['patientPassportNumber'] : null,
		'vaccination_status'             	  => !empty($_POST['vaccinationStatus']) ? $_POST['vaccinationStatus'] : null,
		'vaccination_dosage'                  => !empty($_POST['vaccinationDosage']) ? $_POST['vaccinationDosage'] : null,
		'vaccination_type'                    => !empty($_POST['vaccinationType']) ? $_POST['vaccinationType'] : null,
		'vaccination_type_other'              => !empty($_POST['vaccinationTypeOther']) ? $_POST['vaccinationTypeOther'] : null,
		'flight_airline'                 	  => !empty($_POST['airline']) ? $_POST['airline'] : null,
		'flight_seat_no'                 	  => !empty($_POST['seatNo']) ? $_POST['seatNo'] : null,
		'flight_arrival_datetime'             => !empty($_POST['arrivalDateTime']) ? $_POST['arrivalDateTime'] : null,
		'flight_airport_of_departure'         => !empty($_POST['airportOfDeparture']) ? $_POST['airportOfDeparture'] : null,
		'flight_transit'          			  => !empty($_POST['transit']) ? $_POST['transit'] : null,
		'reason_of_visit'          			  => !empty($_POST['reasonOfVisit']) ? $_POST['reasonOfVisit'] : null,
		'is_sample_collected'                 => !empty($_POST['isSampleCollected']) ? $_POST['isSampleCollected'] : null,
		'reason_for_covid19_test'             => !empty($_POST['reasonForCovid19Test']) ? $_POST['reasonForCovid19Test'] : null,
		'type_of_test_requested'              => !empty($_POST['testTypeRequested']) ? $_POST['testTypeRequested'] : null,
		'specimen_type'                       => !empty($_POST['specimenType']) ? $_POST['specimenType'] : null,
		'specimen_taken_before_antibiotics'   => !empty($_POST['specimenTakenBeforeAntibiotics']) ? $_POST['specimenTakenBeforeAntibiotics'] : null,
		'sample_collection_date'              => !empty($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
		'sample_dispatched_datetime'          => !empty($_POST['sampleDispatchedDate']) ? $_POST['sampleDispatchedDate'] : null,
		'health_outcome'              		  => !empty($_POST['healthOutcome']) ? $_POST['healthOutcome'] : null,
		'health_outcome_date'                 => !empty($_POST['outcomeDate']) ? DateUtils::isoDateFormat($_POST['outcomeDate']) : null,
		'is_sample_post_mortem'               => !empty($_POST['isSamplePostMortem']) ? $_POST['isSamplePostMortem'] : null,
		'priority_status'                     => !empty($_POST['priorityStatus']) ? $_POST['priorityStatus'] : null,
		'number_of_days_sick'                 => !empty($_POST['numberOfDaysSick']) ? $_POST['numberOfDaysSick'] : null,
		'suspected_case'                 	  => !empty($_POST['suspectedCase']) ? $_POST['suspectedCase'] : null,
		'asymptomatic'                 	  	  => !empty($_POST['asymptomatic']) ? $_POST['asymptomatic'] : null,
		'date_of_symptom_onset'               => !empty($_POST['dateOfSymptomOnset']) ? DateUtils::isoDateFormat($_POST['dateOfSymptomOnset']) : null,
		'date_of_initial_consultation'        => !empty($_POST['dateOfInitialConsultation']) ? DateUtils::isoDateFormat($_POST['dateOfInitialConsultation']) : null,
		'fever_temp'        				  => !empty($_POST['feverTemp']) ? $_POST['feverTemp'] : null,
		'medical_history'        			  => !empty($_POST['medicalHistory']) ? $_POST['medicalHistory'] : null,
		'recent_hospitalization'   			  => !empty($_POST['recentHospitalization']) ? $_POST['recentHospitalization'] : null,
		'patient_lives_with_children'		  => !empty($_POST['patientLivesWithChildren']) ? $_POST['patientLivesWithChildren'] : null,
		'patient_cares_for_children'		  => !empty($_POST['patientCaresForChildren']) ? $_POST['patientCaresForChildren'] : null,
		'temperature_measurement_method' 	  => !empty($_POST['temperatureMeasurementMethod']) ? $_POST['temperatureMeasurementMethod'] : null,
		'respiratory_rate' 	  				  => !empty($_POST['respiratoryRate']) ? $_POST['respiratoryRate'] : null,
		'oxygen_saturation'	  				  => !empty($_POST['oxygenSaturation']) ? $_POST['oxygenSaturation'] : null,
		'close_contacts'        			  => !empty($_POST['closeContacts']) ? $_POST['closeContacts'] : null,
		'contact_with_confirmed_case'         => !empty($_POST['contactWithConfirmedCase']) ? $_POST['contactWithConfirmedCase'] : null,
		'has_recent_travel_history'           => !empty($_POST['hasRecentTravelHistory']) ? $_POST['hasRecentTravelHistory'] : null,
		'travel_country_names'                => !empty($_POST['countryName']) ? $_POST['countryName'] : null,
		'travel_return_date'                  => !empty($_POST['returnDate']) ? DateUtils::isoDateFormat($_POST['returnDate']) : null,
		'sample_received_at_vl_lab_datetime'  => !empty($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
		'sample_condition'  				  => !empty($_POST['sampleCondition']) ? $_POST['sampleCondition'] : (isset($_POST['specimenQuality']) ? $_POST['specimenQuality'] : null),
		'is_sample_rejected'                  => !empty($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,
		'result'                              => !empty($_POST['result']) ? $_POST['result'] : null,
		'result_sent_to_source'               => $resultSentToSource,
		'if_have_other_diseases'              => (!empty($_POST['ifOtherDiseases'])) ? $_POST['ifOtherDiseases'] : null,
		'other_diseases'                      => (!empty($_POST['otherDiseases']) && $_POST['result'] != 'positive') ? $_POST['otherDiseases'] : null,
		'result_reviewed_by' 				  => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
		'result_reviewed_datetime' 			  => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'result_approved_by' 				  => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  null,
		'result_approved_datetime' 			  => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] :  null,
		'tested_by'                			  => !empty($_POST['testedBy']) ? $_POST['testedBy'] : null,
		'is_result_authorised'                => !empty($_POST['isResultAuthorized']) ? $_POST['isResultAuthorized'] : null,
		'authorized_by'                       => !empty($_POST['authorizedBy']) ? $_POST['authorizedBy'] : null,
		'authorized_on' 					  => !empty($_POST['authorizedOn']) ? DateUtils::isoDateFormat($_POST['authorizedOn']) : null,
		'rejection_on'	 					  => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtils::isoDateFormat($_POST['rejectionDate']) : null,
		'result_status'                       => $status,
		'data_sync'                           => 0,
		'reason_for_sample_rejection'         => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
		'request_created_datetime'            => DateUtils::getCurrentDateTime(),
		'sample_registered_at_lab'            => $db->now(),
		'last_modified_datetime'              => $db->now(),
		'request_created_by'              	  => $_SESSION['userId'],
		'last_modified_by'              	  => $_SESSION['userId'],
		'lab_technician'              		  => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  $_SESSION['userId']
	);
	if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == "vluser" || $sarr['sc_user_type'] == "standalone")) {
		$covid19Data['source_of_request'] = 'vlsm';
	} else if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == "remoteuser")) {
		$covid19Data['source_of_request'] = 'vlsts';
	} else if (!empty($_POST['api']) && $_POST['api'] == "yes") {
		$covid19Data['source_of_request'] = 'api';
	}

	if (!empty($_POST['labId'])) {
		$facility = $facilityDb->getFacilityById($_POST['labId']);
		if (isset($facility['contact_person']) && $facility['contact_person'] != "") {
			$covid19Data['lab_manager'] = $facility['contact_person'];
		}
	}

	if (isset($_POST['asymptomatic']) && $_POST['asymptomatic'] != "yes") {
		$db = $db->where('covid19_id', $_POST['covid19SampleId']);
		$db->delete("covid19_patient_symptoms");
		if (isset($_POST['symptomDetected']) && !empty($_POST['symptomDetected']) || (isset($_POST['symptom']) && !empty($_POST['symptom']))) {
			for ($i = 0; $i < count($_POST['symptomDetected']); $i++) {
				$symptomData = array();
				$symptomData["covid19_id"] = $_POST['covid19SampleId'];
				$symptomData["symptom_id"] = $_POST['symptomId'][$i];
				$symptomData["symptom_detected"] = $_POST['symptomDetected'][$i];
				$symptomData["symptom_details"] 	= (isset($_POST['symptomDetails'][$_POST['symptomId'][$i]]) && count($_POST['symptomDetails'][$_POST['symptomId'][$i]]) > 0) ? json_encode($_POST['symptomDetails'][$_POST['symptomId'][$i]]) : null;
				//var_dump($symptomData);
				$db->insert("covid19_patient_symptoms", $symptomData);
			}
		}
	}

	$db = $db->where('covid19_id', $_POST['covid19SampleId']);
	$db->delete("covid19_reasons_for_testing");
	if (!empty($_POST['reasonDetails'])) {
		$reasonData = array();
		$reasonData["covid19_id"] 		= $_POST['covid19SampleId'];
		$reasonData["reasons_id"] 		= $_POST['reasonForCovid19Test'];
		$reasonData["reasons_detected"]	= "yes";
		$reasonData["reason_details"] 	= json_encode($_POST['reasonDetails']);
		//var_dump($reasonData);
		$db->insert("covid19_reasons_for_testing", $reasonData);
	}

	//die;
	$db = $db->where('covid19_id', $_POST['covid19SampleId']);
	$db->delete("covid19_patient_comorbidities");
	if (isset($_POST['comorbidityDetected']) && !empty($_POST['comorbidityDetected'])) {

		for ($i = 0; $i < count($_POST['comorbidityDetected']); $i++) {
			$comorbidityData = array();
			$comorbidityData["covid19_id"] = $_POST['covid19SampleId'];
			$comorbidityData["comorbidity_id"] = $_POST['comorbidityId'][$i];
			$comorbidityData["comorbidity_detected"] = $_POST['comorbidityDetected'][$i];
			$db->insert("covid19_patient_comorbidities", $comorbidityData);
		}
	}

	// echo "<pre>";print_r($_POST);die;
	if (isset($_POST['covid19SampleId']) && $_POST['covid19SampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
		if (isset($_POST['testName']) && count($_POST['testName']) > 0) {
			foreach ($_POST['testName'] as $testKey => $testKitName) {
				if (isset($testKitName) && !empty($testKitName)) {
					if (isset($_POST['testDate'][$testKey]) && trim($_POST['testDate'][$testKey]) != "") {
						$testedDateTime = explode(" ", $_POST['testDate'][$testKey]);
						$_POST['testDate'][$testKey] = DateUtils::isoDateFormat($testedDateTime[0]) . " " . $testedDateTime[1];
					} else {
						$_POST['testDate'][$testKey] = null;
					}
					$covid19TestData = array(
						'covid19_id'				=> $_POST['covid19SampleId'],
						'test_name'					=> ($testKitName == 'other') ? $_POST['testNameOther'][$testKey] : $testKitName,
						'facility_id'           	=> isset($_POST['labId']) ? $_POST['labId'] : null,
						'sample_tested_datetime' 	=> date('Y-m-d H:i:s', strtotime($_POST['testDate'][$testKey])),
						'testing_platform'      	=> isset($_POST['testingPlatform'][$testKey]) ? $_POST['testingPlatform'][$testKey] : null,
						'kit_lot_no'      			=> (strpos($testKitName, 'RDT') !== false) ? $_POST['lotNo'][$testKey] : null,
						'kit_expiry_date'      		=> (strpos($testKitName, 'RDT') !== false) ? DateUtils::isoDateFormat($_POST['expDate'][$testKey]) : null,
						'result'					=> $_POST['testResult'][$testKey]
					);
					$db->insert($testTableName, $covid19TestData);
					$covid19Data['sample_tested_datetime'] = date('Y-m-d H:i:s', strtotime($_POST['testDate'][$testKey]));
					$covid19Data['covid19_test_platform'] = $_POST['testingPlatform'][$testKey];
					$covid19Data['covid19_test_name'] = $_POST['testName'][$testKey];
				}
			}
		}
	} else {
		$db = $db->where('covid19_id', $_POST['covid19SampleId']);
		$db->delete($testTableName);
		$covid19Data['sample_tested_datetime'] = null;
	}
	$id = 0;

	if (!empty($_POST['covid19SampleId'])) {
		$db = $db->where('covid19_id', $_POST['covid19SampleId']);
		$id = $db->update($tableName, $covid19Data);
	}
	if (!empty($_POST['api']) && $_POST['api'] == "yes") {
		if ($id > 0) {
			$payload = array(
				'status' => 'success',
				'timestamp' => time(),
				'message' => 'Successfully added.'
			);
			$app = new App();
			$trackId = $app->addApiTracking($user['user_id'], 1, 'add-request', 'covid19', $requestUrl, $params, 'json');
			http_response_code(200);
		} else {
			$payload = array(
				'status' => 'failed',
				'timestamp' => time(),
				'error' => 'Unable to add this Covid-19 sample. Please try again later',
				'data' => array()
			);
			http_response_code(301);
		}

		echo json_encode($payload);
		exit(0);
	} else {
		if ($id > 0) {
			$_SESSION['alertMsg'] = _("Covid-19 test request added successfully");
			//Add event log
			$eventType = 'covid-19-add-request';
			$action = $_SESSION['userName'] . ' added a new Covid-19 request with the Sample Code/ID ' . $_POST['sampleCode'] . ' (' . $_POST['covid19SampleId'] . ')';
			$resource = 'covid-19-add-request';

			$general->activityLog($eventType, $action, $resource);
		} else {
			$_SESSION['alertMsg'] = _("Unable to add this Covid-19 sample. Please try again later");
		}
		if (!empty($_POST['saveNext']) && $_POST['saveNext'] == 'next' && (!empty($_POST['quickForm']) && $_POST['quickForm'] == "quick")) {
			header("location:/covid-19/requests/covid-19-quick-add.php");
		} else {
			if (!empty($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
				header("location:/covid-19/requests/covid-19-add-request.php");
			} else {
				header("location:/covid-19/requests/covid-19-requests.php");
			}
		}
	}
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
