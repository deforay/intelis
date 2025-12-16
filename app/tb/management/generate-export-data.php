<?php




use App\Services\TbService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);
$tbResults = $tbService->getTbResults();

/* Global config data */
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();
$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['tbResultQuery']) && trim((string) $_SESSION['tbResultQuery']) !== "") {

	$output = [];

	$headings = ["S. No.", "Sample ID", "Remote Sample ID", "Testing Lab Name", "Date specimen Received", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Case ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Sex", "Date specimen collected", "Reason for Test Request", "Date specimen Entered", "Specimen Status", "Specimen Type", "Is Sample Rejected?", "Rejection Reason", "Recommended Corrective Action", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released"];
	if ($general->isStandaloneInstance() && ($key = array_search("Remote Sample ID", $headings)) !== false) {
		unset($headings[$key]);
	}

	if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
		$headings = array_values(array_diff($headings, ["Case ID", "Patient Name"]));
	}

	$no = 1;
	$resultSet = $db->rawQuery($_SESSION['tbResultQuery']);
	foreach ($resultSet as $aRow) {
		$row = [];

		// Get testing platform and test method
		$tbTestQuery = "SELECT * from tb_tests where tb_id= ? ORDER BY tb_test_id ASC";
		$tbTestInfo = $db->rawQuery($tbTestQuery, [$aRow['tb_id']]);

		foreach ($tbTestInfo as $indexKey => $rows) {
			$testPlatform = $rows['testing_platform'];
			$testMethod = $rows['test_name'];
		}


		//set gender
		$gender = match (strtolower((string) $aRow['patient_gender'])) {
			'male', 'm' => 'M',
			'female', 'f' => 'F',
			'not_recorded', 'notrecorded', 'unreported' => 'Unreported',
			default => '',
		};

		//set sample rejection
		$sampleRejection = 'No';
		if (trim((string) $aRow['is_sample_rejected']) === 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim((string) $aRow['reason_for_sample_rejection']) !== '' && $aRow['reason_for_sample_rejection'] > 0)) {
			$sampleRejection = 'Yes';
		}

		$sampleCode = $general->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

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

		$row[] = $no;
		if ($general->isStandaloneInstance()) {
			$row[] = $aRow["sample_code"];
		} else {
			$row[] = $aRow["sample_code"];
			$row[] = $aRow["remote_sample_code"];
		}
		$row[] = ($aRow['lab_name']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
		$row[] = ($aRow['labTechnician']);
		$row[] = ($aRow['facility_district']);
		$row[] = ($aRow['facility_state']);
		$row[] = ($aRow['facility_name']);
		if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
			$row[] = $aRow['patient_id'];
			$row[] = $patientFname . " " . $patientLname;
		}
		$row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
		$row[] = ($aRow['patient_age'] != null && trim((string) $aRow['patient_age']) !== '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
		$row[] = ($aRow['patient_gender']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = ($aRow['test_reason_name']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
		$row[] = ($aRow['status_name']);
		$row[] = ($aRow['sample_name']);
		$row[] = $sampleRejection;
		$row[] = $aRow['rejection_reason'];
		$row[] = $aRow['recommended_corrective_action_name'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
		$row[] = ($testPlatform);
		$row[] = ($testMethod);
		$row[] = $tbResults[$aRow['result']] ?? $aRow['result'] ?? null;
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');

		$output[] = $row;
		$no++;
	}


	if (isset($_SESSION['tbResultQueryCount']) && $_SESSION['tbResultQueryCount'] > 50000) {

		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-TB-Export-Data-' . date('d-M-Y-H-i-s') . '.csv';
		$fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
		// we dont need the $output variable anymore
		unset($output);
		echo base64_encode((string) $fileName);
	} else {
		$excel = new Spreadsheet();
		$sheet = $excel->getActiveSheet();
		$sheet->setTitle('TB Results');

		$sheet->fromArray($headings, null, 'A3');

		foreach ($output as $rowNo => $rowData) {
			$rRowCount = $rowNo + 4;
			$sheet->fromArray($rowData, null, 'A' . $rRowCount);
		}

		$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
		$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-VIRAL-LOAD-Data-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(5) . '.xlsx';
		$writer->save($filename);
		echo urlencode(basename($filename));
	}
}
