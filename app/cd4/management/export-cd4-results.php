<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$key = (string) $general->getGlobalConfig('key');

$globalConfig = $general->getGlobalConfig();
$formId = (int) $globalConfig['vl_form'];

$delimiter = $globalConfig['default_csv_delimiter'] ?? ',';
$enclosure = $globalConfig['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['cd4ResultQuery']) && trim((string) $_SESSION['cd4ResultQuery']) != "") {

	$output = [];

	$headings = [_translate("S.No."), _translate("Sample ID"), _translate("Remote Sample ID"), _translate("Health Facility Name"), _translate("Testing Lab"), _translate("Sample Reception Date"), _translate("Health Facility Code"), _translate("District/County"), _translate("Province/State"), _translate("Unique ART No."), _translate("Patient Name"), _translate("Date of Birth"), _translate("Age"), _translate("Sex"), _translate("Patient Cellphone Number"), _translate("Date of Sample Collection"), _translate("Sample Type"), _translate("Date of Treatment Initiation"), _translate("Current Regimen"), _translate("Date of Initiation of Current Regimen"), _translate("Is Patient Pregnant?"), _translate("Is Patient Breastfeeding?"), _translate("ARV Adherence"), _translate("Indication for Viral Load Testing"), _translate("Requesting Clinican"), _translate("Requesting Clinican Cellphone Number"), _translate("Request Date"), _translate("Is Sample Rejected?"), _translate("Rejection Reason"), _translate("Recommended Corrective Action"), _translate("Sample Tested On"), _translate("Result (cp/mL)"), _translate("Result Printed Date"), _translate("Result (log)"), _translate("Comments"), _translate("Funding Source"), _translate("Implementing Partner"), _translate("Request Created On")];

	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
		$headings = MiscUtility::removeMatchingElements($headings, [_translate("Unique ART No."), _translate("Patient Name")]);
	}


	if ($general->isStandaloneInstance()) {
		$headings = MiscUtility::removeMatchingElements($headings, [_translate("Remote Sample ID")]);
	}


	$no = 1;
	$resultSet = $db->rawQueryGenerator($_SESSION['cd4ResultQuery']);
	foreach ($resultSet as $aRow) {
		$row = [];

		$age = _translate('Not Reported');
		$aRow['patient_age_in_years'] = (int) $aRow['patient_age_in_years'];
		$age = DateUtility::ageInYearMonthDays($aRow['patient_dob'] ?? '');
		if (!empty($age) && $age['year'] > 0) {
			$aRow['patient_age_in_years'] = $age['year'];
		}

		$gender = MiscUtility::getGenderFromString($aRow['patient_gender']);

		//set ARV adherecne
		$arvAdherence = '';
		if (trim((string) $aRow['arv_adherance_percentage']) == 'good') {
			$arvAdherence = 'Good >= 95%';
		} elseif (trim((string) $aRow['arv_adherance_percentage']) == 'fair') {
			$arvAdherence = 'Fair 85-94%';
		} elseif (trim((string) $aRow['arv_adherance_percentage']) == 'poor') {
			$arvAdherence = 'Poor <85%';
		}

		$sampleRejection = ($aRow['is_sample_rejected'] == 'yes' || ($aRow['reason_for_sample_rejection'] != null && $aRow['reason_for_sample_rejection'] > 0)) ? 'Yes' : 'No';


		//set result log value
		$logVal = '';
		if (!empty($aRow['result_value_log']) && is_numeric($aRow['result_value_log'])) {
			$logVal = round($aRow['result_value_log'], 1);
		}

		if ($aRow['patient_first_name'] != '') {
			$patientFname = $aRow['patient_first_name'] ?? '';
		} else {
			$patientFname = '';
		}
		if ($aRow['patient_middle_name'] != '') {
			$patientMname = $aRow['patient_middle_name'] ?? '';
		} else {
			$patientMname = '';
		}
		if ($aRow['patient_last_name'] != '') {
			$patientLname = $aRow['patient_last_name'] ?? '';
		} else {
			$patientLname = '';
		}

		$row[] = $no;
		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
				$aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
				$patientFname = $general->crypto('decrypt', $patientFname, $key);
				$patientMname = $general->crypto('decrypt', $patientMname, $key);
				$patientLname = $general->crypto('decrypt', $patientLname, $key);
			}
		}
		$row[] = $aRow["sample_code"];

		if (!$general->isStandaloneInstance()) {
			$row[] = $aRow["remote_sample_code"];
		}

		$row[] = $aRow['facility_name'];
		$row[] = $aRow['lab_name'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
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
		$row[] = $aRow['patient_mobile_number'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = $aRow['sample_name'] ?: null;
		$row[] = DateUtility::humanReadableDateFormat($aRow['treatment_initiated_date']);
		$row[] = $aRow['current_regimen'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['date_of_initiation_of_current_regimen']);
		$row[] = $aRow['is_patient_pregnant'];
		$row[] = $aRow['is_patient_breastfeeding'];
		$row[] = $arvAdherence;
		$row[] = str_replace("_", " ", (string) $aRow['test_reason_name']);
		$row[] = $aRow['request_clinician_name'];
		$row[] = $aRow['request_clinician_phone_number'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['test_requested_on']);
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason'];
		$row[] = $aRow['recommended_corrective_action_name'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
		$row[] = $aRow['result'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
		$row[] = $logVal;
		$row[] = $aRow['lab_tech_comments'];
		$row[] = $aRow['funding_source_name'] ?? null;
		$row[] = $aRow['i_partner_name'] ?? null;
		$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
		$output[] = $row;
		$no++;
	}


	if (isset($_SESSION['cd4ResultQueryCount']) && $_SESSION['cd4ResultQueryCount'] > 50000) {

		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-CD4-Data-' . date('d-M-Y-H-i-s') . '.csv';
		$fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
		// we dont need the $output variable anymore
		unset($output);
		echo base64_encode((string) $fileName);
	} else {

		$excel = new Spreadsheet();
		$sheet = $excel->getActiveSheet();

		$sheet->fromArray($headings, null, 'A1'); // Write headings
		$sheet->fromArray($output, null, 'A2');  // Write data starting from row 2

		$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
		$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-CD4-Data-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(5) . '.xlsx';
		$writer->save($filename);
		echo urlencode(basename($filename));
	}
}
