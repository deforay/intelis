<?php

use App\Models\General;
use App\Utilities\DateUtils;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;


ini_set('memory_limit', -1);
$general = new General();
$dateTimeUtil = new DateUtils();
/*
$sQuery = "SELECT  
                        vl.vl_sample_id,
                        vl.sample_code,
                        vl.remote_sample_code,
                        vl.patient_art_no,
                        vl.patient_first_name,
                        vl.patient_middle_name,
                        vl.patient_last_name,
                        vl.patient_dob,
                        vl.patient_gender,
                        vl.patient_age_in_years,
                        vl.sample_collection_date,
                        vl.treatment_initiated_date,
                        vl.date_of_initiation_of_current_regimen,
                        vl.test_requested_on,
                        vl.sample_tested_datetime,
                        vl.arv_adherance_percentage,
                        vl.is_sample_rejected,
                        vl.reason_for_sample_rejection,
                        vl.result_value_log,
                        vl.result_value_absolute,
                        vl.result,
                        vl.current_regimen,
                        vl.is_patient_pregnant,
                        vl.is_patient_breastfeeding,
                        vl.request_clinician_name,
                        vl.lab_tech_comments,
                        vl.sample_received_at_hub_datetime,							
                        vl.sample_received_at_vl_lab_datetime,							
                        vl.result_dispatched_datetime,	
                        vl.result_printed_datetime,	
                        vl.request_created_datetime, 
                        vl.last_modified_datetime,
                        vl.result_status,
                        vl.data_sync,
                        s.sample_name,
                        b.batch_code,
                        f.facility_name,
                        testingLab.facility_name as lab_name,
                        f.facility_code,
                        f.facility_state,
                        f.facility_district,
                        rs.rejection_reason_name,
                        tr.test_reason_name,
                        r_f_s.funding_source_name,
                        r_i_p.i_partner_name 
                        
                        FROM form_vl as vl 
                        
                        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                        LEFT JOIN facility_details as testingLab ON vl.lab_id=testingLab.facility_id 
                        LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type 
                        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
                        LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
                        LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing 
                        LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
                        LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";

if (isset($_SESSION['vlRequestData']['sWhere']) && !empty($_SESSION['vlRequestData']['sWhere'])) {
	$sQuery = $sQuery . ' WHERE ' . $_SESSION['vlRequestData']['sWhere'];
}

if (isset($_SESSION['vlRequestData']['sOrder']) && !empty($_SESSION['vlRequestData']['sOrder'])) {
	$sQuery = $sQuery . " ORDER BY " . $_SESSION['vlRequestData']['sOrder'];
}*/
$sQuery = $_SESSION['vlRequestSearchResultQuery'];
$rResult = $db->rawQuery($sQuery);

$excel = new Spreadsheet();
$output = array();
$sheet = $excel->getActiveSheet();
if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
$headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Unique ART No.", "Patient Name", "Date of Birth", "Age", "Gender", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Current Regimen", "Date of Initiation of Current Regimen", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "ARV Adherence", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Sample Tested On", "Result (cp/ml)", "Result (log)", "Sample Receipt Date", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
}
else{
	$headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Date of Birth", "Age", "Gender", "Date of Sample Collection", "Sample Type", "Date of Treatment Initiation", "Current Regimen", "Date of Initiation of Current Regimen", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "ARV Adherence", "Indication for Viral Load Testing", "Requesting Clinican", "Request Date", "Is Sample Rejected?", "Sample Tested On", "Result (cp/ml)", "Result (log)", "Sample Receipt Date", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner", "Request Created On");
}
if ($_SESSION['instanceType'] == 'standalone') {
	if (($key = array_search("Remote Sample Code", $headings)) !== false) {
		unset($headings[$key]);
	}
}
$colNo = 1;

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
$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
		->setValueExplicit(html_entity_decode($nameValue), DataType::TYPE_STRING);
if (isset($_POST['withAlphaNum']) && $_POST['withAlphaNum'] == 'yes') {
	foreach ($headings as $field => $value) {
		$string = str_replace(' ', '', $value);
		$value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
		$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
				->setValueExplicit(html_entity_decode($value), DataType::TYPE_STRING);
		$colNo++;
	}
} else {
	foreach ($headings as $field => $value) {
		$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
				->setValueExplicit(html_entity_decode($value), DataType::TYPE_STRING);
		$colNo++;
	}
}
$sheet->getStyle('A3:AH3')->applyFromArray($styleArray);

