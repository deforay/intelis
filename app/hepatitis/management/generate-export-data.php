<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);

$hepatitisResults = $hepatitisService->getHepatitisResults();

$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['hepatitisResultQuery']) && trim((string) $_SESSION['hepatitisResultQuery']) !== "") {

	$headings = ["S.No.", "Sample ID", "Testing Lab Name", "Sample Received On", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Sex", "Sample Collection Date", "Is Sample Rejected?", "Rejection Reason", "Sample Tested On", "HCV VL Result", "HBV VL Result", "Date Result Dispatched", "Result Status", "Comments", "Funding Source", "Implementing Partner"];
	$output = [];

	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
		$headings = array_values(array_diff($headings, ["Patient ID", "Patient Name"]));
	}

	$no = 1;
	$resultSet = $db->rawQuery($_SESSION['hepatitisResultQuery']);
	foreach ($resultSet as $aRow) {
		$row = [];
		//set gender
		$gender = match (strtolower((string)$aRow['patient_gender'])) {
			'male', 'm' => 'M',
			'female', 'f' => 'F',
			'not_recorded', 'notrecorded', 'unreported' => 'Unreported',
			default => '',
		};

		$sampleRejection = ($aRow['is_sample_rejected'] == 'yes' || ($aRow['reason_for_sample_rejection'] != null && $aRow['reason_for_sample_rejection'] > 0)) ? 'Yes' : 'No';

		$sampleCode = $general->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

		if (!empty($aRow['patient_name'])) {
			$patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow['patient_id']));
		} else {
			$patientFname = '';
		}
		if ($aRow['patient_last_name'] != '') {
			$patientLname = ($general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']));
		} else {
			$patientLname = '';
		}

		if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
			$aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
			$patientFname = $general->crypto('decrypt', $patientFname, $key);
			$patientLname = $general->crypto('decrypt', $patientLname, $key);
		}

		$row[] = $no;
		$row[] = $aRow[$sampleCode];
		$row[] = ($aRow['labName']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
		$row[] = ($aRow['facility_name']);
		$row[] = $aRow['facility_code'];
		$row[] = ($aRow['facility_district']);
		$row[] = ($aRow['facility_state']);
		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			$row[] = $aRow['patient_id'];
			$row[] = $patientFname . " " . $patientLname;
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob'] ?? '');
		$aRow['patient_age'] ??= 0;
		$row[] = ($aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
		$row[] = $gender;
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
		//$row[] = $hepatitisResults[$aRow['result']] ?? $aRow['result'];
		$row[] = $aRow['hcv_vl_count'];
		$row[] = $aRow['hbv_vl_count'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
		$row[] = $aRow['status_name'];
		$row[] = ($aRow['lab_tech_comments']);
		$row[] = $aRow['funding_source_name'] ?? null;
		$row[] = $aRow['i_partner_name'] ?? null;
		$output[] = $row;
		$no++;
	}


	if (isset($_SESSION['hepatitisResultQueryCount']) && $_SESSION['hepatitisResultQueryCount'] > 50000) {

		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'Hepatitis-Export-Data-' . date('d-M-Y-H-i-s') . '.csv';
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
		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'Hepatitis-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
		$writer->save($fileName);
		echo urlencode(basename($fileName));
	}
}
