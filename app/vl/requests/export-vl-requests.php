<?php

// vl/requests/export-vl-requests.php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use OpenSpout\Common\Entity\Row;
use App\Services\DatabaseService;
use OpenSpout\Writer\XLSX\Writer;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', '512M');
set_time_limit(300);
ini_set('max_execution_time', 300);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$globalConf = $general->getGlobalConfig();
$formId = (int) $globalConf['vl_form'];

$delimiter = $globalConf['default_csv_delimiter'] ?? ',';
$enclosure = $globalConf['default_csv_enclosure'] ?? '"';

if ($formId == COUNTRY\CAMEROON && $globalConf['vl_excel_export_format'] == "cresar") {
	$headings = array(_translate('S.No.'), _translate("Sample ID"), _translate('Region of sending facility'), _translate('District of sending facility'), _translate('Sending facility'), _translate('Project'), _translate('Existing ART Code'), _translate('Date of Birth'), _translate('Age'), _translate('Patient Name'), _translate('Sex'), _translate('Universal Insurance Code'), _translate('Sample Creation Date'), _translate('Sample Created By'), _translate('Sample collection date'), _translate('Sample Type'), _translate('Requested by contact'), _translate('Treatment start date'), _translate("Treatment Protocol"), _translate('ARV Protocol'), _translate('CV Number'), _translate('Batch Code'), _translate('Test Platform'), _translate("Test platform detection limit"), _translate("Sample Tested"), _translate("Date of test"), _translate("Date of result sent to facility"), _translate("Sample Rejected"), _translate("Communication of rejected samples or high viral load (yes, no or NA)"), _translate("Result Value"), _translate("Result Printed Date"), _translate("Result Value Log"),  _translate("Is suppressed"), _translate("Name of reference Lab"), _translate("Sample Reception Date"), _translate("Category of testing site"), _translate("TAT"), _translate("Age Range"), _translate("Was sample sent to another reference lab?"), _translate("If sample was sent to another lab, give name of lab"), _translate("Invalid test (yes or no)"), _translate("Invalid sample repeated (yes or no)"), _translate("Error codes (yes or no)"), _translate("Error codes values"), _translate("Tests repeated due to error codes (yes or no)"), _translate("New CV number"), _translate("Date of repeat test"), _translate("Result sent back to facility (yes or no)"), _translate("Result Type"), _translate("Observations"));
} else {
	$headings = [_translate("S.No."), _translate("Sample ID"), _translate("Remote Sample ID"), _translate("Testing Lab"), _translate("Lab Assigned Code"), _translate("Sample Reception Date"), _translate("Health Facility Name"), _translate("Health Facility Code"), _translate("District/County"), _translate("Province/State"), _translate("Unique ART No."), _translate("Patient Name"),  _translate("Patient Contact Number"), _translate("Clinician Contact Number"), _translate("Date of Birth"), _translate("Age"), _translate("Sex"),  _translate("Universal Insurance Code"), _translate("Date of Sample Collection"), _translate("Sample Type"), _translate("Date of Treatment Initiation"), _translate("Current Regimen"), _translate("Date of Initiation of Current Regimen"), _translate("Is Patient Pregnant?"), _translate("Is Patient Breastfeeding?"), _translate("ARV Adherence"), _translate("Indication for Viral Load Testing"), _translate("Requesting Clinican"), _translate("Request Date"), _translate("Is Sample Rejected?"), _translate("Freezer"), _translate("Rack"), _translate("Box"), _translate("Position"), _translate("Volume (ml)"), _translate("Sample Tested On"), _translate("Result (cp/mL)"), _translate("Result Printed Date"), _translate("Result (log)"), _translate("Comments"), _translate("Funding Source"), _translate("Implementing Partner"), _translate("Request Created On")];
}

if ($general->isStandaloneInstance()) {
	$headings = MiscUtility::removeMatchingElements($headings, [_translate("Remote Sample ID")]);
}

if ($formId != COUNTRY\DRC) {
	$headings = MiscUtility::removeMatchingElements($headings, [_translate('Freezer'), _translate("Rack"), _translate("Box"), _translate("Position"), _translate("Volume (ml)")]);
}

