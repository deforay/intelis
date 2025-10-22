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

$sQuery = $_SESSION['samplewiseReportsQuery'];
$rResult = $db->rawQuery($sQuery);

$calcQuery = $_SESSION['samplewiseReportsCalc'];
$calcResult = $db->rawQuery($calcQuery);

$totalCalculationHeadings = ['No. of Samples Requested', 'No. of Samples Acknowledged', 'No. of Samples Received at Testing Lab', 'No. of Samples Tested', 'No. of Results Returned'];
$headings = ['LIS Sample ID', 'Name of the Clinic', 'External ID', "Electronic Test request Date and Time", "STS Sample ID", "Request Acknowledged Date Time", "Samples Received At Lab", "Date Time of Sample added to Batch", "Test Result", "Result Received/Entered Date and Time", "Result Approved Date and Time", "Result Return Date and Time", "Last Modified On"];

$outputCalc = [];
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


foreach ($totalCalculationHeadings as $field => $value) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum) . '2', html_entity_decode($value));
    $colNum++;
}

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


foreach ($calcResult as $cRow) {
    $rowC = [];
    $rowC[] = $cRow['totalSamplesRequested'];
    $rowC[] = $cRow['totalSamplesAcknowledged'];
    $rowC[] = $cRow['totalSamplesReceived'];
    $rowC[] = $cRow['totalSamplesTested'];
    $rowC[] = $cRow['totalSamplesDispatched'];

    $outputCalc[] = $rowC;
}

$no = 1;
foreach ($rResult as $aRow) {
    $row = [];
    $row[] = $aRow['sample_code'];
    $row[] = $aRow['labname'];
    $row[] = $aRow['external_sample_code'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
    $row[] = $aRow['remote_sample_code'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
    $row[] = DateUtility::humanReadableDateFormat($aRow['batch_request_created']);
    $row[] = $aRow['result'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['result_reviewed_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['result_approved_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['result_sent_to_source_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime']);

    $output[] = $row;
    $no++;
}

foreach ($outputCalc as $rNo => $rData) {
    $colNum = 1;
    $rCount = $rNo + 3;
    foreach ($rData as $field => $value) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum) . $rCount, html_entity_decode((string) $value));
        $colNum++;
    }
}

foreach ($output as $rowNo => $rowData) {
    $colNo = 1;
    $rRowCount = $rowNo + 6;
    foreach ($rowData as $field => $value) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode((string) $value));
        $colNo++;
    }
}
$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
$filename = 'InteLIS-SAMPLEWISE-REPORT-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomNumber(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo urlencode(basename($filename));