$no = 1;
foreach ($rResult as $aRow) {
	$row = array();
	//date of birth
	$dob = '';
	if ($aRow['patient_dob'] != null && trim($aRow['patient_dob']) != '' && $aRow['patient_dob'] != '0000-00-00') {
		$dob =  DateUtils::humanReadableDateFormat($aRow['patient_dob']);
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
	$gender = '';
	if ($aRow['patient_gender'] == 'male') {
		$gender = 'M';
	} else if ($aRow['patient_gender'] == 'female') {
		$gender = 'F';
	} else if ($aRow['patient_gender'] == 'not_recorded') {
		$gender = 'Unreported';
	}
	//sample collecion date
	$sampleCollectionDate = '';
	if (!empty($aRow['sample_collection_date'])) {
		$sampleCollectionDate =  DateUtils::humanReadableDateFormat($aRow['sample_collection_date']);
	}
	//treatment initiation date
	$treatmentInitiationDate = '';
	if (!empty($aRow['treatment_initiated_date'])) {
		$treatmentInitiationDate =  DateUtils::humanReadableDateFormat($aRow['treatment_initiated_date']);
	}
	//date of initiation of current regimen
	$dateOfInitiationOfCurrentRegimen = '';
	if (!empty($aRow['date_of_initiation_of_current_regimen'])) {
		$dateOfInitiationOfCurrentRegimen =  DateUtils::humanReadableDateFormat($aRow['date_of_initiation_of_current_regimen']);
	}
	//requested date
	$requestedDate = '';
	if (!empty($aRow['test_requested_on'])) {
		$requestedDate =  DateUtils::humanReadableDateFormat($aRow['test_requested_on']);
	}

	//request created date time
	$requestCreatedDatetime = '';
	if (!empty($aRow['request_created_datetime'])) {
		$requestCreatedDatetime =  DateUtils::humanReadableDateFormat($aRow['request_created_datetime'], true);
	}

	$sampleTestedOn = '';
	if (!empty($aRow['sample_tested_datetime'])) {
		$sampleTestedOn =  DateUtils::humanReadableDateFormat($aRow['sample_tested_datetime']);
	}

	$sampleReceivedOn = '';
	if (!empty($aRow['sample_received_at_vl_lab_datetime'])) {
		$sampleReceivedOn =  DateUtils::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
	}

	//set ARV adherecne
	$arvAdherence = '';
	if (trim($aRow['arv_adherance_percentage']) == 'good') {
		$arvAdherence = 'Good >= 95%';
	} else if (trim($aRow['arv_adherance_percentage']) == 'fair') {
		$arvAdherence = 'Fair 85-94%';
	} else if (trim($aRow['arv_adherance_percentage']) == 'poor') {
		$arvAdherence = 'Poor <85%';
	}
	//set sample rejection
	$sampleRejection = 'No';
	if (trim($aRow['is_sample_rejected']) == 'yes') {
		$sampleRejection = 'Yes';
	}

	//result dispatched date
	$resultDispatchedDate = '';
	if (!empty($aRow['result_printed_datetime'])) {
		$resultDispatchedDate =  DateUtils::humanReadableDateFormat($aRow['result_printed_datetime']);
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
	$row[] = $aRow['lab_name'];
	$row[] = $aRow['facility_name'];
	$row[] = $aRow['facility_code'];
	$row[] = ($aRow['facility_district']);
	$row[] = ($aRow['facility_state']);
	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
	$row[] = $aRow['patient_art_no'];
	$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
	}
	$row[] = $dob;
	$row[] = ($aRow['patient_age_in_years'] != null && trim($aRow['patient_age_in_years']) != '' && $aRow['patient_age_in_years'] > 0) ? $aRow['patient_age_in_years'] : 0;
	$row[] = $gender;
	$row[] = $sampleCollectionDate;
	$row[] = (isset($aRow['sample_name'])) ? ($aRow['sample_name']) : '';
	$row[] = $treatmentInitiationDate;
	$row[] = $aRow['current_regimen'];
	$row[] = $dateOfInitiationOfCurrentRegimen;
	$row[] = ($aRow['is_patient_pregnant']);
	$row[] = ($aRow['is_patient_breastfeeding']);
	$row[] = $arvAdherence;
	$row[] = isset($aRow['test_reason_name']) ? (str_replace("_", " ", $aRow['test_reason_name'])) : null;
	$row[] = ($aRow['request_clinician_name']);
	$row[] = $requestedDate;
	$row[] = $sampleRejection;
	$row[] = $sampleTestedOn;
	$row[] = $aRow['result'];
	$row[] = $aRow['result_value_log'];
	$row[] = $sampleReceivedOn;
	$row[] = $resultDispatchedDate;
	//$row[] = $tatdays;
	$row[] = ($aRow['lab_tech_comments']);
	$row[] = (isset($aRow['funding_source_name']) && trim($aRow['funding_source_name']) != '') ? ($aRow['funding_source_name']) : '';
	$row[] = (isset($aRow['i_partner_name']) && trim($aRow['i_partner_name']) != '') ? ($aRow['i_partner_name']) : '';
	$row[] = $requestCreatedDatetime;
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
$filename = 'VLSM-VL-REQUESTS-' . date('d-M-Y-H-i-s') . '-' . $general->generateRandomString(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
