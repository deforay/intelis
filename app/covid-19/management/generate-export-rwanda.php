<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$covid19Symptoms = $covid19Service->getCovid19Symptoms();
$covid19Comorbidities = $covid19Service->getCovid19Comorbidities();


$covid19Results = $covid19Service->getCovid19Results();
$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');


$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['covid19ResultQuery']) && trim((string) $_SESSION['covid19ResultQuery']) != "") {

	$output = [];

	$headings = array("S. No.", "Sample ID", "Remote Sample ID", "Testing Lab Name", "Sample Received On", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Patient ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Gender", "Sample Collection Date", "Symptoms Presented in last 14 days", "Co-morbidities", "Is Sample Rejected?", "Rejection Reason", "Recommended Corrective Action", "Sample Tested On", "Result", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner");
	if ($_SESSION['instanceType'] == 'standalone' && ($key = array_search("Remote Sample ID", $headings)) !== false) {
		unset($headings[$key]);
	}

	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
		$headings = array_values(array_diff($headings, ["Patient ID","Patient Name"]));
	}

	$no = 1;
	$sysmtomsArr = [];
	$comorbiditiesArr = [];
	$resultSet = $db->rawQuery($_SESSION['covid19ResultQuery']);
	foreach ($resultSet as $aRow) {
		$row = [];
		//set gender
		$gender = match (strtolower((string)$aRow['patient_gender'])) {
			'male', 'm' => 'M',
			'female', 'f' => 'F',
			'not_recorded', 'notrecorded', 'unreported' => 'Unreported',
			default => '',
		};

		//set sample rejection
		$sampleRejection = 'No';
		if (trim((string) $aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim((string) $aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
			$sampleRejection = 'Yes';
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
		/* To get Symptoms and Comorbidities details */
		$covid19SelectedSymptoms = $covid19Service->getCovid19SymptomsByFormId($aRow['covid19_id']);
		foreach ($covid19Symptoms as $symptomId => $symptomName) {
			if ($covid19SelectedSymptoms[$symptomId] == 'yes') {
				$sysmtomsArr[] = $symptomName . ':' . $covid19SelectedSymptoms[$symptomId];
			}
		}
		$covid19SelectedComorbidities = $covid19Service->getCovid19ComorbiditiesByFormId($aRow['covid19_id']);
		foreach ($covid19Comorbidities as $comId => $comName) {
			if ($covid19SelectedComorbidities[$symptomId] == 'yes') {
				$comorbiditiesArr[] = $comName . ':' . $covid19SelectedComorbidities[$comId];
			}
		}

		if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
			$aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
			$patientFname = $general->crypto('decrypt', $patientFname, $key);
			$patientLname = $general->crypto('decrypt', $patientLname, $key);
		}

		$row[] = $no;
		if ($_SESSION['instanceType'] == 'standalone') {
			$row[] = $aRow["sample_code"];
		} else {
			$row[] = $aRow["sample_code"];
			$row[] = $aRow["remote_sample_code"];
		}
		$row[] = ($aRow['lab_name']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
		$row[] = ($aRow['facility_name']);
		$row[] = $aRow['facility_code'];
		$row[] = ($aRow['facility_district']);
		$row[] = ($aRow['facility_state']);
		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			$row[] = $aRow['patient_id'];
			$row[] = $patientFname . " " . $patientLname;
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
		$aRow['patient_age'] ??= 0;
		$row[] = ($aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
		$row[] = $gender;
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		/* To get Symptoms and Comorbidities details */
		$row[] = implode(',', $sysmtomsArr);
		$row[] = implode(',', $comorbiditiesArr);
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason'];
		$row[] = $aRow['recommended_corrective_action_name'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
		$row[] = $covid19Results[$aRow['result']] ?? $aRow['result'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
		$row[] = $aRow['lab_tech_comments'];
		$row[] = $aRow['funding_source_name'] ?? null;
		$row[] = $aRow['i_partner_name'] ?? null;
		$output[] = $row;
		$no++;
	}

	if (isset($_SESSION['covid19ResultQueryCount']) && $_SESSION['covid19ResultQueryCount'] > 50000) {

		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'Covid-19-Export-Data-' . date('d-M-Y-H-i-s') . '.csv';
		$fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
		// we dont need the $output variable anymore
		unset($output);
		echo base64_encode((string) $fileName);
	} else {

		$excel = new Spreadsheet();
		$sheet = $excel->getActiveSheet();

		$sheet->fromArray($headings, null, 'A3');
		foreach ($output as $rowNo => $rowData) {
			$rRowCount = $rowNo + 4;
			$sheet->fromArray($rowData, null, 'A' . $rRowCount);
		}

		$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
		$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'Covid-19-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
		$writer->save($filename);
		echo base64_encode($filename);
	}
}
