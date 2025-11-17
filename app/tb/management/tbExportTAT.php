<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$rResult = $db->rawQuery($_SESSION['tbTatData']);

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();

$headings = ["TB Sample ID", "Sample Collection Date", "Sample Received Date in Lab", "Sample Test Date", "Result Print Date", "Sample Email Date"];

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
	$sampleCollectionDate = '';
	if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
		$expStr = explode(" ", (string) $aRow['sample_collection_date']);
		$sampleCollectionDate =  DateUtility::humanReadableDateFormat($expStr[0]);
	}
	if (isset($aRow['sample_received_at_lab_datetime']) && trim((string) $aRow['sample_received_at_lab_datetime']) !== '' && $aRow['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
		$sampleRecievedDate = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime']);
	} else {
		$sampleRecievedDate = '';
	}
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
$filename = 'TB-TAT-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo urlencode(basename($filename));
