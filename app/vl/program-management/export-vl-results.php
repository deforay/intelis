<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

ini_set('memory_limit', -1);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var DateUtility $dateTimeUtil */
$dateTimeUtil = new DateUtility();


if (isset($_SESSION['vlResultQuery']) && trim($_SESSION['vlResultQuery']) != "") {

	$rResult = $db->rawQuery($_SESSION['vlResultQuery']);

	$output = [];


	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
		$headings = array("No.", "Sample Code", "Remote Sample Code", "Health Facility Name", "Testing Lab", "Health Facility Code", "District/County", "Province/State", "Unique ART No.",  "Patient Name", "Date of Birth", "Age", "Gender", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Current Regimen", "Date of Initiation of Current Regimen", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "ARV Adherence", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result (cp/ml)", "Result (log)", "Sample Receipt Date", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
	} else {
		$headings = array("No.", "Sample Code", "Remote Sample Code", "Health Facility Name", "Testing Lab", "Health Facility Code", "District/County", "Province/State", "Date of Birth", "Age", "Gender", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Current Regimen", "Date of Initiation of Current Regimen", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "ARV Adherence", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result (cp/ml)", "Result (log)", "Sample Receipt Date", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
	}
	if ($_SESSION['instanceType'] == 'standalone' && ($key = array_search("Remote Sample Code", $headings)) !== false) {
		unset($headings[$key]);
	}
	//$sheet->getStyle('A3:AI3')->applyFromArray($styleArray);

	$no = 1;
	foreach ($rResult as $aRow) {
		$row = [];

		$age = null;
		$aRow['patient_age_in_years'] = (int) $aRow['patient_age_in_years'];
		if (!empty($aRow['patient_dob'])) {
			$age = $dateTimeUtil->ageInYearMonthDays($aRow['patient_dob']);
			if (!empty($age) && $age['year'] > 0) {
				$aRow['patient_age_in_years'] = $age['year'];
			}
		}
		//set gender
		switch (strtolower($aRow['patient_gender'])) {
			case 'male':
			case 'm':
				$gender = 'M';
				break;
			case 'female':
			case 'f':
				$gender = 'F';
				break;
			case 'not_recorded':
			case 'notrecorded':
			case 'unreported':
				$gender = 'Unreported';
				break;
			default:
				$gender = '';
				break;
		}


		//set ARV adherecne
		$arvAdherence = '';
		if (trim($aRow['arv_adherance_percentage']) == 'good') {
			$arvAdherence = 'Good >= 95%';
		} elseif (trim($aRow['arv_adherance_percentage']) == 'fair') {
			$arvAdherence = 'Fair 85-94%';
		} elseif (trim($aRow['arv_adherance_percentage']) == 'poor') {
			$arvAdherence = 'Poor <85%';
		}

		$sampleRejection = ($aRow['is_sample_rejected'] == 'yes' || ($aRow['reason_for_sample_rejection'] != null && $aRow['reason_for_sample_rejection'] > 0)) ? 'Yes' : 'No';


		//set result log value
		$logVal = '';
		if (!empty($aRow['result_value_log']) && is_numeric($aRow['result_value_log'])) {
			$logVal = round($aRow['result_value_log'], 1);
		}

		if ($aRow['patient_first_name'] != '') {
			$patientFname = $general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_art_no']);
		} else {
			$patientFname = '';
		}
		if ($aRow['patient_middle_name'] != '') {
			$patientMname = $general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_art_no']);
		} else {
			$patientMname = '';
		}
		if ($aRow['patient_last_name'] != '') {
			$patientLname = $general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_art_no']);
		} else {
			$patientLname = '';
		}

		$row[] = $no;
		$row[] = $aRow["sample_code"];

		if ($_SESSION['instanceType'] != 'standalone') {
			$row[] = $aRow["remote_sample_code"];
		}

		$row[] = $aRow['facility_name'];
		$row[] = $aRow['lab_name'];
		$row[] = $aRow['facility_code'];
		$row[] = $aRow['facility_district'];
		$row[] = $aRow['facility_state'];

		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			$row[] = $aRow['patient_art_no'];
			$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
		$row[] = $aRow['patient_age_in_years'];
		$row[] = $gender;
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
		$row[] = $aRow['sample_name'] ?: null;
		$row[] = DateUtility::humanReadableDateFormat($aRow['treatment_initiated_date']);
		$row[] = $aRow['current_regimen'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['date_of_initiation_of_current_regimen']);
		$row[] = $aRow['is_patient_pregnant'];
		$row[] = $aRow['is_patient_breastfeeding'];
		$row[] = $arvAdherence;
		$row[] = str_replace("_", " ", $aRow['test_reason_name']);
		$row[] = $aRow['request_clinician_name'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['test_requested_on']);
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
		$row[] = $aRow['result'];
		$row[] = $logVal;
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime']);
		$row[] = $aRow['lab_tech_comments'];
		$row[] = $aRow['funding_source_name'] ?? null;
		$row[] = $aRow['i_partner_name'] ?? null;
		$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
		$output[] = $row;
		$no++;
	}

	if (isset($_SESSION['vlResultQueryCount']) && $_SESSION['vlResultQueryCount'] > 5000) {

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
		$nameValue = '';

		$excel = new Spreadsheet();
		$sheet = $excel->getActiveSheet();
		$sheet->setTitle('VL Results');

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
		$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-VIRAL-LOAD-Data-' . date('d-M-Y-H-i-s') . '-' . $general->generateRandomString(5) . '.xlsx';
		$writer->save($filename);
		echo base64_encode($filename);
	}
}
