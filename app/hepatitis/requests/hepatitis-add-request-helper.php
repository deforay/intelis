<?php

use App\Models\General;
use App\Utilities\DateUtils;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}


$general = new General();

// echo "<pre>";print_r($_POST);die;

$tableName = "form_hepatitis";
$tableName1 = "activity_log";

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
		$_POST['sampleCollectionDate'] = DateUtils::isoDateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
	} else {
		$_POST['sampleCollectionDate'] = null;
	}


	//Set sample received date
	if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
		$sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
		$_POST['sampleReceivedDate'] = DateUtils::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
	} else {
		$_POST['sampleReceivedDate'] = null;
	}

	if (isset($_POST['sampleTestedDateTime']) && trim($_POST['sampleTestedDateTime']) != "") {
		$sampleTestedDate = explode(" ", $_POST['sampleTestedDateTime']);
		$_POST['sampleTestedDateTime'] = DateUtils::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
	} else {
		$_POST['sampleTestedDateTime'] = null;
	}

	if (!isset($_POST['sampleCode']) || trim($_POST['sampleCode']) == '') {
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

	$resultSentToSource = 'pending';

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['hcvCount'] = null;
		$_POST['hbvCount'] = null;
		$resultSentToSource = 'pending';
	} else if (empty($_POST['hcvCount']) && empty($_POST['hbvCount'])) {
		$resultSentToSource = null;
	}

	if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
		$reviewedOn = explode(" ", $_POST['reviewedOn']);
		$_POST['reviewedOn'] = DateUtils::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
	} else {
		$_POST['reviewedOn'] = null;
	}

	$hepatitisData = array(
		'vlsm_instance_id'                    => $instanceId,
		'vlsm_country_id'                     => $_POST['formId'],
		'external_sample_code'                => isset($_POST['externalSampleCode']) ? $_POST['externalSampleCode'] : null,
		'hepatitis_test_type'                 => isset($_POST['hepatitisTestType']) ? $_POST['hepatitisTestType'] : 'hcv',
		'facility_id'                         => isset($_POST['facilityId']) ? $_POST['facilityId'] : null,
		'test_number'                         => isset($_POST['testNumber']) ? $_POST['testNumber'] : null,
		'province_id'                         => isset($_POST['provinceId']) ? $_POST['provinceId'] : null,
		'lab_id'                              => isset($_POST['labId']) ? $_POST['labId'] : null,
		'implementing_partner'                => isset($_POST['implementingPartner']) ? $_POST['implementingPartner'] : null,
		'funding_source'                      => isset($_POST['fundingSource']) ? $_POST['fundingSource'] : null,
		'patient_id'                          => isset($_POST['patientId']) ? $_POST['patientId'] : null,
		'patient_name'                        => isset($_POST['firstName']) ? $_POST['firstName'] : null,
		'patient_surname'                     => isset($_POST['lastName']) ? $_POST['lastName'] : null,
		'patient_dob'                         => isset($_POST['patientDob']) ? DateUtils::isoDateFormat($_POST['patientDob']) : null,
		'patient_gender'                      => isset($_POST['patientGender']) ? $_POST['patientGender'] : null,
		'patient_age'                         => isset($_POST['patientAge']) ? $_POST['patientAge'] : null,
		'patient_marital_status'              => isset($_POST['maritalStatus']) ? $_POST['maritalStatus'] : null,
		'patient_insurance'                	  => isset($_POST['insurance']) ? $_POST['insurance'] : null,
		'patient_phone_number'                => isset($_POST['patientPhoneNumber']) ? $_POST['patientPhoneNumber'] : null,
		'patient_address'                     => isset($_POST['patientAddress']) ? $_POST['patientAddress'] : null,
		'patient_province'                    => isset($_POST['patientProvince']) ? $_POST['patientProvince'] : null,
		'patient_district'                    => isset($_POST['patientDistrict']) ? $_POST['patientDistrict'] : null,
		'patient_city'                    	  => isset($_POST['patientCity']) ? $_POST['patientCity'] : null,
		'patient_occupation'                  => isset($_POST['patientOccupation']) ? $_POST['patientOccupation'] : null,
		'patient_nationality'                 => isset($_POST['patientNationality']) ? $_POST['patientNationality'] : null,
		'hbv_vaccination'                     => isset($_POST['HbvVaccination']) ? $_POST['HbvVaccination'] : null,
		'is_sample_collected'                 => isset($_POST['isSampleCollected']) ? $_POST['isSampleCollected'] : null,
		'type_of_test_requested'              => isset($_POST['testTypeRequested']) ? $_POST['testTypeRequested'] : null,
		'reason_for_vl_test'  				  => isset($_POST['reasonVlTest']) ? $_POST['reasonVlTest'] : null,
		'specimen_type'                       => isset($_POST['specimenType']) ? $_POST['specimenType'] : null,
		'sample_collection_date'              => isset($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
		'sample_received_at_vl_lab_datetime'  => isset($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
		'sample_tested_datetime'  			  => isset($_POST['sampleTestedDateTime']) ? $_POST['sampleTestedDateTime'] : null,
		'vl_testing_site'  			  		  => isset($_POST['vlTestingSite']) ? $_POST['vlTestingSite'] : null,
		'sample_condition'  				  => isset($_POST['sampleCondition']) ? $_POST['sampleCondition'] : (isset($_POST['specimenQuality']) ? $_POST['specimenQuality'] : null),
		'is_sample_rejected'                  => isset($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,
		'hbsag_result'                        => isset($_POST['HBsAg']) ? $_POST['HBsAg'] : null,
		'anti_hcv_result'                     => isset($_POST['antiHcv']) ? $_POST['antiHcv'] : null,
		'result'                       		  => isset($_POST['result']) ? $_POST['result'] : null,
		'hcv_vl_result'                       => isset($_POST['hcv']) ? $_POST['hcv'] : null,
		'hbv_vl_result'                       => isset($_POST['hbv']) ? $_POST['hbv'] : null,
		'hcv_vl_count'                        => isset($_POST['hcvCount']) ? $_POST['hcvCount'] : null,
		'hbv_vl_count'                        => isset($_POST['hbvCount']) ? $_POST['hbvCount'] : null,
		'hepatitis_test_platform'             => isset($_POST['hepatitisPlatform']) ? $_POST['hepatitisPlatform'] : null,
		'import_machine_name'                 => isset($_POST['machineName']) ? $_POST['machineName'] : null,
		'is_result_authorised'                => isset($_POST['isResultAuthorized']) ? $_POST['isResultAuthorized'] : null,
		'result_reviewed_by' 				  => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
		'result_reviewed_datetime' 			  => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'authorized_by'                       => isset($_POST['authorizedBy']) ? $_POST['authorizedBy'] : null,
		'social_category'                     => isset($_POST['socialCategory']) ? $_POST['socialCategory'] : null,
		'authorized_on' 					  => isset($_POST['authorizedOn']) ? DateUtils::isoDateFormat($_POST['authorizedOn']) : null,
		'rejection_on'	 					  => (isset($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtils::isoDateFormat($_POST['rejectionDate']) : null,
		'result_status'                       => $status,
		'result_sent_to_source'               => $resultSentToSource,
		'data_sync'                           => 0,
		'reason_for_sample_rejection'         => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
		'request_created_by'                  => $_SESSION['userId'],
		'request_created_datetime'            => DateUtils::getCurrentDateTime(),
		'sample_registered_at_lab'            => $db->now(),
		'last_modified_by'                    => $_SESSION['userId'],
		'last_modified_datetime'              => $db->now(),
		'lab_technician'              		  => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  $_SESSION['userId']
	);

	if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == "vluser" || $sarr['sc_user_type'] == "standalone")) {
		$hepatitisData['source_of_request'] = 'vlsm';
	} else if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == "remoteuser")) {
		$hepatitisData['source_of_request'] = 'vlsts';
	} else if (!empty($_POST['api']) && $_POST['api'] == "yes") {
        $hepatitisData['source_of_request'] = 'api';
    }

	// echo "<pre>";print_r($hepatitisData);die;
	// For Save Comorbidity 
	if (isset($_POST['hepatitisSampleId']) && $_POST['hepatitisSampleId'] != 0) {

		$db = $db->where('hepatitis_id', $_POST['hepatitisSampleId']);
		$db->delete("hepatitis_patient_comorbidities");
		if (isset($_POST['comorbidity']) && !empty($_POST['comorbidity'])) {

			foreach ($_POST['comorbidity'] as $id => $value) {
				$comorbidityData = array();
				$comorbidityData["hepatitis_id"] = $_POST['hepatitisSampleId'];
				$comorbidityData["comorbidity_id"] = $id;
				$comorbidityData["comorbidity_detected"] = (isset($value) && $value == 'other') ? $_POST['comorbidityOther'][$id] : $value;
				$db->insert("hepatitis_patient_comorbidities", $comorbidityData);
			}
		}
		// For Save Risk factors 
		$db = $db->where('hepatitis_id', $_POST['hepatitisSampleId']);
		$db->delete("hepatitis_risk_factors");
		if (isset($_POST['riskFactors']) && !empty($_POST['riskFactors'])) {

			foreach ($_POST['riskFactors'] as $id => $value) {
				$riskFactorsData = array();
				$riskFactorsData["hepatitis_id"] = $_POST['hepatitisSampleId'];
				$riskFactorsData["riskfactors_id"] = $id;
				$riskFactorsData["riskfactors_detected"] = (isset($value) && $value == 'other') ? $_POST['riskFactorsOther'][$id] : $value;
                $db->insert("hepatitis_risk_factors", $riskFactorsData);
			}
		}

		$id = 0;
		if (isset($_POST['hepatitisSampleId']) && $_POST['hepatitisSampleId'] != '') {
			$db = $db->where('hepatitis_id', $_POST['hepatitisSampleId']);
			$id = $db->update($tableName, $hepatitisData);
		}
	} else {
		$id = 0;
	}

	if ($id > 0) {
		$_SESSION['alertMsg'] = _("Hepatitis test request added successfully");
		//Add event log
		$eventType = 'hepatitis-add-request';
		$action = $_SESSION['userName'] . ' added a new hepatitis request with the Sample ID/Code  ' . $_POST['hepatitisSampleId'];
		$resource = 'hepatitis-add-request';

		$general->activityLog($eventType, $action, $resource);
	} else {
		$_SESSION['alertMsg'] = _("Unable to add this hepatitis sample. Please try again later");
	}

	if (isset($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
		header("location:/hepatitis/requests/hepatitis-add-request.php");
	} else {
		header("location:/hepatitis/requests/hepatitis-requests.php");
	}
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
