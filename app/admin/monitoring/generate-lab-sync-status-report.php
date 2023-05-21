<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

ini_set('memory_limit', -1);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$dateTimeUtil = new DateUtility();

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();

$headings = array("Lab Name", "Last Sync done on", "Latest Results Sync from Lab", "Latest Requests Sync from VLSTS", "Version");
$colNo = 1;


foreach ($headings as $field => $value) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($value));
    $colNo++;
}


$rResult = $db->rawQuery($_SESSION['labSyncStatus']);
$no = 1;

$today = new DateTimeImmutable();
$twoWeekExpiry = $today->sub(DateInterval::createFromDateString('2 weeks'));
$threeWeekExpiry = $today->sub(DateInterval::createFromDateString('4 weeks'));

foreach ($rResult as $aRow) {
    $row = [];
    $_color = "f08080";

    $aRow['latest'] = $aRow['latest'] ?? $aRow['requested_on'];
    $latest = new DateTimeImmutable($aRow['latest']);

    $latest = (!empty($aRow['latest'])) ? new DateTimeImmutable($aRow['latest']) : null;

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

    $row[] = $aRow['facility_name'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['latest']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['lastResultsSync']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['lastRequestsSync']);
    $row[] = $aRow['version'] ?? " - ";
    $output[] = $row;

    $no++;
}
$start = (count($output)) + 2;
$colorNo = 0;
foreach ($output as $rowNo => $rowData) {
    $colNo = 1;
    foreach ($rowData as $field => $value) {
        $rRowCount = ($rowNo + 2);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode($value));
        $colNo++;
    }
    $colorNo++;
}
$writer = IOFactory::createWriter($excel, 'Xlsx');
$filename = 'VLSM-LAB-SYNC-STATUS-' . date('d-M-Y-H-i-s') . '-' . $general->generateRandomString(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
