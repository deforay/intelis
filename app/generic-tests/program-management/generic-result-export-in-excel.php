<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;





/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

//system config
$arr = $general->getGlobalConfig();

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['genericResultQuery']) && trim((string) $_SESSION['genericResultQuery']) != "") {

	/* To get dynamic fields */
	$labels = [];
	foreach ($rResult as $key => $row) {
		$testType[$key] = $genericTestsService->getDynamicFields($row['sample_id']);
		foreach ($testType[$key]['dynamicLabel'] as $id => $le) {
			$labels[$id] = $le;
		}
	}

	$output = [];

	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
		$headings = array("No.", "Sample ID", "Remote Sample ID", "Health Facility Name", "Testing Lab", "Sample Receipt Date", "Health Facility Code", "District/County", "Province/State", "Patient ID.",  "Patient Name", "Date of Birth", "Age", "Sex", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
	} else {
		$headings = array("No.", "Sample ID", "Remote Sample ID", "Health Facility Name", "Testing Lab", "Sample Receipt Date", "Health Facility Code", "District/County", "Province/State", "Date of Birth", "Age", "Sex", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
	}
	if ($general->isStandaloneInstance()) {
		$headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
	}
	/* Assign the dynamic labels to the heading */
	if (!empty($labels)) {
		$headings = array_merge($headings, $labels);
	}

	$no = 1;
	$resultSet = $db->rawQuery($_SESSION['genericResultQuery']);
	foreach ($resultSet as $key => $aRow) {
		$row = [];
		$age = null;
		$aRow['patient_age_in_years'] = (int) $aRow['patient_age_in_years'];
		if (!empty($aRow['patient_dob'])) {
			$age = DateUtility::ageInYearMonthDays($aRow['patient_dob']);
			if (!empty($age) && $age['year'] > 0) {
				$aRow['patient_age_in_years'] = $age['year'];
			}
		}
		//set gender
		$gender = match (strtolower((string)$aRow['patient_gender'])) {
			'male', 'm' => 'M',
			'female', 'f' => 'F',
			'not_recorded', 'notrecorded', 'unreported' => 'Unreported',
			default => '',
		};
		//sample collecion date
		$sampleCollectionDate = '';
		if (!empty($aRow['sample_collection_date'])) {
			$sampleCollectionDate =  DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		}
		//requested date
		$requestedDate = '';
		if (!empty($aRow['test_requested_on'])) {
			$requestedDate =  DateUtility::humanReadableDateFormat($aRow['test_requested_on']);
		}
		//request created date time
		$requestCreatedDatetime = '';
		if (!empty($aRow['request_created_datetime'])) {
			$requestCreatedDatetime =  DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
		}

		$sampleTestedOn = '';
		if (!empty($aRow['sample_tested_datetime'])) {
			$sampleTestedOn =  DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
		}

		$sampleReceivedOn = '';
		if (!empty($aRow['sample_received_at_lab_datetime'])) {
			$sampleReceivedOn =  DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime']);
		}

		//set sample rejection
		$sampleRejection = null;
		if (isset($aRow['is_sample_rejected']) && trim((string) $aRow['is_sample_rejected']) == 'yes' || $aRow['result_status'] == 4) {
			$sampleRejection = 'Yes';
		} else if (trim((string) $aRow['is_sample_rejected']) == 'no') {
			$sampleRejection = 'No';
		}
		//result dispatched date
		$lastViralLoadTest = '';
		if (!empty($aRow['last_viral_load_date'])) {
			$lastViralLoadTest =  DateUtility::humanReadableDateFormat($aRow['last_viral_load_date']);
		}

		//result dispatched date
		$resultDispatchedDate = '';
		if (!empty($aRow['result_printed_datetime'])) {
			$resultDispatchedDate =  DateUtility::humanReadableDateFormat($aRow['result_printed_datetime']);
		}

		if ($aRow['patient_first_name'] != '') {
			$patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_id']));
		} else {
			$patientFname = '';
		}
		if ($aRow['patient_middle_name'] != '') {
			$patientMname = ($general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_id']));
		} else {
			$patientMname = '';
		}
		if ($aRow['patient_last_name'] != '') {
			$patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_id']));
		} else {
			$patientLname = '';
		}

		$row[] = $no;
		if ($general->isStandaloneInstance()) {
			$row[] = $aRow["sample_code"];
		} else {
			$row[] = $aRow["sample_code"];
			$row[] = $aRow["remote_sample_code"] ?? null;
		}
		$row[] = $aRow['facility_name'];
		$row[] = $aRow['lab_name'];
		$row[] = $sampleReceivedOn;
		$row[] = $aRow['facility_code'];
		$row[] = ($aRow['facility_district']);
		$row[] = ($aRow['facility_state']);

		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			$row[] = $aRow['patient_id'];
			$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob'] ?? '');
		$row[] = $aRow['patient_age_in_years'];
		$row[] = $gender;
		$row[] = $sampleCollectionDate;
		$row[] = $aRow['sample_name'] ?: null;
		$row[] = $treatmentInitiationDate;
		$row[] = ($aRow['is_patient_pregnant']);
		$row[] = ($aRow['is_patient_breastfeeding']);
		$row[] = (str_replace("_", " ", (string) $aRow['test_reason']));
		$row[] = ($aRow['request_clinician_name']);
		$row[] = $requestedDate;
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason_name'];
		$row[] = $sampleTestedOn;
		$row[] = $aRow['result'];
		$row[] = $resultDispatchedDate;
		$row[] = ($aRow['lab_tech_comments']);
		$row[] = (isset($aRow['funding_source_name']) && trim((string) $aRow['funding_source_name']) != '') ? ($aRow['funding_source_name']) : '';
		$row[] = (isset($aRow['i_partner_name']) && trim((string) $aRow['i_partner_name']) != '') ? ($aRow['i_partner_name']) : '';
		$row[] = $requestCreatedDatetime;

		/* To assign the dynamic fields values */
		if (!empty($labels)) {
			foreach ($labels as $id => $le) {
				if (!empty($testType[$key]['dynamicValue'][$id])) {
					$row[] = $testType[$key]['dynamicValue'][$id];
				} else {
					$row[] = "";
				}
			}
		}
		$output[] = $row;
		$no++;
	}

	if (isset($_SESSION['genericResultQueryCount']) && $_SESSION['genericResultQueryCount'] > 50000) {
		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-LAB-TESTS-Data-' . date('d-M-Y-H-i-s') . '.csv';
		$fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
		// we dont need the $output variable anymore
		unset($output);
		echo base64_encode((string) $fileName);
	} else {
		$excel = new Spreadsheet();
		$sheet = $excel->getActiveSheet();

		$styleArray = array(
			'font' => array(
				'bold' => true,
				'size' => 12,
			),
			'alignment' => array(
				'horizontal' => Alignment::HORIZONTAL_CENTER,
				'vertical' => Alignment::VERTICAL_CENTER,
			),
			'borders' => array(
				'outline' => array(
					'style' => Border::BORDER_THIN,
				),
			)
		);

		$borderStyle = array(
			'alignment' => array(
				'horizontal' => Alignment::HORIZONTAL_CENTER,
			),
			'borders' => array(
				'outline' => array(
					'style' => Border::BORDER_THIN,
				),
			)
		);
		$sheet->setTitle('Generic Results');
		$sheet->mergeCells('A1:AH1');
		$sheet->getStyle('A3:' . 'AH3')->applyFromArray($styleArray);
		$sheet->fromArray($headings, null, 'A3');
		foreach ($output as $rowNo => $rowData) {
			$rRowCount = $rowNo + 4;
			$sheet->fromArray($rowData, null, 'A' . $rRowCount);
		}


		$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
		$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-LAB-TESTS-Data-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(5) . '.xlsx';
		$writer->save($filename);
		echo urlencode(basename($filename));
	}
}
