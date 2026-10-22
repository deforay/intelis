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

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

//system config

if (isset($_SESSION['genericRequestQuery']) && trim((string) $_SESSION['genericRequestQuery']) !== "") {

	/* To get dynamic fields */
	$labels = [];

	$output = [];

	$headings = ["No.", "Test Type","Sample ID", "Remote Sample ID", "Health Facility Name", "Testing Lab", "Sample Receipt Date", "Health Facility Code", "District/County", "Province/State", "Patient ID.", "Patient Name", "Date of Birth", "Age", "Sex", "Date of Sample Collection", "Sample Type", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "Result", "Date Result Dispatched", "Comments", "Request Created On"];
	 
	if ($general->isStandaloneInstance() && ($key = array_search("Remote Sample ID", $headings)) !== false) {
		unset($headings[$key]);
	}
	/* Assign the dynamic labels to the heading */
	if ($labels !== []) {
		$headings = array_merge($headings, $labels);
	}

	$no = 1;
	$resultSet = $db->rawQuery($_SESSION['genericRequestQuery']);
	foreach ($resultSet as $key => $row) {
		// Key the structure mapping strictly by sample_id
		$testType[$row['sample_id']] = $genericTestsService->getDynamicFields($row['sample_id']);
		foreach ($testType[$row['sample_id']]['dynamicLabel'] as $id => $le) {
			$labels[$id] = $le;
		}
	}


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
		$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob'] ?? '');
		$row[] = $aRow['patient_age_in_years'];
		$row[] = $gender;
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = $aRow['sample_type_name'] ?: null;
		$row[] = ($aRow['is_patient_pregnant']);
		$row[] = ($aRow['is_patient_breastfeeding']);
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason_name'];
		$row[] = $sampleTestedOn;
		$row[] = $aRow['result'];
		$row[] = $resultDispatchedDate;
		$row[] = ($aRow['lab_tech_comments']);
		$row[] = $requestCreatedDatetime;

		/* To assign the dynamic fields values */
		if ($labels !== []) {
			foreach (array_keys($labels) as $id) {
				// Look up the dynamic field value mapped securely by sample_id
				$row[] = empty($testType[$aRow['sample_id']]['dynamicValue'][$id]) ? "" : $testType[$aRow['sample_id']]['dynamicValue'][$id];
			}
		}

		$output[] = $row;
		$no++;
	}

	if (isset($_SESSION['genericRequestQueryCount']) && $_SESSION['genericRequestQueryCount'] > 50000) {
		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-LAB-TESTS-REQUESTS-' . date('d-M-Y-H-i-s') . '.csv';
		$fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
		// we dont need the $output variable anymore
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
