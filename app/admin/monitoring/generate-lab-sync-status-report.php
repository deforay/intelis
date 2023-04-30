<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

ini_set('memory_limit', -1);

/** @var MysqliDb $db */
$db = \App\Registries\ContainerRegistry::get('db');

/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$dateTimeUtil = new DateUtility();

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();

$headings = array("Lab Name", "Last Sync done on", "Latest Results Sync from Lab", "Latest Requests Sync from VLSTS", "Version");
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

// $sheet->mergeCells('A1:AH1');
// $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode("Lab Sync Status Report"), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
foreach ($headings as $field => $value) {
    $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode($value));
    $sheet->getStyle($colNo . 1)->applyFromArray($borderStyle);
    // // $sheet->getDefaultRowDimension($colNo)->setRowHeight(18);
    // $sheet->getColumnDimensionByColumn($colNo)->setWidth(30);
    $colNo++;
}
$sheet->getStyle('A1:AH1')->applyFromArray($styleArray);


$rResult = $db->rawQuery($_SESSION['labSyncStatus']);
$no = 1;

$today = new DateTimeImmutable();
$twoWeekExpiry = $today->sub(DateInterval::createFromDateString('2 weeks'));
//$twoWeekExpiry = date("Y-m-d", strtotime(date("Y-m-d") . '-2 weeks'));
$threeWeekExpiry = $today->sub(DateInterval::createFromDateString('4 weeks'));

foreach ($rResult as $aRow) {
    $row = [];
    $_color = "f08080";

    $aRow['latest'] = $aRow['latest'] ?? $aRow['requested_on'];
    $latest = new DateTimeImmutable($aRow['latest']);

    $latest = (!empty($aRow['latest'])) ? new DateTimeImmutable($aRow['latest']) : null;
    // $twoWeekExpiry = new DateTimeImmutable($twoWeekExpiry);
    // $threeWeekExpiry = new DateTimeImmutable($threeWeekExpiry);

    if (empty($latest)) {
        $_color = "f08080";
    } elseif ($latest >= $twoWeekExpiry) {
        $_color = "90ee90";
    } elseif ($latest > $threeWeekExpiry && $latest < $twoWeekExpiry) {
        $_color = "ffff00";
    } elseif ($latest >= $threeWeekExpiry) {
        $_color = "f08080";
    }
    $color[]['color'] = $_color;

    $row[] = ($aRow['facility_name']);
    //$row[] = ($aRow['test_type']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['latest']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['lastResultsSync']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['lastRequestsSync']);
    $row[] = (isset($aRow['version']) && !empty($aRow['version']) && $aRow['version'] != "" && $aRow['version'] != null)?$aRow['version']:" - ";
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
        $rRowCount = ($rowNo+2);
        $sheet->getCellByColumnAndRow($colNo, $rRowCount)->setValueExplicit(html_entity_decode($value));
        // echo "Col : ".$colNo ." => Row : " . $rRowCount . " => Color : " .$color[$colorNo]['color'];
        // echo "<br>";
        $cellName = $sheet->getCellByColumnAndRow($colNo, $rRowCount)->getColumn();
        $sheet->getStyle($cellName . $rRowCount)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color[$colorNo]['color']);
        $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
        // // $sheet->getDefaultRowDimension($colNo)->setRowHeight(18);
        // $sheet->getColumnDimensionByColumn($colNo)->setWidth(30);
        $colNo++;
    }
    $colorNo++;
}
// $sheet->getStyle('A3:AH3')->applyFromArray($styleArray);
$writer = IOFactory::createWriter($excel, 'Xlsx');
$filename = 'VLSM-LAB-SYNC-STATUS-' . date('d-M-Y-H-i-s') . '-' . CommonService::generateRandomString(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
