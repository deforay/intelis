<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GenericTestsService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
/** @var GenericTestsService $genericObj */
$genericObj = ContainerRegistry::get(GenericTestsService::class);
$dateTimeUtil = new DateUtility();
//system config

if (isset($_SESSION['genericResultQuery']) && trim($_SESSION['genericResultQuery']) != "") {

	$rResult = $db->rawQuery($_SESSION['genericResultQuery']);
	/* To get dynamic fields */
	$labels= array();
	foreach($rResult as $key=>$row){
		$testType[$key] = $genericObj->getDynamicFields($row['sample_id']);
		foreach($testType[$key]['dynamicLabel'] as $id => $le){
			$labels[$id] = $le;
		}
	}

	$excel = new Spreadsheet();
	$output = [];
	$sheet = $excel->getActiveSheet();
	$sheet->setTitle('VL Results');
	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
		$headings = array("No.", "Sample Code", "Remote Sample Code", "Health Facility Name", "Testing Lab", "Health Facility Code", "District/County", "Province/State", "Patient ID.",  "Patient Name", "Date of Birth", "Age", "Gender", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result", "Sample Receipt Date", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
	} else {
		$headings = array("No.", "Sample Code", "Remote Sample Code", "Health Facility Name", "Testing Lab", "Health Facility Code", "District/County", "Province/State", "Date of Birth", "Age", "Gender", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result", "Sample Receipt Date", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
	}
	if ($_SESSION['instanceType'] == 'standalone') {
		if (($key = array_search("Remote Sample Code", $headings)) !== false) {
			unset($headings[$key]);
		}
	}
	/* Assign the dynamic labels to the heading */
	if(isset($labels) && !empty($labels)){
		$headings = array_merge($headings, $labels);
	}

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

	$sheet->mergeCells('A1:AH1');
	$nameValue = '';

	foreach ($_POST as $key => $value) {
		if (trim($value) != '' && trim($value) != '-- Select --') {
			$nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
		}
	}
	$colNo = 1;
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
				->setValueExplicit(html_entity_decode($value));
			$colNo++;
		}
	}
	$lastColumn = Coordinate::stringFromColumnIndex(($colNo-1));
	$sheet->getStyle('A3:'.$lastColumn.'3')->applyFromArray($styleArray);

	$no = 1;
	foreach ($rResult as $key => $aRow) {
		$row = [];
		//date of birth
		$dob = '';
		if (!empty($aRow['patient_dob'])) {
			$dob =  DateUtility::humanReadableDateFormat($aRow['patient_dob']);
		}

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
		//sample collecion date
		$sampleCollectionDate = '';
		if (!empty($aRow['sample_collection_date'])) {
			$sampleCollectionDate =  DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
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
		if (!empty($aRow['sample_received_at_testing_lab_datetime'])) {
			$sampleReceivedOn =  DateUtility::humanReadableDateFormat($aRow['sample_received_at_testing_lab_datetime']);
		}

		//set sample rejection
		$sampleRejection = null;
		if (isset($aRow['is_sample_rejected']) && trim($aRow['is_sample_rejected']) == 'yes' || $aRow['result_status'] == 4) {
			$sampleRejection = 'Yes';
		} else if (trim($aRow['is_sample_rejected']) == 'no') {
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
		if ($_SESSION['instanceType'] == 'standalone') {
			$row[] = $aRow["sample_code"];
		} else {
			$row[] = $aRow["sample_code"];
			$row[] = $aRow["remote_sample_code"] ?: null;
		}
		$row[] = $aRow['facility_name'];
		$row[] = $aRow['lab_name'];
		$row[] = $aRow['facility_code'];
		$row[] = ($aRow['facility_district']);
		$row[] = ($aRow['facility_state']);

		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			$row[] = $aRow['patient_id'];
			$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
		}
		$row[] = $dob;
		$row[] = $aRow['patient_age_in_years'];
		$row[] = $gender;
		$row[] = $sampleCollectionDate;
		$row[] = $aRow['sample_name'] ?: null;
		$row[] = $treatmentInitiationDate;
		$row[] = ($aRow['is_patient_pregnant']);
		$row[] = ($aRow['is_patient_breastfeeding']);
		$row[] = (str_replace("_", " ", $aRow['test_reason']));
		$row[] = ($aRow['request_clinician_name']);
		$row[] = $requestedDate;
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason_name'];
		$row[] = $sampleTestedOn;
		$row[] = $aRow['result'];
		$row[] = $sampleReceivedOn;
		$row[] = $resultDispatchedDate;
		$row[] = ($aRow['lab_tech_comments']);
		$row[] = (isset($aRow['funding_source_name']) && trim($aRow['funding_source_name']) != '') ? ($aRow['funding_source_name']) : '';
		$row[] = (isset($aRow['i_partner_name']) && trim($aRow['i_partner_name']) != '') ? ($aRow['i_partner_name']) : '';
		$row[] = $requestCreatedDatetime;

		/* To assign the dynamic fields values */
		if(isset($labels) && !empty($labels)){
			foreach($labels as $id => $le){
				if(isset($testType[$key]['dynamicValue'][$id]) && !empty($testType[$key]['dynamicValue'][$id])){
					$row[] = $testType[$key]['dynamicValue'][$id];
				}else{
					$row[] = "";
				}
			}
		}
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
				html_entity_decode($value)
			);
			$colNo++;
		}
	}
	$writer = IOFactory::createWriter($excel, 'Xlsx');
	$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-LAB-TESTS-Data-' . date('d-M-Y-H-i-s') . '-' . $general->generateRandomString(5) . '.xlsx';
	$writer->save($filename);
	echo base64_encode($filename);
}
