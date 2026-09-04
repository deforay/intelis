<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;
use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\PatientsService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Utilities\SampleStatusUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var PatientsService $patientsService */
$patientsService = ContainerRegistry::get(PatientsService::class);

// Shared with VL and CD4: the mother's ARV regimen is stored in r_vl_art_regimen, and
// VlService::resolveArtRegimen() is the single path that registers a value there.
/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$tableName = "form_eid";
$tableName1 = "activity_log";

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

// Which result fields this form actually sent. Taken before anything below can add
// a key, so an absent field cannot look present. Writing a result column the form
// never rendered blanks whatever was already there -- the defect fixed for Custom
// Tests in b20503799 and for TB in 2a7c80af3. Keyed on what was posted rather than
// on a country list, so a new form gets this right without anyone remembering.
$resultColumnsOwnedByTheForm = [
    'sample_tested_datetime'     => 'sampleTestedDateTime',
    'result'                     => 'result',
    'result_reviewed_by'         => 'reviewedBy',
    'result_reviewed_datetime'   => 'reviewedOn',
    'result_dispatched_datetime' => 'resultDispatchedOn',
    'tested_by'                  => 'testedBy',
    'lab_tech_comments'          => 'labTechCmt',
    'result_approved_by'         => 'approvedBy',
    'result_approved_datetime'   => 'approvedOnDateTime',
];
$resultColumnsPosted = [];
foreach ($resultColumnsOwnedByTheForm as $column => $postKey) {
    if (array_key_exists($postKey, (array) $_POST)) {
        $resultColumnsPosted[] = $column;
    }
}


