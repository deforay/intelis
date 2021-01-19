<?php

///  if you change anyting in this file make sure Api file for covid 19 update also 
// Path   /vlsm/api/covid-19/v1/update-request.php

ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

$general = new \Vlsm\Models\General($db);

// echo "<pre>";print_r($_POST);die;

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
	if (isset($_SESSION['instanceId'])) {
		$instanceId = $_SESSION['instanceId'];
	}

	if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != "") {
		$sampleCollectionDate = explode(" ", $_POST['sampleCollectionDate']);
		$_POST['sampleCollectionDate'] = $general->dateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
	} else {
		$_POST['sampleCollectionDate'] = NULL;
	}

	//Set sample received date
	if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
		$sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
		$_POST['sampleReceivedDate'] = $general->dateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
	} else {
		$_POST['sampleReceivedDate'] = NULL;
	}

	if (isset($_POST['sampleTestedDateTime']) && trim($_POST['sampleTestedDateTime']) != "") {
		$sampleTestedDate = explode(" ", $_POST['sampleTestedDateTime']);
		$_POST['sampleTestedDateTime'] = $general->dateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
	} else {
		$_POST['sampleTestedDateTime'] = NULL;
	}


	if (isset($_POST['arrivalDateTime']) && trim($_POST['arrivalDateTime']) != "") {
		$arrivalDate = explode(" ", $_POST['arrivalDateTime']);
		$_POST['arrivalDateTime'] = $general->dateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
	} else {
		$_POST['arrivalDateTime'] = NULL;
	}


	if ($sarr['user_type'] == 'remoteuser') {
		$sampleCode = 'remote_sample_code';
		$sampleCodeKey = 'remote_sample_code_key';
	} else {
		$sampleCode = 'sample_code';
		$sampleCodeKey = 'sample_code_key';
	}




	if ($sarr['user_type'] == 'remoteuser') {
		$status = 9;
	}

	if (isset($_POST['oldStatus']) && !empty($_POST['oldStatus'])) {
		$status = $_POST['oldStatus'];
	}

	if ($sarr['user_type'] == 'vluser' && $_POST['oldStatus'] == 9) {
		$status = 6;
	}

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['result'] = null;
		$status = 4;
	}


	if ($sarr['user_type'] == 'remoteuser' && $_POST['oldStatus'] == 9) {
		$_POST['status'] = 9;
	} else if ($sarr['user_type'] == 'vluser' && $_POST['oldStatus'] == 9) {
		$_POST['status'] = 6;
	}
	if (isset($_POST['status']) && $_POST['status'] == '') {
		$_POST['status']  = $_POST['oldStatus'];
	}


	$covid19Data = array(
		'external_sample_code'                => !empty($_POST['externalSampleCode']) ? $_POST['externalSampleCode'] : null,
		'facility_id'                         => !empty($_POST['facilityId']) ? $_POST['facilityId'] : null,
		'test_number'                         => !empty($_POST['testNumber']) ? $_POST['testNumber'] : null,
		'province_id'                         => !empty($_POST['provinceId']) ? $_POST['provinceId'] : null,
		'lab_id'                              => !empty($_POST['labId']) ? $_POST['labId'] : null,
		'testing_point'                       => !empty($_POST['testingPoint']) ? $_POST['testingPoint'] : null,
		'implementing_partner'                => !empty($_POST['implementingPartner']) ? $_POST['implementingPartner'] : null,
		'source_of_alert'                	  => !empty($_POST['sourceOfAlertPOE']) ? $_POST['sourceOfAlertPOE'] : null,
		'source_of_alert_other'               => (!empty($_POST['sourceOfAlertPOE']) && $_POST['sourceOfAlertPOE']== 'others') ? $_POST['alertPoeOthers'] : null,
		'funding_source'                      => !empty($_POST['fundingSource']) ? $_POST['fundingSource'] : null,
		'patient_id'                          => !empty($_POST['patientId']) ? $_POST['patientId'] : null,
		'patient_name'                        => !empty($_POST['firstName']) ? $_POST['firstName'] : null,
		'patient_surname'                     => !empty($_POST['lastName']) ? $_POST['lastName'] : null,
		'patient_dob'                         => !empty($_POST['patientDob']) ? $general->dateFormat($_POST['patientDob']) : null,
		'patient_gender'                      => !empty($_POST['patientGender']) ? $_POST['patientGender'] : null,
		'is_patient_pregnant'                 => !empty($_POST['isPatientPregnant']) ? $_POST['isPatientPregnant'] : null,
		'patient_age'                         => !empty($_POST['patientAge']) ? $_POST['patientAge'] : null,
		'patient_phone_number'                => !empty($_POST['patientPhoneNumber']) ? $_POST['patientPhoneNumber'] : null,
		'patient_address'                     => !empty($_POST['patientAddress']) ? $_POST['patientAddress'] : null,
		'patient_province'                    => !empty($_POST['patientProvince']) ? $_POST['patientProvince'] : null,
		'patient_district'                    => !empty($_POST['patientDistrict']) ? $_POST['patientDistrict'] : null,
		'patient_city'                    	  => !empty($_POST['patientCity']) ? $_POST['patientCity'] : null,
		'patient_occupation'                  => !empty($_POST['patientOccupation']) ? $_POST['patientOccupation'] : null,
		'does_patient_smoke'                  => !empty($_POST['doesPatientSmoke']) ? $_POST['doesPatientSmoke'] : null,
		'patient_nationality'                 => !empty($_POST['patientNationality']) ? $_POST['patientNationality'] : null,
		'patient_passport_number'             => !empty($_POST['patientPassportNumber']) ? $_POST['patientPassportNumber'] : null,
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
		'sample_collection_date'              => !empty($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
		'is_sample_post_mortem'               => !empty($_POST['isSamplePostMortem']) ? $_POST['isSamplePostMortem'] : null,
		'priority_status'                     => !empty($_POST['priorityStatus']) ? $_POST['priorityStatus'] : null,
		'number_of_days_sick'                 => !empty($_POST['numberOfDaysSick']) ? $_POST['numberOfDaysSick'] : null,
		'date_of_symptom_onset'               => !empty($_POST['dateOfSymptomOnset']) ? $general->dateFormat($_POST['dateOfSymptomOnset']) : null,
		'date_of_initial_consultation'        => !empty($_POST['dateOfInitialConsultation']) ? $general->dateFormat($_POST['dateOfInitialConsultation']) : null,
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
		'travel_return_date'                  => !empty($_POST['returnDate']) ? $general->dateFormat($_POST['returnDate']) : null,
		'sample_received_at_vl_lab_datetime'  => !empty($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
		'sample_condition'  				  => !empty($_POST['sampleCondition']) ? $_POST['sampleCondition'] : (!empty($_POST['specimenQuality']) ? $_POST['specimenQuality'] : null),
		// 'lab_technician' 					  => (!empty($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  $_SESSION['userId'],
		'is_sample_rejected'                  => !empty($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,
		'result'                              => !empty($_POST['result']) ? $_POST['result'] : null,
		'other_diseases'                      => (!empty($_POST['otherDiseases']) && $_POST['result'] != 'positive') ? $_POST['otherDiseases'] : null,
		// 'tested_by'                       	  => !empty($_POST['testedBy']) ? $_POST['testedBy'] : null,
		'is_result_authorised'                => !empty($_POST['isResultAuthorized']) ? $_POST['isResultAuthorized'] : null,
		'authorized_by'                       => !empty($_POST['authorizedBy']) ? $_POST['authorizedBy'] : null,
		'authorized_on' 					  => !empty($_POST['authorizedOn']) ? $general->dateFormat($_POST['authorizedOn']) : null,
		'rejection_on'	 					  => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? $general->dateFormat($_POST['rejectionDate']) : null,
		'reason_for_changing'				  => (!empty($_POST['reasonForChanging']) && !empty($_POST['reasonForChanging'])) ? $_POST['reasonForChanging'] : null,
		'result_status'                       => $status,
		'data_sync'                           => 0,
		'reason_for_sample_rejection'         => (!empty($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
		'sample_registered_at_lab'            => $general->getDateTime(),
		'last_modified_by'                    => $_SESSION['userId'],
		'last_modified_datetime'              => $general->getDateTime()
	);
	$lock = $general->getGlobalConfig('lock_approved_covid19_samples');
    if($status == 7 && $lock == 'yes'){
		$covid19Data['locked'] = 'yes';
	}

	if(!empty($_POST['api']) && $_POST['api'] = "yes")
	{

	}
	else
	{
		$covid19Data['last_modified_by'] =  $_SESSION['userId'];
		$covid19Data['lab_technician'] = (!empty($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  $_SESSION['userId'];
	}
	// if ($sarr['user_type'] == 'remoteuser') {
	// 	//$covid19Data['remote_sample_code'] = (!empty($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] : NULL;
	// } else {
	// 	if (isset($_POST['sampleCodeCol']) && $_POST['sampleCodeCol'] != '') {
	// 		//$covid19Data['sample_code'] = (!empty($_POST['sampleCodeCol']) && $_POST['sampleCodeCol'] != '') ? $_POST['sampleCodeCol'] : NULL;
	// 	} else {
	// 		$covid19Model = new \Vlsm\Models\Covid19($db);

	// 		$sampleCodeKeysJson = $covid19Model->generateCovid19SampleCode($_POST['provinceCode'], $_POST['sampleCollectionDate']);
	// 		$sampleCodeKeys = json_decode($sampleCodeKeysJson, true);
	// 		$covid19Data['sample_code'] = $sampleCodeKeys['sampleCode'];
	// 		$covid19Data['sample_code_key'] = $sampleCodeKeys['sampleCodeKey'];
	// 		$covid19Data['sample_code_format'] = $sampleCodeKeys['sampleCodeFormat'];
	// 	}
	// }

	if (isset($_POST['deletedRow']) && trim($_POST['deletedRow']) != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
		$deleteRows = explode(',', $_POST['deletedRow']);
		foreach ($deleteRows as $delete) {
			$db = $db->where('test_id', base64_decode($delete));
			$db->delete($testTableName);
		}
	}

	$db = $db->where('covid19_id', $_POST['covid19SampleId']);
	$sid = $db->delete("covid19_patient_symptoms");
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

	$db = $db->where('covid19_id', $_POST['covid19SampleId']);
	$db->delete("covid19_reasons_for_testing");
	if (!empty($_POST['reasonDetails'])) {
		$reasonData = array();
			$reasonData["covid19_id"] 		= $_POST['covid19SampleId'];
			$reasonData["reasons_id"] 		= $_POST['reasonForCovid19Test'];
			$reasonData["reasons_detected"]	= "yes";
			$reasonData["reason_details"] 	= json_encode($_POST['reasonDetails']);
			$db->insert("covid19_reasons_for_testing", $reasonData);
	}

	$db = $db->where('covid19_id', $_POST['covid19SampleId']);
	$pid = $db->delete("covid19_patient_comorbidities");
	if (isset($_POST['comorbidityDetected']) && !empty($_POST['comorbidityDetected'])) {

		for ($i = 0; $i < count($_POST['comorbidityDetected']); $i++) {
			$comorbidityData = array();
			$comorbidityData["covid19_id"] = $_POST['covid19SampleId'];
			$comorbidityData["comorbidity_id"] = $_POST['comorbidityId'][$i];
			$comorbidityData["comorbidity_detected"] = $_POST['comorbidityDetected'][$i];
			$db->insert("covid19_patient_comorbidities", $comorbidityData);
		}
	}


	if (isset($_POST['covid19SampleId']) && $_POST['covid19SampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {

		if (isset($_POST['testName']) && count($_POST['testName']) > 0) {
			foreach ($_POST['testName'] as $testKey => $testName) {
				if (trim($_POST['testName'][$testKey]) != "") {
					if (isset($_POST['testDate'][$testKey]) && trim($_POST['testDate'][$testKey]) != "") {
						$testedDateTime = explode(" ", $_POST['testDate'][$testKey]);
						$_POST['testDate'][$testKey] = $general->dateFormat($testedDateTime[0]) . " " . $testedDateTime[1];
					} else {
						$_POST['testDate'][$testKey] = NULL;
					}
					$covid19TestData = array(
						'covid19_id'			=> $_POST['covid19SampleId'],
						'test_name'				=> ($testName == 'other') ? $_POST['testNameOther'][$testKey] : $testName,
						'facility_id'           => isset($_POST['labId']) ? $_POST['labId'] : null,
						'sample_tested_datetime' => $_POST['testDate'][$testKey],
						'testing_platform'      => isset($_POST['testingPlatform'][$testKey]) ? $_POST['testingPlatform'][$testKey] : null,
						'result'				=> $_POST['testResult'][$testKey],
					);
					if (isset($_POST['testId'][$testKey]) && $_POST['testId'][$testKey] != '') {
						$db = $db->where('test_id', base64_decode($_POST['testId'][$testKey]));
						$db->update($testTableName, $covid19TestData);
					} else {
						$db->insert($testTableName, $covid19TestData);
					}
					$covid19Data['sample_tested_datetime'] = date('Y-m-d H:i:s', strtotime($_POST['testDate'][$testKey]));
				}
			}
		}
	} else {
		$db = $db->where('covid19_id', $_POST['covid19SampleId']);
		$db->delete($testTableName);
		$covid19Data['sample_tested_datetime'] = null;
	}
	// echo "<pre>";print_r($covid19Data);die;
	if (isset($_POST['covid19SampleId']) && $_POST['covid19SampleId'] != '') {
		$db = $db->where('covid19_id', $_POST['covid19SampleId']);
		$id = $db->update($tableName, $covid19Data);
	}
	if(isset($_POST['api']) && $_POST['api'] = "yes")
	{
		$payload = array(
			        'status' => 'success',
			        'timestamp' => time(),
			        'message' => 'Successfully updated.'
			    );
			   
			
			    http_response_code(200);
			    echo json_encode($payload);
			    exit(0);
	}
	else
	{
		if ($id > 0 || $sid > 0 || $pid > 0) {
			$_SESSION['alertMsg'] = "Covid-19 request updated successfully";
			//Add event log
			$eventType = 'update-covid-19-request';
			$action = ucwords($_SESSION['userName']) . ' updated Covid-19 request data with the sample id ' . $_POST['covid19SampleId'];
			$resource = 'covid-19-edit-request';

			$general->activityLog($eventType, $action, $resource);

			// $data=array(
			// 'event_type'=>$eventType,
			// 'action'=>$action,
			// 'resource'=>$resource,
			// 'date_time'=>$general->getDateTime()
			// );
			// $db->insert($tableName1,$data);

		} else {
			$_SESSION['alertMsg'] = "Please try again later";
		}
		header("location:/covid-19/requests/covid-19-requests.php");
	}
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
