<?php

use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sQuery = "SELECT vl.sample_collection_date,
				vl.sample_tested_datetime,
				vl.sample_received_at_lab_datetime,
				vl.result_printed_datetime,
				vl.result_mail_datetime,
				vl.request_created_by,
				vl.remote_sample_code,
				vl.sample_code
				FROM form_cd4 as vl
				INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
				LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
				LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
				WHERE (vl.sample_collection_date is NOT NULL)
				AND (vl.sample_tested_datetime IS NOT NULL)
				AND vl.cd4_result is not null
				AND vl.cd4_result != '' ";
if (!empty($_SESSION['cd4TatData']['sWhere'])) {
	$sQuery .= $_SESSION['cd4TatData']['sWhere'];
}

if (!empty($_SESSION['cd4TatData']['sOrder'])) {
	$sQuery = $sQuery . " ORDER BY " . $_SESSION['cd4TatData']['sOrder'];
}
$rResult = $db->rawQuery($sQuery);

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();

$headings = ["cd4 Sample ID", "Sample Collection Date", "Sample Received Date in Lab", "Sample Test Date", "Result Print Date", "Sample Email Date"];

$colNo = 1;

$styleArray = ['font' => ['bold' => true, 'size' => '13'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['outline' => ['style' => Border::BORDER_THICK]]];


$sheet->mergeCells('A1:AE1');
$nameValue = '';
foreach ($_POST as $key => $value) {
	if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
		$nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
	}
}
$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
	->setValueExplicit(html_entity_decode($nameValue));
foreach ($headings as $field => $value) {
	$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
		->setValueExplicit(html_entity_decode($value));
	$colNo++;
}
$sheet->getStyle('A3:F3')->applyFromArray($styleArray);

$no = 1;
foreach ($rResult as $aRow) {
	$row = [];
	//sample collecion date
	$sampleCollectionDate =  DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
	$sampleRecievedDate = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
	$testDate = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
	$printDate = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
	$mailDate = DateUtility::humanReadableDateFormat($aRow['result_mail_datetime'] ?? '');

	$row[] = $aRow['sample_code'];
	$row[] = $sampleCollectionDate;
	$row[] = $sampleRecievedDate;
	$row[] = $testDate;
	$row[] = $printDate;
	$row[] = $mailDate;
	$output[] = $row;
	$no++;
}

$start = (count($output)) + 2;
foreach ($output as $rowNo => $rowData) {
	$colNo = 1;
	$rRowCount = $rowNo + 4;
	foreach ($rowData as $field => $value) {
		$sheet->setCellValue(
			Coordinate::stringFromColumnIndex($colNo) . $rRowCount,
			html_entity_decode((string) $value)
		);
		$colNo++;
	}
}
$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
$filename = 'CD4-TAT-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo urlencode(basename($filename));