try {
	// Acting as a LIS: the Testing Lab is this install's own lab, not a free
	// choice. The forms already constrain the dropdown, but AJAX endpoints
	// bypass ACL, so the rule only holds if it holds here. No-op on STS and
	// standalone installs, and on any LIS session with no resolved lab.
	$_POST['labId'] = $general->resolveRequestLabId($_POST['labId'] ?? null, $tableName, 'eid_id', $_POST['eidSampleId'] ?? null);

	$db->where('eid_id', $_POST['eidSampleId'] ?? 0);
	$sampleFacilityId = (int) ($db->getValue($tableName, 'facility_id') ?? 0);
	$general->assertFacilityAllowed($sampleFacilityId);
	if (!empty($_POST['facilityId'])) {
		$general->assertFacilityAllowed((int) $_POST['facilityId']);
	}

	$instanceId = '';
	if (isset($_SESSION['instanceId'])) {
		$instanceId = $_SESSION['instanceId'];
	}

	$_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($_POST['sampleCollectionDate'] ?? '', true);
	$_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($_POST['sampleReceivedDate'] ?? '', true);
	$_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($_POST['sampleTestedDateTime'] ?? '', true);
	$_POST['rapidtestDate'] = DateUtility::isoDateFormat($_POST['rapidtestDate'] ?? '');
	$_POST['startedArtDate'] = DateUtility::isoDateFormat($_POST['startedArtDate'] ?? '');
	$_POST['motherHivTestDate'] = DateUtility::isoDateFormat($_POST['motherHivTestDate'] ?? '');
	$_POST['test1Date'] = DateUtility::isoDateFormat($_POST['test1Date'] ?? '');
	$_POST['test2Date'] = DateUtility::isoDateFormat($_POST['test2Date'] ?? '');
	$_POST['childDob'] = DateUtility::isoDateFormat($_POST['childDob'] ?? '');
	$_POST['mothersDob'] = DateUtility::isoDateFormat($_POST['mothersDob'] ?? '');
	$_POST['motherTreatmentInitiationDate'] = DateUtility::isoDateFormat($_POST['motherTreatmentInitiationDate'] ?? '');
	$_POST['childTreatmentInitiationDate'] = DateUtility::isoDateFormat($_POST['childTreatmentInitiationDate'] ?? '');
	$_POST['nextAppointmentDate'] = DateUtility::isoDateFormat($_POST['nextAppointmentDate'] ?? '');
	$_POST['childStartedCotrimDate'] = DateUtility::isoDateFormat($_POST['childStartedCotrimDate'] ?? '');
	$_POST['childStartedArtDate'] = DateUtility::isoDateFormat($_POST['childStartedArtDate'] ?? '');
	$_POST['pcr1TestDate'] = DateUtility::isoDateFormat($_POST['pcr1TestDate'] ?? '');
	$_POST['pcr2TestDate'] = DateUtility::isoDateFormat($_POST['pcr2TestDate'] ?? '');
	$_POST['pcr3TestDate'] = DateUtility::isoDateFormat($_POST['pcr3TestDate'] ?? '');
	$_POST['dateOfWeaning'] = DateUtility::isoDateFormat($_POST['dateOfWeaning'] ?? '');
	$_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true);
	$_POST['resultDispatchedOn'] = DateUtility::isoDateFormat($_POST['resultDispatchedOn'] ?? '', true);
	$_POST['approvedOnDateTime'] = DateUtility::isoDateFormat($_POST['approvedOnDateTime'] ?? '', true);


	if (!empty($_POST['newRejectionReason'])) {
		$rejectionReasonQuery = "SELECT rejection_reason_id
                    FROM r_eid_sample_rejection_reasons
                    WHERE rejection_reason_name like ?";
		$rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
		if (empty($rejectionResult)) {
			$data = [
				'rejection_reason_name' => $_POST['newRejectionReason'],
				'rejection_type' => 'general',
				'rejection_reason_status' => 'active',
				'updated_datetime' => DateUtility::getCurrentDateTime()
			];
			$id = $db->insert('r_eid_sample_rejection_reasons', $data);
			$_POST['sampleRejectionReason'] = $id;
		} else {
			$_POST['sampleRejectionReason'] = $rejectionResult['rejection_reason_id'];
		}
	}


	// The "other" box for the mother's regimen. EID shares r_vl_art_regimen with VL and
	// CD4, so this resolves through the same path as those helpers and the API.
	//
	// The lookup this replaces interpolated $_POST['newArtRegimen'] directly into the
	// query string, three times, unescaped -- a SQL injection reachable by anyone who can
	// edit an EID request. resolveArtRegimen() binds the value as a parameter.
	//
	// The three OR'd comparisons it also drops were redundant: two of them were the same
	// strtolower() expression, and art_code is matched under a case-insensitive collation
	// in any case, so lowercasing changed nothing.
	if (isset($_POST['newArtRegimen']) && trim((string) $_POST['newArtRegimen']) !== "") {
		$_POST['motherRegimen'] = $vlService->resolveArtRegimen($_POST['newArtRegimen'], 'form');
	}

	if ($general->isSTSInstance()) {
		$sampleCode = 'remote_sample_code';
		$sampleCodeKey = 'remote_sample_code_key';
	} else {
		$sampleCode = 'sample_code';
		$sampleCodeKey = 'sample_code_key';
	}


	if (isset($_POST['motherViralLoadCopiesPerMl']) && $_POST['motherViralLoadCopiesPerMl'] != "") {
		$motherVlResult = $_POST['motherViralLoadCopiesPerMl'];
	} elseif (isset($_POST['motherViralLoadText']) && $_POST['motherViralLoadText'] != "") {
		$motherVlResult = $_POST['motherViralLoadText'];
	} else {
		$motherVlResult = null;
	}



	if (($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site')) {
		$status = RECEIVED_AT_CLINIC;
	}

	if (!empty($_POST['oldStatus'])) {
		$status = $_POST['oldStatus'];
	}

	if ($general->isLISInstance() && $_POST['oldStatus'] == RECEIVED_AT_CLINIC) {
		$status = RECEIVED_AT_TESTING_LAB;
	}

	if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
		$_POST['result'] = null;
		$status = REJECTED;
	}

	if ($general->isSTSInstance() && $_POST['oldStatus'] == RECEIVED_AT_CLINIC) {
		$_POST['status'] = RECEIVED_AT_CLINIC;
	} elseif ($general->isLISInstance() && $_POST['oldStatus'] == RECEIVED_AT_CLINIC) {
		$_POST['status'] = RECEIVED_AT_TESTING_LAB;
	}
	if ($_POST['status'] == '') {
		$_POST['status'] = $_POST['oldStatus'];
	}

	$testingPlatform = null;
	$instrumentId = null;
	if (isset($_POST['eidPlatform']) && trim((string) $_POST['eidPlatform']) !== '') {
		$platForm = explode("##", (string) $_POST['eidPlatform']);
		$testingPlatform = $platForm[0];
		$instrumentId = $platForm[1];
	}

	//Update patient Information in Patients Table
	//$systemPatientCode = $patientsService->savePatient($_POST, 'form_eid');

	//$systemGeneratedCode = $patientsService->getSystemPatientId($_POST['childId'], $_POST['childGender'], DateUtility::isoDateFormat($_POST['childDob'] ?? ''));


	$eidData = [
		'facility_id' => $_POST['facilityId'] ?? null,
		'province_id' => $_POST['provinceId'] ?? null,
		// 'lab_assigned_code' is set below, only when the field was actually
		// submitted, so non-LIS forms (where the input isn't rendered) don't
		// wipe the existing lab code on every request edit.
		'lab_id' => $_POST['labId'] ?? null,
		'lab_testing_point' => $_POST['labTestingPoint'] ?? null,
		//'system_patient_code' => $systemPatientCode,
		'funding_source' => (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) !== '') ? base64_decode((string) $_POST['fundingSource']) : null,
		'implementing_partner' => (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) !== '') ? base64_decode((string) $_POST['implementingPartner']) : null,
		'mother_id' => $_POST['mothersId'] ?? null,
		'caretaker_contact_consent' => $_POST['caretakerConsentForContact'] ?? null,
		'caretaker_phone_number' => $_POST['caretakerPhoneNumber'] ?? null,
		'caretaker_address' => $_POST['caretakerAddress'] ?? null,
		'previous_sample_code' => $_POST['previousSampleCode'] ?? null,
		'clinical_assessment' => $_POST['clinicalAssessment'] ?? null,
		'clinician_name' => $_POST['clinicianName'] ?? null,
		'request_clinician_phone_number' => $_POST['reqClinicianPhoneNumber'] ?? null,
		'is_mother_alive' => $_POST['isMotherAlive'] ?? null,
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
		'health_insurance_code' => $_POST['healthInsuranceCode'] ?? null,
		'child_age' => $_POST['childAge'] ?? null,
		'child_age_in_weeks' => $_POST['childAgeInWeeks'] ?? null,
		'child_treatment' => isset($_POST['childTreatment']) ? implode(",", $_POST['childTreatment']) : null,
		'child_treatment_other' => $_POST['childTreatmentOther'] ?? null,
		'child_weight' => $_POST['childWeight'] ?? null,
		'child_prophylactic_arv' => $_POST['childProphylacticArv'] ?? null,
		'child_prophylactic_arv_other' => $_POST['childProphylacticArvOther'] ?? null,
		'child_treatment_initiation_date' => $_POST['childTreatmentInitiationDate'] ?? null,
		'child_age_in_days' => $_POST['childAgeInDays'] ?? null,
		'test_request_date' => $_POST['testRequestDate'] ?? null,
		'infant_email' => $_POST['email'] ?? null,
		'infant_phone' => $_POST['phone'] ?? null,
		'is_infant_receiving_treatment' => $_POST['isInfantReceivingTratment'] ?? null,
		'specific_infant_treatment' => $_POST['specificInfantTreatment'] ?? null,
		'mother_cd4' => $_POST['mothercd4'] ?? null,
		'mother_vl_result' => $motherVlResult,
		'mother_hiv_test_date' => $_POST['motherHivTestDate'] ?? null,
		'mother_hiv_status' => $_POST['mothersHIVStatus'] ?? null,
		'mode_of_delivery' => $_POST['modeOfDelivery'] ?? null,
		'mode_of_delivery_other' => $_POST['modeOfDeliveryOther'] ?? null,
		'mother_art_status' => $_POST['motherArtStatus'] ?? null,
		'pcr_1_test_date' => $_POST['pcr1TestDate'] ?? null,
		'pcr_2_test_date' => $_POST['pcr2TestDate'] ?? null,
		'pcr_3_test_date' => $_POST['pcr3TestDate'] ?? null,
		'pcr_1_test_result' => $_POST['pcr1TestResult'] ?? null,
		'pcr_2_test_result' => $_POST['pcr2TestResult'] ?? null,
		'pcr_3_test_result' => $_POST['pcr3TestResult'] ?? null,
		'serological_test' => $_POST['serologicalTest'] ?? null,
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
		'last_pcr_date' => isset($_POST['previousPCRTestDate']) ? DateUtility::isoDateFormat($_POST['previousPCRTestDate']) : null,
		'reason_for_pcr' => $_POST['pcrTestReason'] ?? null,
		'reason_for_repeat_pcr_other' => $_POST['reasonForRepeatPcrOther'] ?? null,
		'sample_requestor_name' => $_POST['sampleRequestorName'] ?? null,
		'sample_requestor_phone' => $_POST['sampleRequestorPhone'] ?? null,
		'sample_dispatcher_phone' => $_POST['sampleDispatcherPhone'] ?? null,
		'sample_dispatcher_name' => $_POST['sampleDispatcherName'] ?? null,
		'has_infant_stopped_breastfeeding' => $_POST['hasInfantStoppedBreastfeeding'] ?? null,
		'infant_on_pmtct_prophylaxis' => $_POST['infantOnPMTCTProphylaxis'] ?? null,
		'infant_on_ctx_prophylaxis' => $_POST['infantOnCTXProphylaxis'] ?? null,
		'age_breastfeeding_stopped_in_months' => $_POST['ageBreastfeedingStopped'] ?? null,
		'infant_art_status' => $_POST['infantArtStatus'] ?? null,
		'infant_art_status_other' => $_POST['infantArtStatusOther'] ?? null,
		'choice_of_feeding' => $_POST['choiceOfFeeding'] ?? null,
		'is_cotrimoxazole_being_administered_to_the_infant' => $_POST['isCotrimoxazoleBeingAdministered'] ?? null,
		'specimen_type' => $_POST['specimenType'] ?? null,
		'location_of_sample_collection' => $_POST['locationOfSampleCollection'] ?? null,
		'sample_collection_date' => $_POST['sampleCollectionDate'] ?? null,
		'is_sample_recollected' => $_POST['isSampleRecollected'] ?? null,
		'sample_dispatched_datetime' => $_POST['sampleDispatchedDate'] ?? null,
		'rapid_test_performed' => $_POST['rapidTestPerformed'] ?? null,
		'rapid_test_date' => $_POST['rapidtestDate'] ?? null,
		'rapid_test_result' => $_POST['rapidTestResult'] ?? null,
		'sample_received_at_lab_datetime' => $_POST['sampleReceivedDate'] ?? null,
		'eid_number' => $_POST['eidNumber'] ?? null,
		'eid_test_platform' => $testingPlatform ?? null,
		'instrument_id' => $instrumentId ?? null,
		'import_machine_name' => $_POST['machineName'] ?? null,
		'lab_reception_person' => $_POST['labReceptionPerson'] ?? null,
		'sample_tested_datetime' => $_POST['sampleTestedDateTime'] ?? null,
		'is_sample_rejected' => ($_POST['isSampleRejected'] ?? null),
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
		'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
		'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
		'result_dispatched_datetime' => (isset($_POST['resultDispatchedOn']) && $_POST['resultDispatchedOn'] != "") ? $_POST['resultDispatchedOn'] : null,
		'tested_by' => (isset($_POST['testedBy']) && $_POST['testedBy'] != '') ? $_POST['testedBy'] : null,
		'lab_tech_comments' => (isset($_POST['labTechCmt']) && $_POST['labTechCmt'] != '') ? $_POST['labTechCmt'] : null,
		'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] : null,
		'result_approved_datetime' => $_POST['approvedOnDateTime'] ?? null,
		'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : null,
		'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
		//'reason_for_changing' => (!empty($_POST['reasonForChanging'])) ? $_POST['reasonForChanging'] : null,
		'result_status' => $status,
		'second_dbs_requested' => (isset($_POST['secondDBSRequested']) && $_POST['secondDBSRequested'] != '') ? $_POST['secondDBSRequested'] : null,
		'second_dbs_requested_reason' => (isset($_POST['secondDBSRequestedReason']) && $_POST['secondDBSRequestedReason'] != '') ? $_POST['secondDBSRequestedReason'] : null,
		'data_sync' => 0,
		'reason_for_sample_rejection' => $_POST['sampleRejectionReason'] ?? null,
		'rejection_on' => isset($_POST['rejectionDate']) ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
		'request_created_datetime' => DateUtility::getCurrentDateTime(),
		'last_modified_datetime' => DateUtility::getCurrentDateTime()
	];

	$db->where('eid_id', $_POST['eidSampleId']);
	$getPrevResult = $db->getOne('form_eid');
	// The request edit is not where a result gets removed. A save that carries no
	// result while the status still says Accepted leaves a row that every printing,
	// dispatch and reporting query passes over, because they all read Accepted as
	// "there is a result here". So the stored result stands and the edit keeps its
	// other changes. Rejecting a sample is unaffected: that branch above moves the
	// status off Accepted first, and clearing a result outright belongs to the
	// result page, which owns that column.
	if (
		SampleStatusUtility::assertsAResult($status)
		&& !SampleStatusUtility::rowHasResult('eid', $eidData)
	) {
		$eidData['result'] = $getPrevResult['result'];
		$_POST['result'] = $getPrevResult['result'];
	}
	// The result counts as modified when the value changed or the sample flipped
	// between rejected and not rejected -- the same rule the change history is logged on.
	$previousState = ['result' => $getPrevResult['result'], 'result_status' => $getPrevResult['result_status'], 'is_sample_rejected' => $getPrevResult['is_sample_rejected'] ?? null];
	$currentState = ['result' => $_POST['result'] ?? null, 'is_sample_rejected' => $_POST['isSampleRejected'] ?? null];
	$eidData['result_modified'] = MiscUtility::resultOrRejectionChanged($previousState, $currentState) ? "yes" : "no";

	// Append the change reason (preserving prior history) whenever the result or rejection changed.
	$reasonForChanges = MiscUtility::appendResultChangeReason(
		$getPrevResult['reason_for_changing'] ?? null,
		$_SESSION['userId'] ?? $_POST['userId'] ?? null,
		$_POST['reasonForResultChanges'] ?? $_POST['reasonForChanging'] ?? null,
		$previousState,
		$currentState
	);
	if ($reasonForChanges !== null) {
		$eidData['reason_for_changing'] = $reasonForChanges;
	}
	$eidData['last_modified_by'] = $_SESSION['userId'] ?? $_POST['userId'] ?? null;


	$eidData['is_encrypted'] = 'no';
	if (isset($_POST['encryptPII']) && $_POST['encryptPII'] == 'yes') {
		$key = (string) $general->getGlobalConfig('key');
		$encryptedChildId = $general->crypto('encrypt', $eidData['child_id'], $key);
		$encryptedChildName = $general->crypto('encrypt', $eidData['child_name'], $key);
		$encryptedChildSurName = $general->crypto('encrypt', $eidData['child_surname'], $key);

		$encryptedMotherId = $general->crypto('encrypt', $eidData['mother_id'], $key);
		$encryptedotherName = $general->crypto('encrypt', $eidData['mother_name'], $key);
		$encryptedMotherSurName = $general->crypto('encrypt', $eidData['mother_surname'], $key);

		$eidData['child_id'] = $encryptedChildId;
		$eidData['child_name'] = $encryptedChildName;
		$eidData['child_surname'] = $encryptedChildSurName;

		$eidData['mother_id'] = $encryptedMotherId;
		$eidData['mother_name'] = $encryptedotherName;
		$eidData['mother_surname'] = $encryptedMotherSurName;

		$eidData['is_encrypted'] = 'yes';
	}

	//Update patient Information in Patients Table
	// $patientsService->savePatient($_POST, 'form_eid');

	$formAttributes = [
		'applicationVersion' => $general->getAppVersion(),
		'ip_address' => $general->getClientIpAddress()
	];
	if (isset($_POST['freezer']) && $_POST['freezer'] != "" && $_POST['freezer'] != null) {

		$freezerCheck = $general->getDataFromOneFieldAndValue('lab_storage', 'storage_id', $_POST['freezer']);

		if (empty($freezerCheck)) {
			$storageId = MiscUtility::generateULID();
			$freezerCode = $_POST['freezer'];
			$d = [
				'storage_id' => $storageId,
				'storage_code' => $freezerCode,
				'lab_id' => $_POST['labId'],
				'storage_status' => 'active'
			];
			$db->insert('lab_storage', $d);
		} else {
			$storageId = $_POST['freezer'];
			$freezerCode = $freezerCheck['storage_code'];
		}

		$formAttributes['storage'] = [
			"storageId" => $storageId,
			"storageCode" => $freezerCode,
			"rack" => $_POST['rack'],
			"box" => $_POST['box'],
			"position" => $_POST['position'],
			"volume" => $_POST['volume']
		];
	}

	$formAttributes = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');
	$eidData['form_attributes'] = $db->func($formAttributes);

	// Only touch lab_assigned_code when the field was part of the submitted
	// form, so non-LIS instances (where the input isn't rendered) preserve the
	// previously stored value instead of nulling it.
	if (array_key_exists('labAssignedCode', $_POST)) {
		$eidData['lab_assigned_code'] = $_POST['labAssignedCode'];
	}

	if (isset($_POST['eidSampleId']) && $_POST['eidSampleId'] != '') {
		$db->where('eid_id', $_POST['eidSampleId']);
		// Drop every result column this form did not send.
		foreach ($resultColumnsOwnedByTheForm as $column => $postKey) {
		    if (!in_array($column, $resultColumnsPosted, true)) {
		        unset($eidData[$column]);
		    }
		}

		$id = $db->update($tableName, $eidData);
	}

	if ($id === true) {
		$_SESSION['alertMsg'] = _translate("EID request updated successfully");
		//Add event log
		$eventType = 'update-eid-request';
		$action = $_SESSION['userName'] . ' updated EID request with the sample id ' . $_POST['sampleCode'] . ' and Child id ' . $_POST['childId'];
		$resource = 'eid-request';

		$general->activityLog($eventType, $action, $resource);
	} else {
		$_SESSION['alertMsg'] = _translate("Please try again later");
	}
	header("Location:/eid/requests/eid-requests.php");
} catch (Exception $exc) {
	throw new SystemException($exc->getMessage(), 500);
}
