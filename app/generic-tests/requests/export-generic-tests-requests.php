<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Services\GenericTestsService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;





/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

$arr = $general->getGlobalConfig();

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

//system config

if (isset($_SESSION['genericRequestQuery']) && trim((string) $_SESSION['genericRequestQuery']) !== "") {

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
		$headings = ["No.", "Sample ID", "Remote Sample ID", "Health Facility Name", "Testing Lab", "Sample Receipt Date", "Health Facility Code", "District/County", "Province/State", "Patient ID.", "Patient Name", "Date of Birth", "Age", "Sex", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On"];
	} else {
		$headings = ["No.", "Sample ID", "Remote Sample ID", "Health Facility Name", "Testing Lab", "Sample Receipt Date", "Health Facility Code", "District/County", "Province/State", "Date of Birth", "Age", "Sex", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On"];
	}
	if ($general->isStandaloneInstance() && ($key = array_search("Remote Sample ID", $headings)) !== false) {
		unset($headings[$key]);
	}
	/* Assign the dynamic labels to the heading */
	if ($labels !== []) {
		$headings = array_merge($headings, $labels);
	}

	/*$colNo = 1;
	$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')->setValueExplicit(html_entity_decode('OTHER LAB TESTS REQUESTS'));
	foreach ($headings as $field => $value) {
		$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
			->setValueExplicit(html_entity_decode($value));
		$colNo++;
	}
	$lastColumn = Coordinate::stringFromColumnIndex(($colNo - 1));
	$sheet->getStyle('A3:' . $lastColumn . '3')->applyFromArray($styleArray);*/

	$no = 1;
	$resultSet = $db->rawQuery($_SESSION['genericRequestQuery']);
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
		if (isset($aRow['is_sample_rejected']) && trim((string) $aRow['is_sample_rejected']) === 'yes' || $aRow['result_status'] == 4) {
      $sampleRejection = 'Yes';
  } elseif (trim((string) $aRow['is_sample_rejected']) === 'no') {
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

		$patientFname = $aRow['patient_first_name'] ?? '';
		$patientMname = $aRow['patient_middle_name'] ?? '';
		$patientLname = $aRow['patient_last_name'] ?? '';

		$row[] = $no;
		$row[] = $aRow["sample_code"];
		if (!$general->isStandaloneInstance()) {
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
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
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
		$row[] = (isset($aRow['funding_source_name']) && trim((string) $aRow['funding_source_name']) !== '') ? ($aRow['funding_source_name']) : '';
		$row[] = (isset($aRow['i_partner_name']) && trim((string) $aRow['i_partner_name']) !== '') ? ($aRow['i_partner_name']) : '';
		$row[] = $requestCreatedDatetime;

		/* To assign the dynamic fields values */
		if ($labels !== []) {
			foreach (array_keys($labels) as $id) {
				$row[] = empty($testType[$key]['dynamicValue'][$id]) ? "" : $testType[$key]['dynamicValue'][$id];
			}
		}
		$output[] = $row;
		$no++;
	}

	if (isset($_SESSION['genericResultQueryCount']) && $_SESSION['genericResultQueryCount'] > 50000) {
		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-LAB-TESTS-REQUESTS-' . date('d-M-Y-H-i-s') . '.csv';
		$fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
		// we dont need the $output variable anymore
		unset($output);
		echo base64_encode((string) $fileName);
	} else {
		$excel = new Spreadsheet();
		$sheet = $excel->getActiveSheet();
		$sheet->setTitle('Generic Results');

		$styleArray = ['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['outline' => ['style' => Border::BORDER_THIN]]];

		$borderStyle = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER], 'borders' => ['outline' => ['style' => Border::BORDER_THIN]]];
		$sheet->mergeCells('A1:O1');
		$sheet->getStyle('A1:O1')->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'alignment' => [
      // 'horizontal' => Alignment::HORIZONTAL_CENTER,
      'vertical' => Alignment::VERTICAL_CENTER,
  ], 'borders' => ['outline' => ['style' => Border::BORDER_THIN]]]);

		$sheet->fromArray($headings, null, 'A3');

		foreach ($output as $rowNo => $rowData) {
			$rRowCount = $rowNo + 4;
			$sheet->fromArray($rowData, null, 'A' . $rRowCount);
		}

		$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
		$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-LAB-TESTS-REQUESTS-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(5) . '.xlsx';
		$writer->save($filename);
		echo urlencode(basename($filename));
	}
}
