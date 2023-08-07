<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// echo "<pre>";
// var_dump($_POST);die;


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

	if (empty($instanceId) && $_POST['instanceId']) {
		$instanceId = $_POST['instanceId'];
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

	if (isset($_POST['startedArtDate']) && trim($_POST['startedArtDate']) != "") {
		$startedArtDate = explode(" ", $_POST['startedArtDate']);
		$_POST['startedArtDate'] = DateUtility::isoDateFormat($startedArtDate[0]) . " " . $startedArtDate[1];
	} else {
		$_POST['startedArtDate'] = null;
	}

	if (isset($_POST['test1Date']) && trim($_POST['test1Date']) != "") {
		$test1Date = explode(" ", $_POST['test1Date']);
		$_POST['test1Date'] = DateUtility::isoDateFormat($test1Date[0]) . " " . $test1Date[1];
	} else {
		$_POST['test1Date'] = null;
	}

	if (isset($_POST['test2Date']) && trim($_POST['test2Date']) != "") {
		$test2Date = explode(" ", $_POST['test2Date']);
		$_POST['test2Date'] = DateUtility::isoDateFormat($test2Date[0]) . " " . $test2Date[1];
	} else {
		$_POST['test2Date'] = null;
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

	if (isset($_POST['childTreatmentInitiationDate']) && trim($_POST['childTreatmentInitiationDate']) != "") {
		$childTreatmentInitiationDate = explode(" ", $_POST['childTreatmentInitiationDate']);
		$_POST['childTreatmentInitiationDate'] = DateUtility::isoDateFormat($childTreatmentInitiationDate[0]) . " " . $childTreatmentInitiationDate[1];
	} else {
		$_POST['childTreatmentInitiationDate'] = null;
	}

	if (isset($_POST['nextAppointmentDate']) && trim($_POST['nextAppointmentDate']) != "") {
		$nextAppointmentDate = explode(" ", $_POST['nextAppointmentDate']);
		$_POST['nextAppointmentDate'] = DateUtility::isoDateFormat($nextAppointmentDate[0]) . " " . $nextAppointmentDate[1];
	} else {
		$_POST['nextAppointmentDate'] = null;
	}

	if (isset($_POST['childStartedCotrimDate']) && trim($_POST['childStartedCotrimDate']) != "") {
		$nextAppointmentDate = explode(" ", $_POST['childStartedCotrimDate']);
		$_POST['childStartedCotrimDate'] = DateUtility::isoDateFormat($childStartedCotrimDate[0]) . " " . $childStartedCotrimDate[1];
	} else {
		$_POST['childStartedCotrimDate'] = null;
	}

	if (isset($_POST['childStartedArtDate']) && trim($_POST['childStartedArtDate']) != "") {
		$nextAppointmentDate = explode(" ", $_POST['childStartedArtDate']);
		$_POST['childStartedArtDate'] = DateUtility::isoDateFormat($childStartedArtDate[0]) . " " . $childStartedArtDate[1];
	} else {
		$_POST['childStartedArtDate'] = null;
	}

	if (isset($_POST['dateOfWeaning']) && trim($_POST['dateOfWeaning']) != "") {
		$nextAppointmentDate = explode(" ", $_POST['dateOfWeaning']);
		$_POST['dateOfWeaning'] = DateUtility::isoDateFormat($dateOfWeaning[0]) . " " . $dateOfWeaning[1];
	} else {
		$_POST['dateOfWeaning'] = null;
	}

	if (!empty($_POST['newRejectionReason'])) {
        $rejectionReasonQuery = "SELECT rejection_reason_id
                    FROM r_eid_sample_rejection_reasons
                    WHERE rejection_reason_name like ?";
        $rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
        if (empty($rejectionResult)) {
            $data = array(
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active',
                'updated_datetime' => DateUtility::getCurrentDateTime()
            );
            $id = $db->insert('r_eid_sample_rejection_reasons', $data);
            $_POST['sampleRejectionReason'] = $id;
        } else {
            $_POST['sampleRejectionReason'] = $rejectionResult['rejection_reason_id'];
        }
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

	if (isset($_POST['previousPCRTestDate']) && trim($_POST['previousPCRTestDate']) != "") {
		$previousPCRTestDate = explode(" ", $_POST['previousPCRTestDate']);
		$_POST['previousPCRTestDate'] = DateUtility::isoDateFormat($previousPCRTestDate[0]) . " " . $previousPCRTestDate[1];
	} else {
		$_POST['previousPCRTestDate'] = null;
	}

	if (!isset($_POST['sampleCode']) || trim($_POST['sampleCode']) == '') {
		$_POST['sampleCode'] = null;
	}

	if (isset($_POST['motherViralLoadCopiesPerMl']) && $_POST['motherViralLoadCopiesPerMl'] != "") {
		$motherVlResult = $_POST['motherViralLoadCopiesPerMl'];
	} elseif (isset($_POST['motherViralLoadText']) && $_POST['motherViralLoadText'] != "") {
		$motherVlResult = $_POST['motherViralLoadText'];
	} else {
		$motherVlResult = null;
	}

	$status = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
	if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
		$status = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
	}

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['result'] = null;
		$status = SAMPLE_STATUS\REJECTED;
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

	if (isset($_POST['approvedOnDateTime']) && trim($_POST['approvedOnDateTime']) != "") {
		$approvedOnDateTime = explode(" ", $_POST['approvedOnDateTime']);
		$_POST['approvedOnDateTime'] = DateUtility::isoDateFormat($approvedOnDateTime[0]) . " " . $approvedOnDateTime[1];
	} else {
		$_POST['approvedOnDateTime'] = null;
	}

	$eidData = array(
		'vlsm_instance_id' => $instanceId,
		'vlsm_country_id' => $_POST['formId'],
		'facility_id' => $_POST['facilityId'] ?? null,
		'province_id' => $_POST['provinceId'] ?? null,
		'lab_id' => $_POST['labId'] ?? null,
		'lab_testing_point' => $_POST['labTestingPoint'] ?? null,
		'implementing_partner' => !empty($_POST['implementingPartner']) ? $_POST['implementingPartner'] : null,
		'funding_source' => !empty($_POST['fundingSource']) ? $_POST['fundingSource'] : null,
		'mother_id' => $_POST['mothersId'] ?? null,
		'caretaker_contact_consent' => $_POST['caretakerConsentForContact'] ?? null,
		'caretaker_phone_number' => $_POST['caretakerPhoneNumber'] ?? null,
		'caretaker_address' => $_POST['caretakerAddress'] ?? null,
		'previous_sample_code' => $_POST['previousSampleCode'] ?? null,
		'clinical_assessment' => $_POST['clinicalAssessment'] ?? null,
		'clinician_name' => $_POST['clinicianName'] ?? null,
		'mother_name' => $_POST['mothersName'] ?? null,
		'mother_surname' => $_POST['mothersSurname'] ?? null,
		'mother_dob' => $_POST['mothersDob'] ?? null,
		'mother_marital_status' => $_POST['mothersMaritalStatus'] ?? null,
		'mother_treatment' => is_array($_POST['motherTreatment']) ? implode(",", $_POST['motherTreatment']) : $_POST['motherTreatment'] ?? null,
		'mother_regimen' => (isset($_POST['motherRegimen']) && $_POST['motherRegimen'] != '') ? $_POST['motherRegimen'] : null,
		'mother_treatment_other' => $_POST['motherTreatmentOther'] ?? null,
		'next_appointment_date' => $_POST['nextAppointmentDate'] ?? null,
		'no_of_exposed_children' => $_POST['noOfExposedChildren'] ?? null,
		'no_of_infected_children' => $_POST['noOfInfectedChildren'] ?? null,
		'mother_arv_protocol' => $_POST['motherArvProtocol'] ?? null,
		'mother_treatment_initiation_date' => $_POST['motherTreatmentInitiationDate'] ?? null,
		'child_id' => $_POST['childId'] ?? null,
		'child_name' => $_POST['childName'] ?? null,
		'child_dob' => $_POST['childDob'] ?? null,
		'child_gender' => $_POST['childGender'] ?? null,
		'child_age' => $_POST['childAge'] ?? null,
		'child_treatment' => isset($_POST['childTreatment']) ? implode(",", $_POST['childTreatment']) : null,
		'child_treatment_other' => $_POST['childTreatmentOther'] ?? null,
		'child_weight' => $_POST['childWeight'] ?? null,
		'child_prophylactic_arv' => $_POST['childProphylacticArv'] ?? null,
		'child_prophylactic_arv_other' => $_POST['childProphylacticArvOther'] ?? null,
		'child_treatment_initiation_date' => $_POST['childTreatmentInitiationDate'] ?? null,
		'mother_cd4' => $_POST['mothercd4'] ?? null,
		'mother_vl_result' => $motherVlResult,
		'mother_hiv_status' => $_POST['mothersHIVStatus'] ?? null,
		'mode_of_delivery' => $_POST['modeOfDelivery'] ?? null,
		'mode_of_delivery_other' => $_POST['modeOfDeliveryOther'] ?? null,
		'mother_art_status' => $_POST['motherArtStatus'] ?? null,
		'mother_mtct_risk' => $_POST['motherMtctRisk'] ?? null,
		'started_art_date' => $_POST['startedArtDate'] ?? null,
		'is_child_symptomatic' => $_POST['isChildSymptomatic'] ?? null,
		'date_of_weaning' => $_POST['dateOfWeaning'] ?? null,
		'was_child_breastfed' => $_POST['wasChildBreastfed'] ?? null,
		'is_child_on_cotrim' => $_POST['isChildOnCotrim'] ?? null,
		'child_started_cotrim_date' => $_POST['childStartedCotrimDate'] ?? null,
		'child_started_art_date' => $_POST['childStartedArtDate'] ?? null,
		'sample_collection_reason' => $_POST['sampleCollectionReason'] ?? null,
		'pcr_test_performed_before' => $_POST['pcrTestPerformedBefore'] ?? null,
		'pcr_test_number' => $_POST['pcrTestNumber'] ?? null,
		'previous_pcr_result' => $_POST['prePcrTestResult'] ?? null,
		'last_pcr_date' => $_POST['previousPCRTestDate'] ?? null,
		'reason_for_pcr' => $_POST['pcrTestReason'] ?? null,
		'reason_for_repeat_pcr_other' => $_POST['reasonForRepeatPcrOther'] ?? null,
		'has_infant_stopped_breastfeeding' => $_POST['hasInfantStoppedBreastfeeding'] ?? null,
		'infant_on_pmtct_prophylaxis' => $_POST['infantOnPMTCTProphylaxis'] ?? null,
		'infant_on_ctx_prophylaxis' => $_POST['infantOnCTXProphylaxis'] ?? null,
		'age_breastfeeding_stopped_in_months' => $_POST['ageBreastfeedingStopped'] ?? null,
		'infant_art_status' => $_POST['infantArtStatus'] ?? null,
		'infant_art_status_other' => $_POST['infantArtStatusOther'] ?? null,
		'choice_of_feeding' => $_POST['choiceOfFeeding'] ?? null,
		'is_cotrimoxazole_being_administered_to_the_infant' => $_POST['isCotrimoxazoleBeingAdministered'] ?? null,
		'specimen_type' => $_POST['specimenType'] ?? null,
		'sample_collection_date' => $_POST['sampleCollectionDate'] ?? null,
		'sample_dispatched_datetime' => $_POST['sampleDispatchedDate'] ?? null,
		'sample_requestor_phone' => $_POST['sampleRequestorPhone'] ?? null,
		'sample_requestor_name' => $_POST['sampleRequestorName'] ?? null,
		'rapid_test_performed' => $_POST['rapidTestPerformed'] ?? null,
		'rapid_test_date' => $_POST['rapidtestDate'] ?? null,
		'rapid_test_result' => $_POST['rapidTestResult'] ?? null,
		'lab_reception_person' => $_POST['labReceptionPerson'] ?? null,
		'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedDate'] ?? null,
		'eid_test_platform' => $_POST['eidPlatform'] ?? null,
		'import_machine_name' => $_POST['machineName'] ?? null,
		'sample_tested_datetime' => $_POST['sampleTestedDateTime'] ?? null,
		'is_sample_rejected' => $_POST['isSampleRejected'] ?? null,
		'recommended_corrective_action' => $_POST['correctiveAction'] ?? null,
		'result' => $_POST['result'] ?? null,
		'test_1_date' => $_POST['test1Date'] ?? null,
		'test_1_batch' => $_POST['test1Batch'] ?? null,
		'test_1_assay' => $_POST['test1Assay'] ?? null,
		'test_1_ct_qs' => $_POST['test1CtQs'] ?? null,
		'test_1_result' => $_POST['test1Result'] ?? null,
		'test_1_repeated' => $_POST['test1Repeated'] ?? null,
		'test_1_repeat_reason' => $_POST['test1RepeatReason'] ?? null,
		'test_2_date' => $_POST['test2Date'] ?? null,
		'test_2_batch' => $_POST['test2Batch'] ?? null,
		'test_2_assay' => $_POST['test2Assay'] ?? null,
		'test_2_ct_qs' => $_POST['test2CtQs'] ?? null,
		'test_2_result' => $_POST['test2Result'] ?? null,
		'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
		'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'result_dispatched_datetime' => (isset($_POST['resultDispatchedOn']) && $_POST['resultDispatchedOn'] != "") ? $_POST['resultDispatchedOn'] : null,
		'tested_by' => (isset($_POST['testedBy']) && $_POST['testedBy'] != '') ? $_POST['testedBy'] : null,
		'lab_tech_comments' => (isset($_POST['labTechCmt']) && $_POST['labTechCmt'] != '') ? $_POST['labTechCmt'] : null,
		'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] : null,
		'result_approved_datetime' => $_POST['approvedOnDateTime'] ?? null,
		'result_status' => $status,
		'second_dbs_requested' => (isset($_POST['secondDBSRequested']) && $_POST['secondDBSRequested'] != '') ? $_POST['secondDBSRequested'] : null,
		'second_dbs_requested_reason' => (isset($_POST['secondDBSRequestedReason']) && $_POST['secondDBSRequestedReason'] != '') ? $_POST['secondDBSRequestedReason'] : null,
		'data_sync' => 0,
		'reason_for_sample_rejection' => $_POST['sampleRejectionReason'] ?? null,
		'rejection_on' => isset($_POST['rejectionDate']) ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
		// 'request_created_by' 								=> $_SESSION['userId'],
		'request_created_datetime' => DateUtility::getCurrentDateTime(),
		'sample_registered_at_lab' => DateUtility::getCurrentDateTime(),
		// 'last_modified_by' 									=> $_SESSION['userId'],
		'last_modified_datetime' => DateUtility::getCurrentDateTime()
	);

	if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == "vluser" || $sarr['sc_user_type'] == "standalone")) {
		$eidData['source_of_request'] = 'vlsm';
	} elseif (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == "remoteuser")) {
		$eidData['source_of_request'] = 'vlsts';
	}

	$eidData['request_created_by'] = $_SESSION['userId'] ?? $_POST['userId'] ?? null;
	$eidData['last_modified_by'] = $_SESSION['userId'] ?? $_POST['userId'] ?? null;


	if (isset($_POST['eidSampleId']) && $_POST['eidSampleId'] != '') {
		$db = $db->where('eid_id', $_POST['eidSampleId']);
		$id = $db->update($tableName, $eidData);
		error_log($db->getLastError());
	}

	if ($id === true) {
		$_SESSION['alertMsg'] = _("EID request added successfully");
		//Add event log
		$eventType = 'add-eid-request';
		$action = $_SESSION['userName'] . ' added a new EID request for the Child ID ' . $_POST['childId'];
		$resource = 'eid-request';

		$general->activityLog($eventType, $action, $resource);
	} else {
		$_SESSION['alertMsg'] = _("Please try again later");
	}
	if (isset($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
		header("Location:/eid/requests/eid-add-request.php");
	} else {
		header("Location:/eid/requests/eid-requests.php");
	}
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
