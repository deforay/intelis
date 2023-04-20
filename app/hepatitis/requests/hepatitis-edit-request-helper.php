<?php

use App\Models\General;
use App\Utilities\DateUtils;


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
	$sarr = [];
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


	if (isset($_POST['arrivalDateTime']) && trim($_POST['arrivalDateTime']) != "") {
		$arrivalDate = explode(" ", $_POST['arrivalDateTime']);
		$_POST['arrivalDateTime'] = DateUtils::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
	} else {
		$_POST['arrivalDateTime'] = null;
	}


	if ($_SESSION['instanceType'] == 'remoteuser') {
		$sampleCode = 'remote_sample_code';
		$sampleCodeKey = 'remote_sample_code_key';
	} else {
		$sampleCode = 'sample_code';
		$sampleCodeKey = 'sample_code_key';
	}

	if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
		$status = 9;
	}

	if (isset($_POST['oldStatus']) && !empty($_POST['oldStatus'])) {
		$status = $_POST['oldStatus'];
	}

	if ($sarr['sc_user_type'] == 'vluser' && $_POST['oldStatus'] == 9) {
		$status = 6;
	}

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['result'] = null;
		$status = 4;
	}


	if ($sarr['sc_user_type'] == 'remoteuser' && $_POST['oldStatus'] == 9) {
		$_POST['status'] = 9;
	} else if ($sarr['sc_user_type'] == 'vluser' && $_POST['oldStatus'] == 9) {
		$_POST['status'] = 6;
	}
	if (isset($_POST['status']) && $_POST['status'] == '') {
		$_POST['status']  = $_POST['oldStatus'];
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
		'external_sample_code'                => $_POST['externalSampleCode'] ?? null,
		'hepatitis_test_type'                 => $_POST['hepatitisTestType'] ?? 'hcv',
		'facility_id'                         => $_POST['facilityId'] ?? null,
		'test_number'                         => $_POST['testNumber'] ?? null,
		'province_id'                         => $_POST['provinceId'] ?? null,
		'lab_id'                              => $_POST['labId'] ?? null,
		'implementing_partner'                => $_POST['implementingPartner'] ?? null,
		'funding_source'                      => $_POST['fundingSource'] ?? null,
		'patient_id'                          => $_POST['patientId'] ?? null,
		'patient_name'                        => $_POST['firstName'] ?? null,
		'patient_surname'                     => $_POST['lastName'] ?? null,
		'patient_dob'                         => isset($_POST['patientDob']) ? DateUtils::isoDateFormat($_POST['patientDob']) : null,
		'patient_gender'                      => $_POST['patientGender'] ?? null,
		'patient_age'                         => $_POST['patientAge'] ?? null,
		'patient_marital_status'              => $_POST['maritalStatus'] ?? null,
		'patient_insurance'                	  => $_POST['insurance'] ?? null,
		'patient_phone_number'                => $_POST['patientPhoneNumber'] ?? null,
		'patient_address'                     => $_POST['patientAddress'] ?? null,
		'patient_province'                    => $_POST['patientProvince'] ?? null,
		'patient_district'                    => $_POST['patientDistrict'] ?? null,
		'patient_city'                    	  => $_POST['patientCity'] ?? null,
		'patient_occupation'                  => $_POST['patientOccupation'] ?? null,
		'patient_nationality'                 => $_POST['patientNationality'] ?? null,
		'hbv_vaccination'                     => $_POST['HbvVaccination'] ?? null,
		'is_sample_collected'                 => $_POST['isSampleCollected'] ?? null,
		'type_of_test_requested'              => $_POST['testTypeRequested'] ?? null,
		'reason_for_vl_test'  				  => $_POST['reasonVlTest'] ?? null,
		'specimen_type'                       => $_POST['specimenType'] ?? null,
		'sample_collection_date'              => $_POST['sampleCollectionDate'] ?? null,
		'sample_received_at_vl_lab_datetime'  => $_POST['sampleReceivedDate'] ?? null,
		'sample_tested_datetime'  			  => $_POST['sampleTestedDateTime'] ?? null,
		'vl_testing_site'  			  		  => $_POST['vlTestingSite'] ?? null,
		'sample_condition'  				  => $_POST['sampleCondition'] ?? ($_POST['specimenQuality'] ?? null),
		'is_sample_rejected'                  => $_POST['isSampleRejected'] ?? null,
		'hbsag_result'                        => $_POST['HBsAg'] ?? null,
		'anti_hcv_result'                     => $_POST['antiHcv'] ?? null,
		'result'                       		  => $_POST['result'] ?? null,
		'hcv_vl_result'                       => $_POST['hcv'] ?? null,
		'hbv_vl_result'                       => $_POST['hbv'] ?? null,
		'hcv_vl_count'                        => $_POST['hcvCount'] ?? null,
		'hbv_vl_count'                        => $_POST['hbvCount'] ?? null,
		'hepatitis_test_platform'             => $_POST['hepatitisPlatform'] ?? null,
		'import_machine_name'                 => $_POST['machineName'] ?? null,
		'is_result_authorised'                => $_POST['isResultAuthorized'] ?? null,
		'result_reviewed_by' 				  => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
		'result_reviewed_datetime' 			  => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'authorized_by'                       => $_POST['authorizedBy'] ?? null,
		'authorized_on' 					  => isset($_POST['authorizedOn']) ? DateUtils::isoDateFormat($_POST['authorizedOn']) : null,
		'revised_by' 						  => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
		'revised_on' 						  => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtils::getCurrentDateTime() : "",
		'rejection_on'	 					  => (isset($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtils::isoDateFormat($_POST['rejectionDate']) : null,
		'result_status'                       => $status,
		'result_sent_to_source'               => $resultSentToSource,
		'data_sync'                           => 0,
		'reason_for_sample_rejection'         => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
		'last_modified_by'                    => $_SESSION['userId'],
		'last_modified_datetime'              => $db->now(),
		'lab_technician'              		  => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  $_SESSION['userId'],
	);

	// For Save Comorbidity 
	if (isset($_POST['hepatitisSampleId']) && $_POST['hepatitisSampleId'] != 0) {

		$db = $db->where('hepatitis_id', $_POST['hepatitisSampleId']);
		$db->delete("hepatitis_patient_comorbidities");
		if (isset($_POST['comorbidity']) && !empty($_POST['comorbidity'])) {

			foreach ($_POST['comorbidity'] as $id => $value) {
				$comorbidityData = [];
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
				$riskFactorsData = [];
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

	if ($id > 0 || $sid > 0 || $pid > 0) {
		$_SESSION['alertMsg'] = _("Hepatitis request updated successfully");
		//Add event log
		$eventType = 'update-hepatitis-request';
		$action = $_SESSION['userName'] . ' updated hepatitis request with the Sample ID/Code  ' . $_POST['hepatitisSampleId'];
		$resource = 'hepatitis-edit-request';

		$general->activityLog($eventType, $action, $resource);

		// $data=array(
		// 'event_type'=>$eventType,
		// 'action'=>$action,
		// 'resource'=>$resource,
		// 'date_time'=>\App\Utilities\DateUtils::getCurrentDateTime()
		// );
		// $db->insert($tableName1,$data);

	} else {
		$_SESSION['alertMsg'] = _("Please try again later");
	}
	header("Location:/hepatitis/requests/hepatitis-requests.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
