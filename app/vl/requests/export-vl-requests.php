<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

$output = [];

$headings = [_translate("S.No."), _translate("Sample ID"), _translate("Remote Sample ID"), _translate("Testing Lab"), _translate("Health Facility Name"), _translate("Health Facility Code"), _translate("District/County"), _translate("Province/State"), _translate("Unique ART No."), _translate("Patient Name"), _translate("Date of Birth"), _translate("Age"), _translate("Gender"), _translate("Date of Sample Collection"), _translate("Sample Type"), _translate("Date of Treatment Initiation"), _translate("Current Regimen"), _translate("Date of Initiation of Current Regimen"), _translate("Is Patient Pregnant?"), _translate("Is Patient Breastfeeding?"), _translate("ARV Adherence"), _translate("Indication for Viral Load Testing"), _translate("Requesting Clinican"), _translate("Request Date"), _translate("Is Sample Rejected?"), _translate("Sample Tested On"), _translate("Result (cp/ml)"), _translate("Result (log)"), _translate("Sample Receipt Date"), _translate("Date Result Dispatched"), _translate("Comments"), _translate("Funding Source"), _translate("Implementing Partner"), _translate("Request Created On")];
if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
	$remove = [_translate("Unique ART No."), _translate("Patient Name")];
	$headings = array_values(array_diff($headings, [_translate("Unique ART No."), _translate("Patient Name")]));
}
if ($_SESSION['instanceType'] == 'standalone') {
	$headings = array_values(array_diff($headings, [_translate("Remote Sample ID")]));
}
$no = 1;
foreach ($db->rawQueryGenerator($_SESSION['vlRequestQuery']) as $aRow) {
	$row = [];
	$age = null;
	$aRow['patient_age_in_years'] = (int) $aRow['patient_age_in_years'];
	$age = DateUtility::ageInYearMonthDays($aRow['patient_dob'] ?? '');
	if (!empty($age) && $age['year'] > 0) {
		$aRow['patient_age_in_years'] = $age['year'];
	}

	$gender = MiscUtility::getGenderFromString($aRow['patient_gender']);

	$arvAdherence = '';
	if (trim($aRow['arv_adherance_percentage']) == 'good') {
		$arvAdherence = 'Good >= 95%';
	} elseif (trim($aRow['arv_adherance_percentage']) == 'fair') {
		$arvAdherence = 'Fair 85-94%';
	} elseif (trim($aRow['arv_adherance_percentage']) == 'poor') {
		$arvAdherence = 'Poor <85%';
	}

	$sampleRejection = ($aRow['is_sample_rejected'] == 'yes' || ($aRow['reason_for_sample_rejection'] != null && $aRow['reason_for_sample_rejection'] > 0)) ? 'Yes' : 'No';


	if ($aRow['patient_first_name'] != '') {
		$patientFname = $aRow['patient_first_name'];
	} else {
		$patientFname = '';
	}
	if ($aRow['patient_middle_name'] != '') {
		$patientMname = $aRow['patient_middle_name'];
	} else {
		$patientMname = '';
	}
	if ($aRow['patient_last_name'] != '') {
		$patientLname = $aRow['patient_last_name'];
	} else {
		$patientLname = '';
	}

	$row[] = $no;
	$row[] = $aRow["sample_code"];

	if ($_SESSION['instanceType'] != 'standalone') {
		$row[] = $aRow["remote_sample_code"];
	}

	$row[] = $aRow['lab_name'] ?? null;
	$row[] = $aRow['facility_name'];
	$row[] = $aRow['facility_code'];
	$row[] = $aRow['facility_district'];
	$row[] = $aRow['facility_state'];
	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
		if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
			$key = base64_decode($general->getGlobalConfig('key'));
			$aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
			$patientFname = $general->crypto('decrypt', $patientFname, $key);
			$patientMname = $general->crypto('decrypt', $patientMname, $key);
			$patientLname = $general->crypto('decrypt', $patientLname, $key);
		}
		$row[] = $aRow['patient_art_no'];
		$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
	}
	$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
	$aRow['patient_age_in_years'] ??= 0;
	$row[] = ($aRow['patient_age_in_years'] > 0) ? $aRow['patient_age_in_years'] : 0;
	$row[] = $gender;
	$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
	$row[] = $aRow['sample_name'] ?? null;
	$row[] = DateUtility::humanReadableDateFormat($aRow['treatment_initiated_date']);
	$row[] = $aRow['current_regimen'];
	$row[] = DateUtility::humanReadableDateFormat($aRow['date_of_initiation_of_current_regimen']);
	$row[] = $aRow['is_patient_pregnant'];
	$row[] = $aRow['is_patient_breastfeeding'];
	$row[] = $arvAdherence;
	$row[] = isset($aRow['test_reason_name']) ? (str_replace("_", " ", $aRow['test_reason_name'])) : null;
	$row[] = $aRow['request_clinician_name'];
	$row[] = DateUtility::humanReadableDateFormat($aRow['test_requested_on']);
	$row[] = $sampleRejection;
	$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
	$row[] = $aRow['result'];
	$row[] = $aRow['result_value_log'];
	$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
	$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
	$row[] = $aRow['lab_tech_comments'];
	$row[] = $aRow['funding_source_name'] ?? null;
	$row[] = $aRow['i_partner_name'] ?? null;
	$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
	$output[] = $row;
	unset($row);
	$no++;
}

if (isset($_SESSION['vlRequestQueryCount']) && $_SESSION['vlRequestQueryCount'] > 100000) {

	$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-VL-REQUESTS-' . date('d-M-Y-H-i-s') . '.csv';
	$fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
	// we dont need the $output variable anymore
	unset($output);
	echo base64_encode($fileName);
} else {

	$excel = new Spreadsheet();
	$sheet = $excel->getActiveSheet();

	$sheet->fromArray($headings, null, 'A3');

	$rowNo = 3;
	foreach ($output as $rowData) {
		$rRowCount = $rowNo++;
		$sheet->fromArray($rowData, null, 'A' . $rRowCount);
	}

	$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
	$filename = 'VLSM-VL-REQUESTS-' . date('d-M-Y-H-i-s') . '-' . $general->generateRandomString(6) . '.xlsx';
	$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
	echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
}
