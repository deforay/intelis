<?php

use App\Models\General;
use App\Utilities\DateUtils;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

ini_set('memory_limit', -1);
$general = new General();
$dateTimeUtil = new DateUtils();

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();

$headings = array("Facility Name", "Test Type", "Province", "District", "Latest Results Sync from Lab", "Latest Requests Sync from VLSTS");
$colNo = 1;

$styleArray = array(
    'font' => array(
        'bold' => true,
        'size' => 12,
    ),
    'alignment' => array(
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ),
    'borders' => array(
        'outline' => array(
            'style' => Border::BORDER_THIN,
        ),
    )
);

$borderStyle = array(
    'alignment' => array(
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ),
    'borders' => array(
        'outline' => array(
            'style' => Border::BORDER_THIN,
        ),
    )
);

foreach ($headings as $field => $value) {
    $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode($value), DataType::TYPE_STRING);
    $sheet->getStyle($colNo . 1)->applyFromArray($borderStyle);
    // // $sheet->getDefaultRowDimension($colNo)->setRowHeight(18);
    // $sheet->getColumnDimensionByColumn($colNo)->setWidth(30);
    $colNo++;
}
$sheet->getStyle('A1:AH1')->applyFromArray($styleArray);


$rResult = $db->rawQuery($_SESSION['labSyncStatusDetails']);
$no = 1;

foreach ($rResult as $aRow) {
    $row = [];
    $row[] = ($aRow['facility_name']);
    $row[] = $aRow['testType'];
    $row[] = ($aRow['province']);
    $row[] = ($aRow['district']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['lastResultsSync']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['lastRequestsSync']);
    $output[] = $row;

    $no++;
}
$start = (count($output)) + 2;
$colorNo = 0;
foreach ($output as $rowNo => $rowData) {
    $colNo = 1;
    foreach ($rowData as $field => $value) {
        $rRowCount = ($rowNo+2);
        $sheet->getCellByColumnAndRow($colNo, $rRowCount)->setValueExplicit(html_entity_decode($value), DataType::TYPE_STRING);
        $cellName = $sheet->getCellByColumnAndRow($colNo, $rRowCount)->getColumn();
        $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
        // // $sheet->getDefaultRowDimension($colNo)->setRowHeight(18);
        // $sheet->getColumnDimensionByColumn($colNo)->setWidth(30);
        $colNo++;
    }
    $colorNo++;
}
// $sheet->getStyle('A3:AH3')->applyFromArray($styleArray);
$writer = IOFactory::createWriter($excel, 'Xlsx');
$filename = 'VLSM-LAB-SYNC-STATUS-DETAILS-' . date('d-M-Y-H-i-s') . '-' . General::generateRandomString(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