if ($formId != COUNTRY\CAMEROON) {
	$headings = MiscUtility::removeMatchingElements($headings, [_translate("Universal Insurance Code"), _translate("Lab Assigned Code")]);
}

$key = (string) $general->getGlobalConfig('key');

// Row builder function
$buildRow = function ($aRow, $no) use ($general, $key, $formId, $globalConf) {
	$row = [];

	$age = null;
	$aRow['patient_age_in_years'] = (int) $aRow['patient_age_in_years'];
	$age = DateUtility::ageInYearMonthDays($aRow['patient_dob'] ?? '');
	if (!empty($age) && $age['year'] > 0) {
		$aRow['patient_age_in_years'] = $age['year'];
	}

	$gender = MiscUtility::getGenderFromString($aRow['patient_gender']);

	$arvAdherence = '';
	if (trim((string) $aRow['arv_adherance_percentage']) == 'good') {
		$arvAdherence = 'Good >= 95%';
	} elseif (trim((string) $aRow['arv_adherance_percentage']) == 'fair') {
		$arvAdherence = 'Fair 85-94%';
	} elseif (trim((string) $aRow['arv_adherance_percentage']) == 'poor') {
		$arvAdherence = 'Poor <85%';
	}

	$sampleRejection = ($aRow['is_sample_rejected'] == 'yes' || ($aRow['reason_for_sample_rejection'] != null && $aRow['reason_for_sample_rejection'] > 0)) ? 'Yes' : 'No';

	$patientFname = $aRow['patient_first_name'] ?? '';
	$patientMname = $aRow['patient_middle_name'] ?? '';
	$patientLname = $aRow['patient_last_name'] ?? '';

	$row[] = $no;

	if ($formId == COUNTRY\CAMEROON && $globalConf['vl_excel_export_format'] == "cresar") {
		$lineOfTreatment = '';
		if ($aRow['line_of_treatment'] == 1)
			$lineOfTreatment = '1st Line';
		elseif ($aRow['line_of_treatment'] == 2)
			$lineOfTreatment = '2nd Line';
		elseif ($aRow['line_of_treatment'] == 3)
			$lineOfTreatment = '3rd Line';
		elseif ($aRow['line_of_treatment'] == 'n/a')
			$lineOfTreatment = 'N/A';

		$row[] = $aRow['sample_code'];
		$row[] = $aRow['facility_state'];
		$row[] = $aRow['facility_district'];
		$row[] = $aRow['facility_name'];
		$row[] = $aRow['funding_source_name'] ?? null;
		$row[] = $aRow['patient_art_no'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
		$row[] = $aRow['patient_age_in_years'];
		$row[] = trim("$patientFname $patientMname $patientLname");
		$row[] = $gender;
		if ($formId == COUNTRY\CAMEROON) {
			$row[] = $aRow['health_insurance_code'];
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'] ?? '');
		$row[] = $aRow['createdBy'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = $aRow['sample_name'];
		$row[] = $aRow['request_clinician_name'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['treatment_initiated_date']);
		$row[] = $lineOfTreatment;
		$row[] = $aRow['current_regimen'];
		$row[] = $aRow['cv_number'];
		$row[] = $aRow['batch_code'];
		$row[] = $aRow['vl_test_platform'];
		if (!empty($aRow['vl_test_platform'])) {
			$row[] = $aRow['lower_limit'] . " - " . $aRow['higher_limit'];
		} else {
			$row[] = "";
		}
		$row[] = !empty($aRow['sample_tested_datetime']) ? "Yes" : "No";
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_dispatched_datetime']);
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason'];
		$row[] = $aRow['result'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
		$row[] = $aRow['result_value_log'];
		$row[] = $aRow['vl_result_category'];
		$row[] = "Reference Lab";
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
		$row[] = "";
		$row[] = "";
		$row[] = '';
		$row[] = '';
		$row[] = '';
		$row[] = "";
		$row[] = "";
		$row[] = "";
		$row[] = "";
		$row[] = "";
		$row[] = "";
		$row[] = "";
		$row[] = "";
		$row[] = "";
	} else {
		$row[] = $aRow["sample_code"];

		if (!$general->isStandaloneInstance()) {
			$row[] = $aRow["remote_sample_code"];
		}

		$row[] = $aRow['lab_name'];
		if ($formId == COUNTRY\CAMEROON) {
			$row[] = $aRow['lab_assigned_code'];
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
		$row[] = $aRow['facility_name'];
		$row[] = $aRow['facility_code'];
		$row[] = $aRow['facility_district'];
		$row[] = $aRow['facility_state'];

		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			if (!empty($key) && !empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
				$aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
				$patientFname = $general->crypto('decrypt', $patientFname, $key);
				$patientMname = $general->crypto('decrypt', $patientMname, $key);
				$patientLname = $general->crypto('decrypt', $patientLname, $key);
			}
			$row[] = $aRow['patient_art_no'];
			$row[] = trim("$patientFname $patientMname $patientLname");
		}

		$row[] = $aRow['patient_mobile_number'];
		$row[] = $aRow['request_clinician_phone_number'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob'] ?? '');
		$aRow['patient_age_in_years'] ??= 0;
		$row[] = ($aRow['patient_age_in_years'] > 0) ? $aRow['patient_age_in_years'] : 0;
		$row[] = $gender;
		if ($formId == COUNTRY\CAMEROON) {
			$row[] = $aRow['health_insurance_code'] ?? null;
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = $aRow['sample_name'] ?? null;
		$row[] = DateUtility::humanReadableDateFormat($aRow['treatment_initiated_date'] ?? '');
		$row[] = $aRow['current_regimen'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['date_of_initiation_of_current_regimen'] ?? '');
		$row[] = $aRow['is_patient_pregnant'];
		$row[] = $aRow['is_patient_breastfeeding'];
		$row[] = $arvAdherence;
		$row[] = isset($aRow['test_reason_name']) ? (str_replace("_", " ", (string) $aRow['test_reason_name'])) : null;
		$row[] = $aRow['request_clinician_name'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['test_requested_on'] ?? '');
		$row[] = $sampleRejection;

		if ($formId == COUNTRY\DRC) {
			$formAttributes = !empty($aRow['form_attributes']) ? json_decode($aRow['form_attributes']) : null;
			$storageObj = isset($formAttributes->storage) ? json_decode($formAttributes->storage) : null;

			$row[] = $storageObj->storageCode ?? '';
			$row[] = $storageObj->rack ?? '';
			$row[] = $storageObj->box ?? '';
			$row[] = $storageObj->position ?? '';
			$row[] = $storageObj->volume ?? '';
		}

		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
		$row[] = $aRow['result'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
		$row[] = $aRow['result_value_log'];
		$row[] = $aRow['lab_tech_comments'] ?? null;
		$row[] = $aRow['funding_source_name'] ?? null;
		$row[] = $aRow['i_partner_name'] ?? null;
		$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'] ?? '', true);
	}

	return $row;
};

// Build filter info for header
$nameValue = '';
foreach ($_POST as $key => $value) {
	if (trim($value) != '' && trim($value) != '-- Select --') {
		$nameValue .= str_replace("_", " ", $key) . " : " . $value . "  ";
	}
}

// Prepare headings (with alpha-numeric conversion if requested)
$processedHeadings = $headings;
if (isset($_POST['withAlphaNum']) && $_POST['withAlphaNum'] == 'yes') {
	$processedHeadings = array_map(function ($value) {
		$string = str_replace(' ', '', $value);
		return preg_replace('/[^A-Za-z0-9\-]/', '', $string);
	}, $headings);
}

$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-VL-REQUESTS-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(6) . '.xlsx';

$writer = new Writer();
$writer->openToFile($filename);

// Write filter info row (merged cell effect simulated by single wide row)
$writer->addRow(Row::fromValues([html_entity_decode($nameValue)]));

// Empty row for spacing
$writer->addRow(Row::fromValues(['']));

// Write headings
$writer->addRow(Row::fromValues(array_map('html_entity_decode', $processedHeadings)));

// Stream data
$resultSet = $db->rawQueryGenerator($_SESSION['vlRequestQuery']);
$no = 1;

foreach ($resultSet as $aRow) {
	$row = $buildRow($aRow, $no++);
	$writer->addRow(Row::fromValues($row));

	// Periodic garbage collection
	if ($no % 5000 === 0) {
		gc_collect_cycles();
	}
}

$writer->close();
echo urlencode(basename($filename));
