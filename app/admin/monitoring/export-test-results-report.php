<?php


use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sQuery = $_SESSION['testResultReportsQuery'];
[$rResult, $resultCount] = $db->getDataAndCount($sQuery);


$headings = [
    _translate('Sample ID'),
    _translate('Remote Sample ID'),
    _translate('Sample Collection Date'),
    _translate('Sample Recieved On'),
    _translate('Sample Tested On'),
    _translate('Result'),
    _translate('Tested By'),
    _translate('Test Platform/Instrument'),
    _translate('Result Status'),
    _translate('Manual Result Entry'),
    _translate('Is Sample Rejected'),
    _translate('Rejection Reason'),
    _translate('Was Result Changed?'),
    _translate('Reason for Changing'),
    _translate('File Link')
];


$output = [];


$colNo = 1;
$colNum = 1;
$styleArray = [
    'font' => [
        'bold' => true,
        'size' => '13',
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'outline' => [
            'style' => Border::BORDER_THIN,
        ],
    ],
];
$nameValue = '';
foreach ($_POST as $key => $value) {
    if (trim((string) $value) != '' && trim((string) $value) != '-- Select --') {
        $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
    }
}

$excel = new Spreadsheet();
$sheet = $excel->getActiveSheet();

$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum) . '1', html_entity_decode($nameValue));



$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($nameValue));


foreach ($headings as $field => $value) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '5', html_entity_decode($value));
    $colNo++;
}

$sheet->getStyle('A2:A2')->applyFromArray($styleArray);
$sheet->getStyle('B2:B2')->applyFromArray($styleArray);
$sheet->getStyle('C2:C2')->applyFromArray($styleArray);
$sheet->getStyle('D2:D2')->applyFromArray($styleArray);
$sheet->getStyle('E2:E2')->applyFromArray($styleArray);

$sheet->getStyle('A5:A5')->applyFromArray($styleArray);
$sheet->getStyle('B5:B5')->applyFromArray($styleArray);
$sheet->getStyle('C5:C5')->applyFromArray($styleArray);
$sheet->getStyle('D5:D5')->applyFromArray($styleArray);
$sheet->getStyle('E5:E5')->applyFromArray($styleArray);
$sheet->getStyle('F5:F5')->applyFromArray($styleArray);
$sheet->getStyle('G5:G5')->applyFromArray($styleArray);
$sheet->getStyle('H5:H5')->applyFromArray($styleArray);
$sheet->getStyle('I5:I5')->applyFromArray($styleArray);
$sheet->getStyle('J5:J5')->applyFromArray($styleArray);
$sheet->getStyle('K5:K5')->applyFromArray($styleArray);
$sheet->getStyle('L5:L5')->applyFromArray($styleArray);
$sheet->getStyle('M5:M5')->applyFromArray($styleArray);
$sheet->getStyle('N5:N5')->applyFromArray($styleArray);
$sheet->getStyle('O5:O5')->applyFromArray($styleArray);


$no = 1;
foreach ($rResult as $aRow) {
    $rejectedObj = json_decode($aRow['reason_for_result_changes']);
    $row = [];
    $row[] = $aRow['sample_code'];
    $row[] = $aRow['remote_sample_code'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '', true);
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '', true);
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '', true);
    $row[] = $aRow['result'];
    $row[] = html_entity_decode($aRow['testedByName'] ?? '');
    $row[] = html_entity_decode($aRow['machine_name'] ?? '');
    $row[] = $aRow['status_name'];
    $row[] = $aRow['manual_result_entry'];
    $row[] = $aRow['is_sample_rejected'];
    $row[] = $aRow['rejection_reason_name'];
    $row[] = $aRow["result_modified"];
    $row[] = html_entity_decode($rejectedObj->reasonForChange ?? '');
    $row[] = $aRow['import_machine_file_name'];
    $output[] = $row;
    $no++;
}

foreach ($output as $rowNo => $rowData) {
    $rRowCount = $rowNo + 6;
    $sheet->fromArray($rowData, null, 'A' . $rRowCount);
}
$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
$filename = 'InteLIS-SAMPLEWISE-REPORT-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomNumber(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo urlencode(basename($filename));
