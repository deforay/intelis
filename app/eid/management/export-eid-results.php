<?php

use App\Services\EidService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}



/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var DateUtility $dateTimeUtil */
$dateTimeUtil = new DateUtility();


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

if (isset($_SESSION['eidExportResultQuery']) && trim($_SESSION['eidExportResultQuery']) != "") {

	$rResult = $db->rawQuery($_SESSION['eidExportResultQuery']);


	$output = [];
	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
		$headings = array("S.No.", "Sample Code", "Remote Sample Code", "Health Facility", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Child ID", "Child Name", "Mother ID", "Child Date of Birth", "Child Age", "Child Gender", "Breastfeeding", "PCR Test Performed Before", "Last PCR Test results", "Sample Collection Date", "Sample Type", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result", "Sample Received On", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
	} else {
		$headings = array("S.No.", "Sample Code", "Remote Sample Code", "Health Facility", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Child Date of Birth", "Child Age", "Child Gender", "Breastfeeding",  "PCR Test Performed Before", "Last PCR Test results", "Sample Collection Date", "Sample Type", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result", "Sample Received On", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
	}
	if ($_SESSION['instanceType'] == 'standalone' && ($key = array_search("Remote Sample Code", $headings)) !== false) {
		unset($headings[$key]);
	}



	$no = 1;
	foreach ($rResult as $aRow) {
		$row = [];

		//set gender
		$gender = '';
		if ($aRow['child_gender'] == 'male') {
			$gender = 'M';
		} elseif ($aRow['child_gender'] == 'female') {
			$gender = 'F';
		} elseif ($aRow['child_gender'] == 'not_recorded') {
			$gender = 'Unreported';
		}
		//set sample rejection
		$sampleRejection = 'No';
		if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
			$sampleRejection = 'Yes';
		}

		if ($aRow['patient_first_name'] != '') {
			$patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_art_no']));
		} else {
			$patientFname = '';
		}
		if ($aRow['patient_middle_name'] != '') {
			$patientMname = ($general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_art_no']));
		} else {
			$patientMname = '';
		}
		if ($aRow['patient_last_name'] != '') {
			$patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_art_no']));
		} else {
			$patientLname = '';
		}

		$row[] = $no;
		if ($_SESSION['instanceType'] == 'standalone') {
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
		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			$row[] = $aRow['child_id'];
			$row[] = $aRow['child_name'];
			$row[] = $aRow['mother_id'];
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['child_dob']);
		$row[] = ($aRow['child_age'] != null && trim($aRow['child_age']) != '' && $aRow['child_age'] > 0) ? $aRow['child_age'] : 0;
		$row[] = $gender;
		$row[] = $aRow['has_infant_stopped_breastfeeding'];
		$row[] = $aRow['pcr_test_performed_before'];
		$row[] = $aRow['previous_pcr_result'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
		$row[] = $aRow['sample_name'] ?: null;
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
		$row[] = $eidResults[$aRow['result']] ?? $aRow['result'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime']);
		$row[] = $aRow['lab_tech_comments'];
		$row[] = $aRow['funding_source_name'] ?? null;
		$row[] = $aRow['i_partner_name'] ?? null;
		$output[] = $row;
		$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
		$no++;
	}

	if (isset($_SESSION['eidExportResultQueryCount']) && $_SESSION['eidExportResultQueryCount'] > 5000) {

		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-VIRAL-LOAD-Data-' . date('d-M-Y-H-i-s') . '.csv';
		$file = new SplFileObject($fileName, 'w');
		$file->setCsvControl(",", "\r\n");
		$file->fputcsv($headings);
		foreach ($output as $row) {
			$file->fputcsv($row);
		}
		// we dont need the $file variable anymore
		$file = null;
		echo base64_encode($fileName);
	} else {
		$colNo = 1;
		$excel = new Spreadsheet();
		$sheet = $excel->getActiveSheet();

		$nameValue = '';
		foreach ($_POST as $key => $value) {
			if (trim($value) != '' && trim($value) != '-- Select --') {
				$nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
			}
		}

		$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($nameValue));
		if ($_POST['withAlphaNum'] == 'yes') {
			foreach ($headings as $field => $value) {
				$string = str_replace(' ', '', $value);
				$value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
				$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', html_entity_decode($value));
				$colNo++;
			}
		} else {
			foreach ($headings as $field => $value) {
				$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', html_entity_decode($value));
				$colNo++;
			}
		}
		//$start = (count($output)) + 2;
		foreach ($output as $rowNo => $rowData) {
			$colNo = 1;
			$rRowCount = $rowNo + 4;
			foreach ($rowData as $field => $value) {
				$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode($value));
				$colNo++;
			}
		}
		$writer = IOFactory::createWriter($excel, 'Xlsx');
		$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-EID-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
		$writer->save($filename);
		echo base64_encode($filename);
	}
}
