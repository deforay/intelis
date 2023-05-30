<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}



use App\Services\TbService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);
$tbResults = $tbService->getTbResults();

/* Global config data */
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();
if (isset($_SESSION['tbResultQuery']) && trim($_SESSION['tbResultQuery']) != "") {

	$rResult = $db->rawQuery($_SESSION['tbResultQuery']);


	$output = [];

	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
		$headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab Name", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Case ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Gender", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Status", "Specimen Type", "Is Sample Rejected?", "Rejection Reason", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released");
	} else {
		$headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab Name", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Patient DoB", "Patient Age", "Patient Gender", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Status", "Specimen Type", "Is Sample Rejected?", "Rejection Reason", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released");
	}
	if ($_SESSION['instanceType'] == 'standalone' && ($key = array_search("Remote Sample Code", $headings)) !== false) {
		unset($headings[$key]);
	}



	$no = 1;
	foreach ($rResult as $aRow) {
		$row = [];

		// Get testing platform and test method
		$tbTestQuery = "SELECT * from tb_tests where tb_id= ? ORDER BY tb_test_id ASC";
		$tbTestInfo = $db->rawQuery($tbTestQuery, [$aRow['tb_id']]);

		foreach ($tbTestInfo as $indexKey => $rows) {
			$testPlatform = $rows['testing_platform'];
			$testMethod = $rows['test_name'];
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

		//set sample rejection
		$sampleRejection = 'No';
		if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
			$sampleRejection = 'Yes';
		}

		if ($_SESSION['instanceType'] == 'remoteuser') {
			$sampleCode = 'remote_sample_code';
		} else {
			$sampleCode = 'sample_code';
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

		$row[] = $no;
		if ($_SESSION['instanceType'] == 'standalone') {
			$row[] = $aRow["sample_code"];
		} else {
			$row[] = $aRow["sample_code"];
			$row[] = $aRow["remote_sample_code"];
		}
		$row[] = ($aRow['lab_name']);
		$row[] = ($aRow['labTechnician']);
		$row[] = ($aRow['facility_district']);
		$row[] = ($aRow['facility_state']);
		$row[] = ($aRow['facility_name']);
		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			$row[] = $aRow['patient_id'];
			$row[] = $patientFname . " " . $patientLname;
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
		$row[] = ($aRow['patient_age'] != null && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
		$row[] = ($aRow['patient_gender']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
		$row[] = ($aRow['test_reason_name']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
		$row[] = ($aRow['status_name']);
		$row[] = ($aRow['sample_name']);
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
		$row[] = ($testPlatform);
		$row[] = ($testMethod);
		$row[] = $tbResults[$aRow['result']];
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime']);

		$output[] = $row;
		$no++;
	}


	if (isset($_SESSION['tbResultQueryCount']) && $_SESSION['tbResultQueryCount'] > 5000) {

		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-TB-Export-Data-' . date('d-M-Y-H-i-s') . '.csv';
		$file = new SplFileObject($fileName, 'w');
		$file->setCsvControl("\t", "\r\n");
		$file->fputcsv($headings);
		foreach ($output as $row) {
			$file->fputcsv($row);
		}
		// we dont need the $file variable anymore
		$file = null;
		echo base64_encode($fileName);
	} else {
		$excel = new Spreadsheet();
		$sheet = $excel->getActiveSheet();

		$colNo = 1;
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

		foreach ($output as $rowNo => $rowData) {
			$colNo = 1;
			$rRowCount = $rowNo + 4;
			foreach ($rowData as $field => $value) {
				$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode($value));
				$colNo++;
			}
		}
		$writer = IOFactory::createWriter($excel, 'Xlsx');
		$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-TB-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
		$writer->save($filename);
		echo $filename;
	}
}
