<?php

ob_start();
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

$general = new \Vlsm\Models\General();

// echo "<pre>";
// var_dump($_POST);
// die;


$tableName = "eid_form";
$tableName1 = "activity_log";

try {
	//system config
	$systemConfigQuery = "SELECT * from system_config";
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

	if (isset($_POST['approvedOnDateTime']) && trim($_POST['approvedOnDateTime']) != "") {
		$approvedOnDateTime = explode(" ", $_POST['approvedOnDateTime']);
		$_POST['approvedOnDateTime'] = $general->dateFormat($approvedOnDateTime[0]) . " " . $approvedOnDateTime[1];
	} else {
		$_POST['approvedOnDateTime'] = NULL;
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

	if (isset($_POST['rapidtestDate']) && trim($_POST['rapidtestDate']) != "") {
		$rapidtestDate = explode(" ", $_POST['rapidtestDate']);
		$_POST['rapidtestDate'] = $general->dateFormat($rapidtestDate[0]) . " " . $rapidtestDate[1];
	} else {
		$_POST['rapidtestDate'] = NULL;
	}

	if (isset($_POST['childDob']) && trim($_POST['childDob']) != "") {
		$childDob = explode(" ", $_POST['childDob']);
		$_POST['childDob'] = $general->dateFormat($childDob[0]) . " " . $childDob[1];
	} else {
		$_POST['childDob'] = NULL;
	}

	if (isset($_POST['mothersDob']) && trim($_POST['mothersDob']) != "") {
		$mothersDob = explode(" ", $_POST['mothersDob']);
		$_POST['mothersDob'] = $general->dateFormat($mothersDob[0]) . " " . $mothersDob[1];
	} else {
		$_POST['mothersDob'] = NULL;
	}

	if (isset($_POST['motherTreatmentInitiationDate']) && trim($_POST['motherTreatmentInitiationDate']) != "") {
		$motherTreatmentInitiationDate = explode(" ", $_POST['motherTreatmentInitiationDate']);
		$_POST['motherTreatmentInitiationDate'] = $general->dateFormat($motherTreatmentInitiationDate[0]) . " " . $motherTreatmentInitiationDate[1];
	} else {
		$_POST['motherTreatmentInitiationDate'] = NULL;
	}


	if ($sarr['sc_user_type'] == 'remoteuser') {
		$sampleCode = 'remote_sample_code';
		$sampleCodeKey = 'remote_sample_code_key';
	} else {
		$sampleCode = 'sample_code';
		$sampleCodeKey = 'sample_code_key';
	}


	if (isset($_POST['motherViralLoadCopiesPerMl']) && $_POST['motherViralLoadCopiesPerMl'] != "") {
		$motherVlResult = $_POST['motherViralLoadCopiesPerMl'];
	} else if (isset($_POST['motherViralLoadText']) && $_POST['motherViralLoadText'] != "") {
		$motherVlResult = $_POST['motherViralLoadText'];
	} else {
		$motherVlResult = null;
	}



	if ($sarr['sc_user_type'] == 'remoteuser') {
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

	if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
		$reviewedOn = explode(" ", $_POST['reviewedOn']);
		$_POST['reviewedOn'] = $general->dateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
	} else {
		$_POST['reviewedOn'] = NULL;
	}

	if ($sarr['sc_user_type'] == 'remoteuser' && $_POST['oldStatus'] == 9) {
		$_POST['status'] = 9;
	} else if ($sarr['sc_user_type'] == 'vluser' && $_POST['oldStatus'] == 9) {
		$_POST['status'] = 6;
	}
	if ($_POST['status'] == '') {
		$_POST['status']  = $_POST['oldStatus'];
	}


	$eidData = array(
		'facility_id' 										=> isset($_POST['facilityId']) ? $_POST['facilityId'] : null,
		'province_id' 										=> isset($_POST['provinceId']) ? $_POST['provinceId'] : null,
		'lab_id' 											=> isset($_POST['labId']) ? $_POST['labId'] : null,
		'implementing_partner' 								=> isset($_POST['implementingPartner']) ? $_POST['implementingPartner'] : null,
		'funding_source' 									=> isset($_POST['fundingSource']) ? $_POST['fundingSource'] : null,
		'mother_id' 										=> isset($_POST['mothersId']) ? $_POST['mothersId'] : null,
		'caretaker_contact_consent' 						=> isset($_POST['caretakerConsentForContact']) ? $_POST['caretakerConsentForContact'] : null,
		'caretaker_phone_number' 							=> isset($_POST['caretakerPhoneNumber']) ? $_POST['caretakerPhoneNumber'] : null,
		'caretaker_address' 								=> isset($_POST['caretakerAddress']) ? $_POST['caretakerPhoneNumber'] : null,
		'mother_name' 										=> isset($_POST['mothersName']) ? $_POST['mothersName'] : null,
		'mother_dob' 										=> isset($_POST['mothersDob']) ? $_POST['mothersDob'] : null,
		'mother_marital_status' 							=> isset($_POST['mothersMaritalStatus']) ? $_POST['mothersMaritalStatus'] : null,
		'mother_treatment' 									=> isset($_POST['motherTreatment']) ? implode(",", $_POST['motherTreatment']) : null,
		'mother_treatment_other' 							=> isset($_POST['motherTreatmentOther']) ? $_POST['motherTreatmentOther'] : null,
		'mother_treatment_initiation_date' 					=> isset($_POST['motherTreatmentInitiationDate']) ? $_POST['motherTreatmentInitiationDate'] : null,
		'child_id' 											=> isset($_POST['childId']) ? $_POST['childId'] : null,
		'child_name' 										=> isset($_POST['childName']) ? $_POST['childName'] : null,
		'child_dob' 										=> isset($_POST['childDob']) ? $_POST['childDob'] : null,
		'child_gender' 										=> isset($_POST['childGender']) ? $_POST['childGender'] : null,
		'child_age' 										=> isset($_POST['childAge']) ? $_POST['childAge'] : null,
		'child_treatment' 									=> isset($_POST['childTreatment']) ? implode(",", $_POST['childTreatment']) : null,
		'child_treatment_other' 							=> isset($_POST['childTreatmentOther']) ? implode(",", $_POST['childTreatmentOther']) : null,
		'mother_cd4'	 									=> isset($_POST['mothercd4']) ? $_POST['mothercd4'] : null,
		'mother_vl_result' 									=> $motherVlResult,
		'mother_hiv_status' 								=> isset($_POST['mothersHIVStatus']) ? $_POST['mothersHIVStatus'] : null,
		'pcr_test_performed_before' 						=> isset($_POST['pcrTestPerformedBefore']) ? $_POST['pcrTestPerformedBefore'] : null,
		'previous_pcr_result' 									=> isset($_POST['prePcrTestResult']) ? $_POST['prePcrTestResult'] : null,
		'last_pcr_date' 									=> isset($_POST['previousPCRTestDate']) ? $_POST['previousPCRTestDate'] : null,
		'reason_for_pcr' 									=> isset($_POST['pcrTestReason']) ? $_POST['pcrTestReason'] : null,
		'sample_requestor_name' 							=> isset($_POST['sampleRequestorName']) ? $_POST['sampleRequestorName'] : null,
		'sample_requestor_phone'							=> isset($_POST['sampleRequestorPhone']) ? $_POST['sampleRequestorPhone'] : null,
		'has_infant_stopped_breastfeeding'					=> isset($_POST['hasInfantStoppedBreastfeeding']) ? $_POST['hasInfantStoppedBreastfeeding'] : null,
		'age_breastfeeding_stopped_in_months' 				=> isset($_POST['ageBreastfeedingStopped']) ? $_POST['ageBreastfeedingStopped'] : null,
		'choice_of_feeding' 								=> isset($_POST['choiceOfFeeding']) ? $_POST['choiceOfFeeding'] : null,
		'is_cotrimoxazole_being_administered_to_the_infant'	=> isset($_POST['isCotrimoxazoleBeingAdministered']) ? $_POST['isCotrimoxazoleBeingAdministered'] : null,
		'specimen_type' 									=> isset($_POST['specimenType']) ? $_POST['specimenType'] : null,
		'sample_collection_date' 							=> isset($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
		'sample_requestor_phone' 							=> isset($_POST['sampleRequestorPhone']) ? $_POST['sampleRequestorPhone'] : null,
		'sample_requestor_name' 							=> isset($_POST['sampleRequestorName']) ? $_POST['sampleRequestorName'] : null,
		'rapid_test_performed' 								=> isset($_POST['rapidTestPerformed']) ? $_POST['rapidTestPerformed'] : null,
		'rapid_test_date' 									=> isset($_POST['rapidtestDate']) ? $_POST['rapidtestDate'] : null,
		'rapid_test_result' 								=> isset($_POST['rapidTestResult']) ? $_POST['rapidTestResult'] : null,
		'sample_received_at_vl_lab_datetime' 				=> isset($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
		'eid_test_platform'                 				=> isset($_POST['eidPlatform']) ? $_POST['eidPlatform'] : null,
		'import_machine_name'               				=> isset($_POST['machineName']) ? $_POST['machineName'] : null,
		'lab_reception_person' 								=> isset($_POST['labReceptionPerson']) ? $_POST['labReceptionPerson'] : null,
		'sample_tested_datetime' 							=> isset($_POST['sampleTestedDateTime']) ? $_POST['sampleTestedDateTime'] : null,
		'is_sample_rejected' 								=> isset($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,
		'result' 											=> isset($_POST['result']) ? $_POST['result'] : null,
		'result_reviewed_by'                				=> (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
		'result_reviewed_datetime'          				=> (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'tested_by' 										=> (isset($_POST['testedBy']) && $_POST['testedBy'] != '') ? $_POST['testedBy'] :  NULL,
		'result_approved_by' 								=> (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  NULL,
		'result_approved_datetime' 							=> (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedOnDateTime'] :  NULL,
		'revised_by' 										=> (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
		'revised_on' 										=> (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $general->getDateTime() : "",
		'reason_for_changing'				  				=> (!empty($_POST['reasonForChanging']) && !empty($_POST['reasonForChanging'])) ? $_POST['reasonForChanging'] : null,
		'result_status' 									=> $status,
		'data_sync' 										=> 0,
		'reason_for_sample_rejection' 						=> isset($_POST['sampleRejectionReason']) ? $_POST['sampleRejectionReason'] : null,
		// 'request_created_by'								=> $_SESSION['userId'],
		'request_created_datetime' 							=> $general->getDateTime(),
		'sample_registered_at_lab' 							=> $general->getDateTime(),
		// 'last_modified_by' 									=> $_SESSION['userId'],
		'last_modified_datetime'							=> $general->getDateTime()
	);
	$lock = $general->getGlobalConfig('lock_approved_eid_samples');
	if ($status == 7 && $lock == 'yes') {
		$eidData['locked'] = 'yes';
	}

	if (isset($_POST['api']) && $_POST['api'] = "yes") {
	} else {
		$eidData['request_created_by'] =  $_SESSION['userId'];
		$eidData['last_modified_by'] =  $_SESSION['userId'];
	}
	// var_dump($eidData);die;
	// if ($sarr['sc_user_type'] == 'remoteuser') {
	//   //$eidData['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] : NULL;
	// } else {
	//   if ($_POST['sampleCodeCol'] != '') {
	//     //$eidData['sample_code'] = (isset($_POST['sampleCodeCol']) && $_POST['sampleCodeCol'] != '') ? $_POST['sampleCodeCol'] : NULL;
	//   } else {
	//     $eidModel = new \Vlsm\Models\Eid();

	//     $sampleCodeKeysJson = $eidModel->generateEIDSampleCode($_POST['provinceCode'], $_POST['sampleCollectionDate']);
	//     $sampleCodeKeys = json_decode($sampleCodeKeysJson, true);
	//     $eidData['sample_code'] = $sampleCodeKeys['sampleCode'];
	//     $eidData['sample_code_key'] = $sampleCodeKeys['sampleCodeKey'];
	//     $eidData['sample_code_format'] = $sampleCodeKeys['sampleCodeFormat'];
	//   }
	// }




	if (isset($_POST['eidSampleId']) && $_POST['eidSampleId'] != '') {
		$db = $db->where('eid_id', $_POST['eidSampleId']);
		$id = $db->update($tableName, $eidData);
	}


	if (isset($_POST['api']) && $_POST['api'] = "yes") {
		$payload = array(
			'status' => 'success',
			'timestamp' => time(),
			'message' => 'Successfully updated.'
		);


		http_response_code(200);
		echo json_encode($payload);
		exit(0);
	} else {
		if ($id > 0) {
			$_SESSION['alertMsg'] = "EID request updated successfully";
			//Add event log
			$eventType = 'eid-edit-request';
			$action = ucwords($_SESSION['userName']) . ' updated EID request data with the Sample ID ' . $_POST['eidSampleId'];
			$resource = 'eid-request-drc';

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
		header("location:/eid/requests/eid-requests.php");
	}
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
