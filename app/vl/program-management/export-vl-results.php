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
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var DateUtility $dateTimeUtil */
$dateTimeUtil = new DateUtility();

$arr = $general->getGlobalConfig();
$formId = $arr['vl_form'];

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['vlResultQuery']) && trim((string) $_SESSION['vlResultQuery']) != "") {

	$output = [];
	if ($formId == COUNTRY\CAMEROON && $arr['vl_excel_export_format'] == "cresar") {
		//$headings = array(_translate('No.'), _translate("Region of sending facility"), _translate("District of sending facility"), _translate("Sending facility"), _translate("Name of reference Lab"), _translate("Category of testing site"), _translate("Project"), _translate("CV Number"),  _translate("TAT"), _translate("Sample ID"), _translate("Existing ART Code"), _translate("ARV Protocol"), _translate("Gender"), _translate("Date of Birth"), _translate("Age"), _translate("Age Range"), _translate("Requested by contact"), _translate("Sample collection date"),  _translate("Sample reception date"), _translate("Sample Type"), _translate("Treatment start date"), _translate("Treatment Protocol"), _translate("Was sample send to another reference lab"), _translate("If sample was send to another lab, give name of lab"), _translate("Sample Rejected"), _translate("Sample Tested"), _translate("Test Platform"), _translate("Test platform detection limit"), _translate("Invalid test (yes or no)"), _translate("Invalid sample repeated (yes or no)"), _translate("Error codes (yes or no)"), _translate("Error codes values"), _translate("Tests repeated due to error codes (yes or no)"), _translate("New CV number"), _translate("Date of test"), _translate("Date of repeat test"), _translate("Result sent back to facility (yes or no)"), _translate("Date of result sent to facility"), _translate("Result Type"), _translate("Result Value"), _translate("Result Value Log"), _translate("Is suppressed"), _translate("Communication of rejected samples or high viral load (yes, no or NA)"), _translate("Observations"));
		$headings = array(_translate('S.No.'), _translate("Sample ID"), _translate('Region of sending facility'), _translate('District of sending facility'), _translate('Sending facility'), _translate('Project'), _translate('Existing ART Code'), _translate('Date of Birth'), _translate('Age'), _translate('Patient Name'), _translate('Gender'), _translate('Sample Creation Date'), _translate('Sample Created By'), _translate('Sample collection date'), _translate('Sample Type'), _translate('Requested by contact'), _translate('Treatment start date'), _translate("Treatment Protocol"), _translate('ARV Protocol'), _translate('CV Number'), _translate('Test Platform'), _translate("Test platform detection limit"), _translate("Sample reception date"), _translate("Sample Tested"), _translate("Date of test"), _translate("Date of result sent to facility"), _translate("Sample Rejected"), _translate("Communication of rejected samples or high viral load (yes, no or NA)"), _translate("Result Value"), _translate("Result Value Log"),  _translate("Is suppressed"), _translate("Name of reference Lab"), _translate("Category of testing site"), _translate("TAT"), _translate("Age Range"), _translate("Was sample send to another reference lab"), _translate("If sample was send to another lab, give name of lab"), _translate("Invalid test (yes or no)"), _translate("Invalid sample repeated (yes or no)"), _translate("Error codes (yes or no)"), _translate("Error codes values"), _translate("Tests repeated due to error codes (yes or no)"), _translate("New CV number"), _translate("Date of repeat test"), _translate("Result sent back to facility (yes or no)"), _translate("Result Type"), _translate("Observations"));
	} else {

		$headings = [_translate("S.No."), _translate("Sample ID"), _translate("Remote Sample ID"), _translate("Health Facility Name"), _translate("Testing Lab"), _translate("Health Facility Code"), _translate("District/County"), _translate("Province/State"), _translate("Unique ART No."), _translate("Patient Name"), _translate("Date of Birth"), _translate("Age"), _translate("Gender"), _translate("Patient Cellphone Number"), _translate("Date of Sample Collection"), _translate("Sample Type"), _translate("Date of Treatment Initiation"), _translate("Current Regimen"), _translate("Date of Initiation of Current Regimen"), _translate("Is Patient Pregnant?"), _translate("Is Patient Breastfeeding?"), _translate("ARV Adherence"), _translate("Indication for Viral Load Testing"), _translate("Requesting Clinican"), _translate("Requesting Clinican Cellphone Number"), _translate("Request Date"), _translate("Is Sample Rejected?"), _translate("Rejection Reason"), _translate("Recommended Corrective Action"), _translate("Sample Tested On"), _translate("Result (cp/ml)"), _translate("Result (log)"), _translate("Sample Receipt Date"), _translate("Date Result Dispatched"), _translate("Comments"), _translate("Funding Source"), _translate("Implementing Partner"), _translate("Request Created On")];

		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
			$headings = array_values(array_diff($headings, [_translate("Unique ART No."), _translate("Patient Name")]));
		}
	}

	if ($_SESSION['instanceType'] == 'standalone') {
		$headings = array_values(array_diff($headings, [_translate("Remote Sample ID")]));
	}


	$no = 1;
	foreach ($db->rawQueryGenerator($_SESSION['vlResultQuery']) as $aRow) {
		$row = [];

		$age = null;
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
				$key = base64_decode((string) $general->getGlobalConfig('key'));
				$aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
				$patientFname = $general->crypto('decrypt', $patientFname, $key);
				$patientMname = $general->crypto('decrypt', $patientMname, $key);
				$patientLname = $general->crypto('decrypt', $patientLname, $key);
			}
		}
		if ($formId == COUNTRY\CAMEROON && $arr['vl_excel_export_format'] == "cresar") {
			$row[] = $aRow['sample_code'];
			$row[] = $aRow['facility_state'];
			$row[] = $aRow['facility_district'];
			$row[] = $aRow['facility_name'];
			$row[] = $aRow['funding_source_name'] ?? null;
			$row[] = $aRow['patient_art_no'];
			$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
			$row[] = $aRow['patient_age_in_years'];
			$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
			$row[] = $gender;
			$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'] ?? '');
			$row[] = $aRow['createdBy'];
			$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
			$row[] = $aRow['sample_name'] ?: null;
			$row[] = $aRow['request_clinician_name'];
			$row[] = DateUtility::humanReadableDateFormat($aRow['treatment_initiated_date']);
			$row[] = $aRow['line_of_treatment'];
			$row[] = $aRow['current_regimen'];
			$row[] = $aRow['cv_number'];
			$row[] = $aRow['vl_test_platform'];
			if (!empty($aRow['vl_test_platform'])) {
				$row[] = $aRow['lower_limit'] . " - " . $aRow['higher_limit']; //Test platform detection limit
			} else {
				$row[] = "";
			}
			$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
			if ($aRow['sample_tested_datetime'] != "")
				$row[] = "Yes";
			else
				$row[] = "No";
			$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
			$row[] = DateUtility::humanReadableDateFormat($aRow['sample_dispatched_datetime']);
			$row[] = $sampleRejection;
			$row[] = $aRow['rejection_reason'];
			$row[] = $aRow['result'];
			$row[] = $logVal;
			$row[] = $aRow['vl_result_category'];
			$row[] = "Reference Lab";
			$row[] = ""; //Category Of testing site
			$row[] = ""; //TAT
			$row[] = ''; //Age range
			$row[] = ''; //Was sample send to another reference lab
			$row[] = ''; //If sample was send to another lab, give name of lab
			$row[] = ""; //Invalid test (yes or no);
			$row[] = ""; //Invalid sample repeated (yes or no)
			$row[] = "";  //Error codes (yes or no)
			$row[] = ""; //Error codes values
			$row[] = "";   //Tests repeated due to error codes (yes or no)
			$row[] = "";    //New CV Number
			$row[] = "";    //Date of repeat test
			$row[] = "";     //Result sent back to facility (yes or no)
			$ROW[] = ""; //Result type
			$row[] = "";    //Observations

		} else {
			$row[] = $aRow["sample_code"];

			if ($_SESSION['instanceType'] != 'standalone') {
				$row[] = $aRow["remote_sample_code"];
			}

			$row[] = $aRow['facility_name'];
			$row[] = $aRow['lab_name'];
			$row[] = $aRow['facility_code'];
			$row[] = $aRow['facility_district'];
			$row[] = $aRow['facility_state'];
			$row[] = $aRow['patient_art_no'];
			$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
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
			$row[] = $logVal;
			$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
			$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
			$row[] = $aRow['lab_tech_comments'];
			$row[] = $aRow['funding_source_name'] ?? null;
			$row[] = $aRow['i_partner_name'] ?? null;
			$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
		}
		$output[] = $row;
		$no++;
	}

	if ($formId == COUNTRY\CAMEROON && $arr['vl_excel_export_format'] == "cresar") {
		usort($output, function ($a, $b) {
			return $a['request_created_datetime'] <=> $b['request_created_datetime'];
		});
	}

	if (isset($_SESSION['vlResultQueryCount']) && $_SESSION['vlResultQueryCount'] > 100000) {

		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-VIRAL-LOAD-Data-' . date('d-M-Y-H-i-s') . '.csv';
		$fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
		// we dont need the $output variable anymore
		unset($output);
		echo base64_encode((string) $fileName);
	} else {

		$excel = new Spreadsheet();
		$sheet = $excel->getActiveSheet();
		//$sheet->setTitle('VL Results');

		$sheet->fromArray($headings, null, 'A1');

		$rowNo = 2;
		foreach ($output as $rowData) {
			$rRowCount = $rowNo++;
			$sheet->fromArray($rowData, null, 'A' . $rRowCount);
		}

		$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
		$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-VIRAL-LOAD-Data-' . date('d-M-Y-H-i-s') . '-' . $general->generateRandomString(5) . '.xlsx';
		$writer->save($filename);
		echo base64_encode($filename);
	}
}
