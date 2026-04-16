<?php

use const COUNTRY\CAMEROON;
use const COUNTRY\DRC;
use App\Services\EidService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Services\CommonService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');
$formId = (int) $general->getGlobalConfig('vl_form');


if (isset($_SESSION['eidExportResultQuery']) && trim((string) $_SESSION['eidExportResultQuery']) !== "") {

	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
		$headings = [_translate("S.No."), _translate("Sample ID"), _translate("Remote Sample ID"), _translate("Health Facility"), _translate("Health Facility Code"), _translate("District/County"), _translate("Province/State"), _translate("Testing Lab Name (Hub)"), _translate("Lab Assigned Code"), _translate("Sample Received On"), _translate("Child ID"), _translate("Child Name"), _translate("Mother ID"), _translate("Child Date of Birth"), _translate("Child Age in Months"), _translate("Child Age in Weeks"), _translate("Child Age in Days"), _translate("Child Sex"), _translate("Breastfeeding"), _translate("Clinician's Phone Number"), _translate("PCR Test Performed Before"), _translate("Last PCR Test results"), _translate("Reason For PCR Test"), _translate("Sample Collection Date"), _translate("Sample Requestor Phone Number"), _translate("Sample Type"), _translate("EID Number"), _translate("Testing Platform"), _translate("Is Sample Rejected?"), _translate("Freezer"), _translate("Rack"), _translate("Box"), _translate("Position"), _translate("Volume (ml)"), _translate("Rejection Reason"), _translate("Recommended Corrective Action"), _translate("Sample Tested On"), _translate("Result"), _translate("Date Result Dispatched"), _translate("Comments"), _translate("Funding Source"), _translate("Implementing Partner"), _translate("Reviewed By"), _translate("Reviewed On"), _translate("Approved By"), _translate("Approved On"), _translate("Request Created On")];
	} else {
		$headings = [_translate("S.No."), _translate("Sample ID"), _translate("Remote Sample ID"), _translate("Health Facility"), _translate("Health Facility Code"), _translate("District/County"), _translate("Province/State"), _translate("Testing Lab Name (Hub)"), _translate("Lab Assigned Code"), _translate("Sample Received On"), _translate("Child Date of Birth"), _translate("Child Age in Months"), _translate("Child Age in Weeks"), _translate("Child Age in Days"), _translate("Child Sex"), _translate("Breastfeeding"), _translate("Clinician's Phone Number"), _translate("PCR Test Performed Before"), _translate("Last PCR Test results"), _translate("Reason For PCR Test"), _translate("Sample Collection Date"), _translate("Sample Requestor Phone Number"), _translate("Sample Type"), _translate("EID Number"), _translate("Testing Platform"), _translate("Is Sample Rejected?"), _translate("Freezer"), _translate("Rack"), _translate("Box"), _translate("Position"), _translate("Volume (ml)"), _translate("Rejection Reason"), _translate("Recommended Corrective Action"), _translate("Sample Tested On"), _translate("Result"), _translate("Date Result Dispatched"), _translate("Comments"), _translate("Funding Source"), _translate("Implementing Partner"), _translate("Reviewed By"), _translate("Reviewed On"), _translate("Approved By"), _translate("Approved On"), _translate("Request Created On")];
	}
	if ($general->isStandaloneInstance() && ($key = array_search(_translate("Remote Sample ID"), $headings)) !== false) {
		unset($headings[$key]);
	}
	//$headings = array(_translate("S.No."), _translate("Sample ID"), _translate("Remote Sample ID"), _translate("Health Facility"), _translate("Health Facility Code"), _translate("District/County"), _translate("Province/State"), _translate("Testing Lab Name (Hub)"), _translate("Lab Assigned Code"), _translate("Sample Received On"), _translate("Child Date of Birth"), _translate("Child Age in Months"), _translate("Child Age in Weeks"), _translate("Child Age in Days"), _translate("Child Sex"), _translate("Breastfeeding"), _translate("PCR Test Performed Before"), _translate("Last PCR Test results"), _translate("Reason For PCR Test"), _translate("Sample Collection Date"), _translate("Sample Type"), _translate("Testing Platform"), _translate("Is Sample Rejected?"), _translate("Rejection Reason"), _translate("Recommended Corrective Action"), _translate("Sample Tested On"), _translate("Result"), _translate("Date Result Dispatched"), _translate("Comments"), _translate("Funding Source"), _translate("Implementing Partner"), _translate("Reviewed By"), _translate("Reviewed On"), _translate("Approved By"), _translate("Approved On"), _translate("Request Created On"));

	if ($formId != CAMEROON) {
		$headings = MiscUtility::removeMatchingElements($headings, [_translate("Lab Assigned Code")]);
	}

	if ($formId != DRC) {
		$headings = MiscUtility::removeMatchingElements($headings, [_translate("Freezer"), _translate("Rack"), _translate("Box"), _translate("Position"), _translate("Volume (ml)"), _translate("Child Age in Weeks"), _translate("Child Age in Days"), _translate("Testing Platform"), _translate("Reviewed By"), _translate("Reviewed On"), _translate("Approved By"), _translate("Approved On")]);
	}

	$testPlatformResult = $general->getTestingPlatforms('eid');
	$testPlatformList = [];
	foreach ($testPlatformResult as $row) {
		$testPlatformList[$row['machine_name'] . '##' . $row['instrument_id']] = $row['machine_name'];
	}
	$testingLabs = $facilitiesService->getTestingLabs('eid');
	$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
	$userInfo = [];
	foreach ($userResult as $user) {
		$userInfo[$user['user_id']] = ($user['user_name']);
	}
	// Row builder function
	$buildRow = function ($aRow, $no) use ($general, $key, $formId, $testPlatformList, $userInfo): array {
		$row = [];

		//set gender
		$gender = '';
		if ($aRow['child_gender'] == 'male') {
			$gender = 'M';
		} elseif ($aRow['child_gender'] == 'female') {
			$gender = 'F';
		} elseif ($aRow['child_gender'] == 'unreported') {
			$gender = 'Unreported';
		}
		//set sample rejection
		$sampleRejection = 'No';
		if (trim((string) $aRow['is_sample_rejected']) === 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim((string) $aRow['reason_for_sample_rejection']) !== '' && $aRow['reason_for_sample_rejection'] > 0)) {
			$sampleRejection = 'Yes';
		}


		$row[] = $no;
		if ($general->isStandaloneInstance()) {
			$row[] = $aRow["sample_code"];
		} else {
			$row[] = $aRow["sample_code"];
			$row[] = $aRow["remote_sample_code"];
		}
		$row[] = $aRow['facility_name'];
		$row[] = $aRow['facility_code'];
		$row[] = $aRow['facility_district'];
		$row[] = $aRow['facility_state'];
		$row[] = ($aRow['lab_name']);
		if ($formId == CAMEROON) {
			$row[] = $aRow['lab_assigned_code'];
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
				$aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
				$aRow['child_name'] = $general->crypto('decrypt', $aRow['child_name'], $key);
				$aRow['mother_id'] = $general->crypto('decrypt', $aRow['mother_id'], $key);
				//$aRow['mother_name'] = $general->crypto('decrypt', $aRow['mother_name'], $key);
			}
			$row[] = $aRow['child_id'];
			$row[] = trim(($aRow['child_name'] ?? '') . ' ' . ($aRow['child_surname'] ?? ''));
			$row[] = $aRow['mother_id'];
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['child_dob']);
		$row[] = ($aRow['child_age'] != null && trim((string) $aRow['child_age']) !== '' && $aRow['child_age'] > 0) ? $aRow['child_age'] : 0;
		if ($formId == DRC) {
			$row[] = $aRow['child_age_in_weeks'] ?? 0;
			$row[] = $aRow['child_age_in_days'] ?? 0;
		}
		$row[] = $gender;
		$row[] = $aRow['has_infant_stopped_breastfeeding'];
		$row[] = $aRow['request_clinician_phone_number'];
		$row[] = $aRow['pcr_test_performed_before'];
		$row[] = $aRow['previous_pcr_result'];
		$row[] = $aRow['reason_for_pcr'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = $aRow['sample_requestor_phone'];
		$row[] = $aRow['sample_name'] ?: null;
		$row[] = $aRow['eid_number'];
		if ($formId == DRC) {
			$row[] = $testPlatformList[$aRow['eid_test_platform'] . '##' . $aRow['instrument_id']];
		}
		$row[] = $sampleRejection;
		if ($formId == DRC) {
			$formAttributes = empty($aRow['form_attributes']) ? null : json_decode((string) $aRow['form_attributes']);
			$storageObj = isset($formAttributes->storage) ? json_decode($formAttributes->storage) : null;

			$row[] = $storageObj->storageCode ?? '';
			$row[] = $storageObj->rack ?? '';
			$row[] = $storageObj->box ?? '';
			$row[] = $storageObj->position ?? '';
			$row[] = $storageObj->volume ?? '';
		}
		$row[] = $aRow['rejection_reason'];
		$row[] = $aRow['recommended_corrective_action_name'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
		$row[] = $eidResults[$aRow['result']] ?? $aRow['result'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
		$row[] = $aRow['lab_tech_comments'];
		$row[] = $aRow['funding_source_name'] ?? null;
		$row[] = $aRow['i_partner_name'] ?? null;
		if ($formId == DRC) {
			$row[] = $userInfo[$aRow['result_reviewed_by']];
			$row[] = DateUtility::humanReadableDateFormat($aRow['result_reviewed_datetime'], true);
			$row[] = $userInfo[$aRow['result_approved_by']];
			$row[] = DateUtility::humanReadableDateFormat($aRow['result_approved_datetime'], true);
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
		return $row;
	};

	$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-EID-Data-' . date('d-M-Y-H-i-s') . '.xlsx';

	$writer = new Writer();
	$writer->openToFile($fileName);

	// Write headings
	$writer->addRow(Row::fromValues($headings));

	// Stream data
	$resultSet = $db->rawQueryGenerator($_SESSION['eidExportResultQuery']);
	$no = 1;

	foreach ($resultSet as $aRow) {
		$row = $buildRow($aRow, $no++);
		$writer->addRow(Row::fromValues($row));

		// Periodic garbage collection every 5000 rows (reduced frequency)
		if ($no % 5000 === 0) {
			gc_collect_cycles();
		}
	}

	$writer->close();
	echo urlencode(basename($fileName));
}
