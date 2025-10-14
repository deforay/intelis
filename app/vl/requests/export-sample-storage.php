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
ini_set('max_execution_time', 300000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();
$formId = (int) $arr['vl_form'];


$output = [];

$headings = [_translate("S.No."), _translate("Sample ID"), _translate("Facility Name"), _translate("Sample Collection Date"), _translate("Patient ID"), _translate("Patient Name"), _translate("Freezer Code"), _translate("Rack"), _translate("Box"), _translate("Position"), _translate("Volume(ml)"), _translate("Date Out"), _translate("Comments"), _translate('Status')];


$no = 1;

$key = (string) $general->getGlobalConfig('key');
$resultSet = $db->rawQueryGenerator($_SESSION['sampleStorageQuery']);

foreach ($resultSet as $aRow) {
	$row = [];

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
	$row[] = $aRow['facility_name'];
	$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');

	if (!empty($key) && !empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
		$aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
		$patientFname = $general->crypto('decrypt', $patientFname, $key);
		$patientMname = $general->crypto('decrypt', $patientMname, $key);
		$patientLname = $general->crypto('decrypt', $patientLname, $key);
	}
	$row[] = $aRow['patient_art_no'];
	$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
	$row[] = $aRow['storage_code'];
	$row[] = $aRow['rack'];
	$row[] = $aRow['box'];
	$row[] = $aRow['position'];
	$row[] = $aRow['volume'];
	$row[] = DateUtility::humanReadableDateFormat($aRow['date_out'] ?? '');
	$row[] = $aRow['comments'] ?? null;
	$row[] = ucfirst($aRow['sample_status']);

	$output[] = $row;
	unset($row);
	$no++;
}

$excel = new Spreadsheet();
$sheet = $excel->getActiveSheet();

$sheet->fromArray($headings, null, 'A1'); // Write headings
$sheet->fromArray($output, null, 'A2');  // Write data starting from row 2

$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
$filename = 'VLSM-SAMPLE-STORAGE-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo urlencode(basename($filename));
