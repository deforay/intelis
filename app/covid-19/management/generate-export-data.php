<?php






use const COUNTRY\SOUTH_SUDAN;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$covid19Results = $covid19Service->getCovid19Results();

/* Global config data */
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();
$key = (string) $general->getGlobalConfig('key');

// echo "<pre>";print_r($arr);die;
if (isset($_SESSION['covid19ResultQuery']) && trim((string) $_SESSION['covid19ResultQuery']) !== "") {

	$rResult = $db->rawQuery($_SESSION['covid19ResultQuery']);

	$excel = new Spreadsheet();
	$output = [];
	$sheet = $excel->getActiveSheet();
	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
		$headings = ["S. No.", "Sample ID", "Remote Sample ID", "Testing Lab Name", "Testing Point", "Lab staff Assigned", "Source Of Alert / POE", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Case ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Sex", "Nationality", "Patient State", "Patient County", "Patient City/Village", "Date specimen collected", "Reason for Test Request", "Date specimen Received", "Date specimen Entered", "Specimen Condition", "Specimen Status", "Specimen Type", "Is Sample Rejected?", "Rejection Reason", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released"];
	} else {
		$headings = ["S. No.", "Sample ID", "Remote Sample ID", "Testing Lab Name", "Testing Point", "Lab staff Assigned", "Source Of Alert / POE", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Patient DoB", "Patient Age", "Patient Sex", "Nationality", "Patient State", "Patient County", "Patient City/Village", "Date specimen collected", "Reason for Test Request", "Date specimen Received", "Date specimen Entered", "Specimen Condition", "Specimen Status", "Specimen Type", "Is Sample Rejected?", "Rejection Reason", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released"];
	}
	if ($general->isStandaloneInstance()) {
		$headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
	}

	$colNo = 1;

	$styleArray = ['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['outline' => ['style' => Border::BORDER_THIN]]];


	$sheet->mergeCells('A1:AG1');
	$nameValue = '';
	foreach ($_POST as $key => $value) {
		if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
			$nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
		}
	}

	$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
		->setValueExplicit(html_entity_decode($nameValue));
	if ($_POST['withAlphaNum'] == 'yes') {
		foreach ($headings as $field => $value) {
			$string = str_replace(' ', '', $value);
			$value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
			$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
				->setValueExplicit(html_entity_decode($value));
			$colNo++;
		}
	} else {
		foreach ($headings as $field => $value) {
			$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
				->setValueExplicit(html_entity_decode((string) $value));
			$colNo++;
		}
	}
	$sheet->getStyle('A3:AG3')->applyFromArray($styleArray);

	$no = 1;
	foreach ($rResult as $aRow) {
		$row = [];
		if ($arr['vl_form'] == SOUTH_SUDAN) {
			// Get testing platform and test method
			$covid19TestQuery = "SELECT * FROM covid19_tests where covid19_id= ? ORDER BY test_id ASC";
			$covid19TestInfo = $db->rawQuery($covid19TestQuery, [$aRow['covid19_id']]);
			foreach ($covid19TestInfo as $indexKey => $rows) {
				$testPlatform = $rows['testing_platform'];
				$testMethod = $rows['test_name'];
			}
		}

		//date of birth
		$dob = '';
		if ($aRow['patient_dob'] != null && trim((string) $aRow['patient_dob']) !== '' && $aRow['patient_dob'] != '0000-00-00') {
			$dob =  date("d-m-Y", strtotime((string) $aRow['patient_dob']));
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
		if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", (string) $aRow['sample_collection_date']);
			$sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
		}

		$sampleTestedOn = '';
		if ($aRow['sample_tested_datetime'] != null && trim((string) $aRow['sample_tested_datetime']) !== '' && $aRow['sample_tested_datetime'] != '0000-00-00') {
			$sampleTestedOn =  date("d-m-Y", strtotime((string) $aRow['sample_tested_datetime']));
		}


		//set sample rejection
		$sampleRejection = 'No';
		if (trim((string) $aRow['is_sample_rejected']) === 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim((string) $aRow['reason_for_sample_rejection']) !== '' && $aRow['reason_for_sample_rejection'] > 0)) {
			$sampleRejection = 'Yes';
		}
		//result dispatched date
		$resultDispatchedDate = '';
		if ($aRow['result_printed_datetime'] != null && trim((string) $aRow['result_printed_datetime']) !== '' && $aRow['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", (string) $aRow['result_printed_datetime']);
			$resultDispatchedDate =  date("d-m-Y", strtotime($expStr[0]));
		}

		if (!empty($aRow['patient_name'])) {
			$patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow['patient_id']));
		} else {
			$patientFname = '';
		}
		if (!empty($aRow['patient_surname'])) {
			$patientLname = ($general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']));
		} else {
			$patientLname = '';
		}

		if (isset($aRow['source_of_alert']) && $aRow['source_of_alert'] != "others") {
			$sourceOfArtPOE = str_replace("-", " ", (string) $aRow['source_of_alert']);
		} else {
			$sourceOfArtPOE = $aRow['source_of_alert_other'];
		}



		$row[] = $no;
		if ($general->isStandaloneInstance()) {
			$row[] = $aRow["sample_code"];
		} else {
			$row[] = $aRow["sample_code"];
			$row[] = $aRow["remote_sample_code"];
		}
		$row[] = ($aRow['lab_name']);
		$row[] = ($aRow['testing_point']);
		$row[] = ($aRow['labTechnician']);
		$row[] = ($sourceOfArtPOE);
		$row[] = ($aRow['facility_district']);
		$row[] = ($aRow['facility_state']);
		$row[] = ($aRow['facility_name']);
		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
				$aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
				$patientFname = $general->crypto('decrypt', $patientFname, $key);
				$patientLname = $general->crypto('decrypt', $patientLname, $key);
			}
			$row[] = $aRow['patient_id'];
			$row[] = $patientFname . " " . $patientLname;
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
		$row[] = ($aRow['patient_age'] != null && trim((string) $aRow['patient_age']) !== '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
		$row[] = ($aRow['patient_gender']);
		$row[] = ($aRow['nationality']);
		$row[] = ($aRow['patient_province']);
		$row[] = ($aRow['patient_district']);
		$row[] = ($aRow['patient_city']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = ($aRow['test_reason_name']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
		$row[] = ($aRow['sample_condition']);
		$row[] = ($aRow['status_name']);
		$row[] = ($aRow['sample_name']);
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
		$row[] = ($testPlatform);
		$row[] = ($testMethod);
		$row[] = $covid19Results[$aRow['result']] ?? $aRow['result'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');

		$output[] = $row;
		$no++;
	}

	$start = (count($output)) + 2;
	foreach ($output as $rowNo => $rowData) {
		$colNo = 1;
		$rRowCount = $rowNo + 4;
		foreach ($rowData as $field => $value) {
			$sheet->setCellValue(
				Coordinate::stringFromColumnIndex($colNo) . $rRowCount,
				html_entity_decode((string) $value)
			);
			$colNo++;
		}
	}
	$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
	$filename = 'Covid-19-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
	$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
	echo urlencode(basename($filename));
}
