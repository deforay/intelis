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





/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');
$formId = (int) $general->getGlobalConfig('vl_form');



if (isset($_SESSION['eidExportResultQuery']) && trim((string) $_SESSION['eidExportResultQuery']) !== "") {



	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
		$headings = ["S.No.", "Sample ID", "Remote Sample ID", "Health Facility", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Lab Assigned Code", "Sample Received On", "Child ID", "Child Name", "Mother ID", "Child Date of Birth", "Child Age", "Child Sex", "Breastfeeding", "Clinician's Phone Number", "PCR Test Performed Before", "Last PCR Test results", "Reason For PCR Test", "Sample Collection Date", "Sample Requestor Phone Number", "Sample Type", "EID Number", "Is Sample Rejected?", "Freezer", "Rack", "Box", "Position", "Volume (ml)", "Rejection Reason", "Recommended Corrective Action", "Sample Tested On", "Result", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On"];
	} else {
		$headings = ["S.No.", "Sample ID", "Remote Sample ID", "Health Facility", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Lab Assigned Code", "Sample Received On", "Child Date of Birth", "Child Age", "Child Sex", "Breastfeeding", "Clinician's Phone Number", "PCR Test Performed Before", "Last PCR Test results", "Reason For PCR Test", "Sample Collection Date", "Sample Requestor Phone Number", "Sample Type", "EID Number", "Is Sample Rejected?", "Freezer", "Rack", "Box", "Position", "Volume (ml)", "Rejection Reason", "Recommended Corrective Action", "Sample Tested On", "Result", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On"];
	}
	if ($general->isStandaloneInstance() && ($key = array_search("Remote Sample ID", $headings)) !== false) {
		unset($headings[$key]);
	}
	//$headings = array("S.No.", "Sample ID", "Remote Sample ID", "Health Facility", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Sample Received On", "Child Date of Birth", "Child Age", "Child Sex", "Breastfeeding",  "PCR Test Performed Before", "Last PCR Test results","Reason For PCR Test", "Sample Collection Date", "Sample Type", "Is Sample Rejected?", "Rejection Reason", "Recommended Corrective Action", "Sample Tested On", "Result", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");


	if ($formId != CAMEROON) {
		$headings = MiscUtility::removeMatchingElements($headings, ["Lab Assigned Code"]);
	}

	if ($formId != DRC) {
		$headings = MiscUtility::removeMatchingElements($headings, ["Freezer", "Rack", "Box", "Position", "Volume (ml)"]);
	}

	// Row builder function
	$buildRow = function ($aRow, $no) use ($general, $key, $formId): array {
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
			$row[] = $aRow['child_name'];
			$row[] = $aRow['mother_id'];
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['child_dob']);
		$row[] = ($aRow['child_age'] != null && trim((string) $aRow['child_age']) !== '' && $aRow['child_age'] > 0) ? $aRow['child_age'] : 0;
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

		$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
		return $row;
	};


	$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-EID-Data-' . date('d-M-Y-H-i-s') . '.csv';

	$writer = new Writer();
	$writer->openToFile($filename);

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
	echo urlencode(basename((string) $filename));
}
