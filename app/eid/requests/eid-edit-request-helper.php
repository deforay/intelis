<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

// echo "<pre>";
// var_dump($_POST);
// die;


$tableName = "form_eid";
$tableName1 = "activity_log";

try {
	//system config
	$systemConfigQuery = "SELECT * from system_config";
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
		$_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
	} else {
		$_POST['sampleCollectionDate'] = null;
	}

	if (isset($_POST['sampleDispatchedDate']) && trim($_POST['sampleDispatchedDate']) != "") {
		$sampleDispatchedDate = explode(" ", $_POST['sampleDispatchedDate']);
		$_POST['sampleDispatchedDate'] = DateUtility::isoDateFormat($sampleDispatchedDate[0]) . " " . $sampleDispatchedDate[1];
	} else {
		$_POST['sampleDispatchedDate'] = null;
	}

	if (isset($_POST['approvedOnDateTime']) && trim($_POST['approvedOnDateTime']) != "") {
		$approvedOnDateTime = explode(" ", $_POST['approvedOnDateTime']);
		$_POST['approvedOnDateTime'] = DateUtility::isoDateFormat($approvedOnDateTime[0]) . " " . $approvedOnDateTime[1];
	} else {
		$_POST['approvedOnDateTime'] = null;
	}



	//Set sample received date
	if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
		$sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
		$_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
	} else {
		$_POST['sampleReceivedDate'] = null;
	}

	if (isset($_POST['sampleTestedDateTime']) && trim($_POST['sampleTestedDateTime']) != "") {
		$sampleTestedDate = explode(" ", $_POST['sampleTestedDateTime']);
		$_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
	} else {
		$_POST['sampleTestedDateTime'] = null;
	}

	if (isset($_POST['rapidtestDate']) && trim($_POST['rapidtestDate']) != "") {
		$rapidtestDate = explode(" ", $_POST['rapidtestDate']);
		$_POST['rapidtestDate'] = DateUtility::isoDateFormat($rapidtestDate[0]) . " " . $rapidtestDate[1];
	} else {
		$_POST['rapidtestDate'] = null;
	}

	if (isset($_POST['childDob']) && trim($_POST['childDob']) != "") {
		$childDob = explode(" ", $_POST['childDob']);
		$_POST['childDob'] = DateUtility::isoDateFormat($childDob[0]) . " " . $childDob[1];
	} else {
		$_POST['childDob'] = null;
	}

	if (isset($_POST['mothersDob']) && trim($_POST['mothersDob']) != "") {
		$mothersDob = explode(" ", $_POST['mothersDob']);
		$_POST['mothersDob'] = DateUtility::isoDateFormat($mothersDob[0]) . " " . $mothersDob[1];
	} else {
		$_POST['mothersDob'] = null;
	}

	if (isset($_POST['motherTreatmentInitiationDate']) && trim($_POST['motherTreatmentInitiationDate']) != "") {
		$motherTreatmentInitiationDate = explode(" ", $_POST['motherTreatmentInitiationDate']);
		$_POST['motherTreatmentInitiationDate'] = DateUtility::isoDateFormat($motherTreatmentInitiationDate[0]) . " " . $motherTreatmentInitiationDate[1];
	} else {
		$_POST['motherTreatmentInitiationDate'] = null;
	}

	if (isset($_POST['newArtRegimen']) && trim($_POST['newArtRegimen']) != "") {
        $artQuery = "SELECT art_id,art_code FROM r_vl_art_regimen where (art_code='" . $_POST['newArtRegimen'] . "' OR art_code='" . strtolower($_POST['newArtRegimen']) . "' OR art_code='" . (strtolower($_POST['newArtRegimen'])) . "')";
        $artResult = $db->rawQuery($artQuery);
        if (!isset($artResult[0]['art_id'])) {
            $data = array(
                'art_code' => $_POST['newArtRegimen'],
                'parent_art' => '1',
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            );
            $result = $db->insert('r_vl_art_regimen', $data);
            $_POST['motherRegimen'] = $_POST['newArtRegimen'];
        } else {
            $_POST['motherRegimen'] = $artResult[0]['art_code'];
        }
    }

	if ($_SESSION['instanceType'] == 'remoteuser') {
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



	if (($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site')) {
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
		$_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
	} else {
		$_POST['reviewedOn'] = null;
	}
	if (isset($_POST['resultDispatchedOn']) && trim($_POST['resultDispatchedOn']) != "") {
		$resultDispatchedOn = explode(" ", $_POST['resultDispatchedOn']);
		$_POST['resultDispatchedOn'] = DateUtility::isoDateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
	} else {
		$_POST['resultDispatchedOn'] = null;
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
		'facility_id' 										=> $_POST['facilityId'] ?? null,
		'province_id' 										=> $_POST['provinceId'] ?? null,
		'lab_id' 											=> $_POST['labId'] ?? null,
		'lab_testing_point' 								=> $_POST['labTestingPoint'] ?? null,
		//'implementing_partner' 								=> !empty($_POST['implementingPartner']) ? $_POST['implementingPartner'] : null,
		//'funding_source' 									=> !empty($_POST['fundingSource']) ? $_POST['fundingSource'] : null,
		'mother_id' 										=> $_POST['mothersId'] ?? null,
		'caretaker_contact_consent' 						=> $_POST['caretakerConsentForContact'] ?? null,
		'caretaker_phone_number' 							=> $_POST['caretakerPhoneNumber'] ?? null,
		'caretaker_address' 								=> isset($_POST['caretakerAddress']) ? $_POST['caretakerPhoneNumber'] : null,
		'mother_name' 										=> $_POST['mothersName'] ?? null,
		'mother_dob' 										=> $_POST['mothersDob'] ?? null,
		'mother_marital_status' 							=> $_POST['mothersMaritalStatus'] ?? null,
		'mother_treatment' 									=> isset($_POST['motherTreatment']) ? implode(",", $_POST['motherTreatment']) : null,
		'mother_regimen' 									=> (isset($_POST['motherRegimen']) && $_POST['motherRegimen'] != '') ? $_POST['motherRegimen'] :  null,
		'mother_treatment_other' 							=> $_POST['motherTreatmentOther'] ?? null,
		'mother_treatment_initiation_date' 					=> $_POST['motherTreatmentInitiationDate'] ?? null,
		'child_id' 											=> $_POST['childId'] ?? null,
		'child_name' 										=> $_POST['childName'] ?? null,
		'child_dob' 										=> $_POST['childDob'] ?? null,
		'child_gender' 										=> $_POST['childGender'] ?? null,
		'child_age' 										=> $_POST['childAge'] ?? null,
		'child_treatment' 									=> isset($_POST['childTreatment']) ? implode(",", $_POST['childTreatment']) : null,
		'child_treatment_other' 							=> $_POST['childTreatmentOther'] ?? null,
		'mother_cd4'	 									=> $_POST['mothercd4'] ?? null,
		'mother_vl_result' 									=> $motherVlResult,
		'mother_hiv_status' 								=> $_POST['mothersHIVStatus'] ?? null,
		//'pcr_test_performed_before' 						=> isset($_POST['pcrTestPerformedBefore']) ? $_POST['pcrTestPerformedBefore'] : null,
		'pcr_test_number' 									=> $_POST['pcrTestNumber'] ?? null,
		'previous_pcr_result' 								=> $_POST['prePcrTestResult'] ?? null,
		'last_pcr_date' 									=> isset($_POST['previousPCRTestDate']) ? DateUtility::isoDateFormat($_POST['previousPCRTestDate']) : null,
		'reason_for_pcr' 									=> $_POST['pcrTestReason'] ?? null,
		'reason_for_repeat_pcr_other' 						=> $_POST['reasonForRepeatPcrOther'] ?? null,
		'sample_requestor_name' 							=> $_POST['sampleRequestorName'] ?? null,
		'sample_requestor_phone'							=> $_POST['sampleRequestorPhone'] ?? null,
		'has_infant_stopped_breastfeeding'					=> $_POST['hasInfantStoppedBreastfeeding'] ?? null,
		'infant_on_pmtct_prophylaxis' 						=> $_POST['infantOnPMTCTProphylaxis'] ?? null,
		'infant_on_ctx_prophylaxis'							=> $_POST['infantOnCTXProphylaxis'] ?? null,
		'age_breastfeeding_stopped_in_months' 				=> $_POST['ageBreastfeedingStopped'] ?? null,
		'choice_of_feeding' 								=> $_POST['choiceOfFeeding'] ?? null,
		'is_cotrimoxazole_being_administered_to_the_infant'	=> $_POST['isCotrimoxazoleBeingAdministered'] ?? null,
		'specimen_type' 									=> $_POST['specimenType'] ?? null,
		'sample_collection_date' 							=> $_POST['sampleCollectionDate'] ?? null,
		'sample_dispatched_datetime' 						=> $_POST['sampleDispatchedDate'] ?? null,
		'rapid_test_performed' 								=> $_POST['rapidTestPerformed'] ?? null,
		'rapid_test_date' 									=> $_POST['rapidtestDate'] ?? null,
		'rapid_test_result' 								=> $_POST['rapidTestResult'] ?? null,
		'sample_received_at_vl_lab_datetime' 				=> $_POST['sampleReceivedDate'] ?? null,
		'eid_test_platform'                 				=> $_POST['eidPlatform'] ?? null,
		'import_machine_name'               				=> $_POST['machineName'] ?? null,
		'lab_reception_person' 								=> $_POST['labReceptionPerson'] ?? null,
		'sample_tested_datetime' 							=> $_POST['sampleTestedDateTime'] ?? null,
		'is_sample_rejected' 								=> $_POST['isSampleRejected'] ?? null,
		'result' 											=> $_POST['result'] ?? null,
		'result_reviewed_by'                				=> (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
		'result_reviewed_datetime'          				=> (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'result_dispatched_datetime'          				=> (isset($_POST['resultDispatchedOn']) && $_POST['resultDispatchedOn'] != "") ? $_POST['resultDispatchedOn'] : null,
		'tested_by' 										=> (isset($_POST['testedBy']) && $_POST['testedBy'] != '') ? $_POST['testedBy'] :  null,
		'lab_tech_comments' 									=> (isset($_POST['labTechCmt']) && $_POST['labTechCmt'] != '') ? $_POST['labTechCmt'] :  null,
		'result_approved_by' 								=> (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  null,
		'result_approved_datetime' 							=> (isset($_POST['approvedOnDateTime']) && $_POST['approvedOnDateTime'] != '') ? $_POST['approvedOnDateTime'] :  null,
		'revised_by' 										=> (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : null,
		'revised_on' 										=> (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
		'reason_for_changing'				  				=> (isset($_POST['reasonForChanging']) && !empty($_POST['reasonForChanging'])) ? $_POST['reasonForChanging'] : null,
		'result_status' 									=> $status,
		'data_sync' 										=> 0,
		'reason_for_sample_rejection' 						=> $_POST['sampleRejectionReason'] ?? null,
		'rejection_on' 						                => isset($_POST['rejectionDate']) ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
		// 'request_created_by'								=> $_SESSION['userId'],
		'request_created_datetime' 							=> DateUtility::getCurrentDateTime(),
		'sample_registered_at_lab' 							=> DateUtility::getCurrentDateTime(),
		// 'last_modified_by' 									=> $_SESSION['userId'],
		'last_modified_datetime'							=> DateUtility::getCurrentDateTime()
	);

	if (isset($_POST['api']) && $_POST['api'] == "yes") {
	} else {
		$eidData['request_created_by'] =  $_SESSION['userId'];
		$eidData['last_modified_by'] =  $_SESSION['userId'];
	}
	// if ($_SESSION['instanceType'] == 'remoteuser') {
	//   //$eidData['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] : null;
	// } else {
	//   if ($_POST['sampleCodeCol'] != '') {
	//     //$eidData['sample_code'] = (isset($_POST['sampleCodeCol']) && $_POST['sampleCodeCol'] != '') ? $_POST['sampleCodeCol'] : null;
	//   } else {
	//     $eidService = new \App\Services\EidService();

	//     $sampleCodeKeysJson = $eidService->generateEIDSampleCode($_POST['provinceCode'], $_POST['sampleCollectionDate']);
	//     $sampleCodeKeys = json_decode($sampleCodeKeysJson, true);
	//     $eidData['sample_code'] = $sampleCodeKeys['sampleCode'];
	//     $eidData['sample_code_key'] = $sampleCodeKeys['sampleCodeKey'];
	//     $eidData['sample_code_format'] = $sampleCodeKeys['sampleCodeFormat'];
	//   }
	// }




	if (isset($_POST['eidSampleId']) && $_POST['eidSampleId'] != '') {
		$db = $db->where('eid_id', $_POST['eidSampleId']);
		$id = $db->update($tableName, $eidData);
		error_log($db->getLastError());
	}


	if (isset($_POST['api']) && $_POST['api'] == "yes") {
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
			$_SESSION['alertMsg'] = _("EID request updated successfully");
			//Add event log
			$eventType = 'eid-edit-request';
			$action = $_SESSION['userName'] . ' updated EID request for the Child ID ' . $_POST['childId'];
			$resource = 'eid-request';

			$general->activityLog($eventType, $action, $resource);

			// $data=array(
			// 'event_type'=>$eventType,
			// 'action'=>$action,
			// 'resource'=>$resource,
			// 'date_time'=>\App\Utilities\DateUtility::getCurrentDateTime()
			// );
			// $db->insert($tableName1,$data);

		} else {
			$_SESSION['alertMsg'] = _("Please try again later");
		}
		header("Location:/eid/requests/eid-requests.php");
	}
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
