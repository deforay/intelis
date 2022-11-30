<?php

use Vlsm\Utilities\DateUtils;

ini_set('memory_limit', -1);
$general = new \Vlsm\Models\General();
$dateTimeUtil = new DateUtils();

$excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$output = array();
$sheet = $excel->getActiveSheet();

$headings = array("Lab Name", "Test Type", "Last Sync done on");
$colNo = 1;

$styleArray = array(
    'font' => array(
        'bold' => true,
        'size' => 12,
    ),
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    ),
    'borders' => array(
        'outline' => array(
            'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ),
    )
);

$borderStyle = array(
    'alignment' => array(
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ),
    'borders' => array(
        'outline' => array(
            'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ),
    )
);

$sheet->mergeCells('A1:AH1');
$sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode("Lab Sync Status Report"), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
if (isset($_POST['withAlphaNum']) && $_POST['withAlphaNum'] == 'yes') {
    foreach ($headings as $field => $value) {
        $string = str_replace(' ', '', $value);
        $value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
        $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getStyle($colNo . 3)->applyFromArray($borderStyle);
        $sheet->getDefaultRowDimension($colNo)->setRowHeight(18);
        $sheet->getColumnDimensionByColumn($colNo)->setWidth(30);
        $colNo++;
    }
} else {
    foreach ($headings as $field => $value) {
        $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getStyle($colNo . 3)->applyFromArray($borderStyle);
        $sheet->getDefaultRowDimension($colNo)->setRowHeight(18);
        $sheet->getColumnDimensionByColumn($colNo)->setWidth(30);
        $colNo++;
    }
}
$sheet->getStyle('A3:AH3')->applyFromArray($styleArray);


$rResult = $db->rawQuery($_SESSION['labSyncStatus']);
$no = 1;

$twoWeekExpiry = date("Y-m-d", strtotime(date("Y-m-d") . '-2 weeks'));
$threeWeekExpiry = date("Y-m-d", strtotime(date("Y-m-d") . '-3 weeks'));
foreach ($rResult as $aRow) {
    $row = array();
    $_color = "f08080";
    if ($twoWeekExpiry <= $aRow['requested_on']) {
        $_color = "90ee90";
    } elseif ($twoWeekExpiry >= $aRow['requested_on'] && $threeWeekExpiry < $aRow['requested_on']) {
        $_color = "ffff00";
    } elseif ($threeWeekExpiry >= $aRow['requested_on'] || (!isset($aRow['test_type']) || !empty($aRow['test_type']))) {
        $_color = "f08080";
    }
    $color[]['color'] = $_color;

    $row[] = ucwords($aRow['facility_name']);
    $row[] = ucwords($aRow['test_type']);
    $row[] = $general->humanReadableDateFormat($aRow['requested_on']);
    $output[] = $row;
    
    $no++;
}
// echo "<pre>";
// print_r($color);
$start = (count($output)) + 2;
$colorNo = 0;
foreach ($output as $rowNo => $rowData) {
    $colNo = 1;
    foreach ($rowData as $field => $value) {
        $rRowCount = ($rowNo + 4);
        $sheet->getCellByColumnAndRow($colNo, $rRowCount)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        // echo "Col : ".$colNo ." => Row : " . $rRowCount . " => Color : " .$color[$colorNo]['color'];
        // echo "<br>";
        $cellName = $sheet->getCellByColumnAndRow($colNo, $rRowCount)->getColumn();
        $sheet->getStyle($cellName . $rRowCount)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($color[$colorNo]['color']);
        $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
        $sheet->getDefaultRowDimension($colNo)->setRowHeight(18);
        $sheet->getColumnDimensionByColumn($colNo)->setWidth(30);
        $colNo++;
    }
    $colorNo++;
}
$sheet->getStyle('A3:AH3')->applyFromArray($styleArray);
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
$filename = 'VLSM-LAB-SYNC-STATUS-' . date('d-M-Y-H-i-s') . '-' . $general->generateRandomString(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
