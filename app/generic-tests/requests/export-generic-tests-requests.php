<?php

use App\Utilities\ExportJobUtility;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Services\GenericTestsService;
use App\Utilities\DateUtility;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, 'genericRequestQuery')) {
	return;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

$arr = $general->getGlobalConfig();

$globalKey = (string) $general->getGlobalConfig('key');


$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

//system config

if (isset($_SESSION['genericRequestQuery']) && trim((string) $_SESSION['genericRequestQuery']) !== "") {

	/* To get dynamic fields */
	$labels = [];
	$testTypeData = [];
	$output = [];

	$resultSet = $db->rawQuery($_SESSION['genericRequestQuery']);

	// 1. Process dynamic fields and headings FIRST so headers are accurately populated
	foreach ($resultSet as $key => $row) {
		ExportJobUtility::tick();
		$testTypeData[$row['sample_id']] = $genericTestsService->getDynamicFields($row['sample_id']);
		if (!empty($testTypeData[$row['sample_id']]['dynamicLabel'])) {
			foreach ($testTypeData[$row['sample_id']]['dynamicLabel'] as $id => $le) {
				$labels[$id] = $le;
			}
		}
	}

	$headings = [
		_translate("No."), 
		_translate("Test Type"), 
		_translate("Sample ID"), 
		_translate("Remote Sample ID"), 
		_translate("Health Facility Name"), 
		_translate("Testing Lab"), 
		_translate("Sample Receipt Date"), 
		_translate("Health Facility Code"), 
		_translate("District/County"), 
		_translate("Province/State"), 
		_translate("Patient ID."), 
		_translate("Patient Name"), 
		_translate("Date of Birth"), 
		_translate("Age"), 
		_translate("Sex"), 
		_translate("Date of Sample Collection"), 
		_translate("Sample Type"), 
		_translate("Is Patient Pregnant?"), 
		_translate("Is Patient Breastfeeding?"), 
		_translate("Requesting Clinican"), _translate("Request Date"),
		_translate("Is Sample Rejected?"), 
		_translate("Rejection Reason"), 
		_translate("Sample Tested On"), 
		_translate("Result"), 
		_translate("Date Result Dispatched"), 
		_translate("Comments"), 
		_translate("Funding Source"), _translate("Implementing Partner"),
		_translate("Request Created On")
	];
	 
	if ($general->isStandaloneInstance() && ($key = array_search("Remote Sample ID", $headings)) !== false) {
		unset($headings[$key]);
	}

	/* Assign the dynamic labels to the heading now that $labels is fully populated */
	if ($labels !== []) {
		$headings = array_merge($headings, $labels);
	}
//echo "<pre>"; print_r($labels); die;
	$no = 1;
	foreach ($resultSet as $key => $aRow) {
		ExportJobUtility::tick();
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
		$row[] = $aRow['test_standard_name'];
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
		$row[] = $aRow['patient_id'];
		if (!empty($globalKey) && !empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
			$aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $globalKey);
			$patientFname = $general->crypto('decrypt', $patientFname, $globalKey);
			$patientMname = $general->crypto('decrypt', $patientMname, $globalKey);
			$patientLname = $general->crypto('decrypt', $patientLname, $globalKey);
		}
		$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob'] ?? '');
		$row[] = $aRow['patient_age_in_years'];
		$row[] = $gender;
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = $aRow['sample_type_name'] ?: null;
		$row[] = ($aRow['is_patient_pregnant']);
		$row[] = ($aRow['is_patient_breastfeeding']);
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
//echo "<pre>"; print_r($labels); die;
		/* To assign the dynamic fields values safely mapped by sample_id */
		if ($labels !== []) {
			foreach (array_keys($labels) as $id) {
				$row[] = empty($testTypeData[$aRow['sample_id']]['dynamicValue'][$id]) ? "" : $testTypeData[$aRow['sample_id']]['dynamicValue'][$id];
			}
		}

		$output[] = $row;
		$no++;
	}

	if (isset($_SESSION['genericRequestQueryCount']) && $_SESSION['genericRequestQueryCount'] > 50000) {
		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-LAB-TESTS-REQUESTS-' . date('d-M-Y-H-i-s') . '.csv';
		$fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
		unset($output);
		echo _downloadToken($fileName);
	} else {
		$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-LAB-TESTS-REQUESTS-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(5) . '.xlsx';

		$writer = new Writer();
		$writer->openToFile($filename);
		$writer->addRow(Row::fromValues($headings));
		foreach ($output as $rowData) {
			$writer->addRow(Row::fromValues($rowData));
		}
		$writer->close();

		echo _downloadToken($filename);
	}
}
