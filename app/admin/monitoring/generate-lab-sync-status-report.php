<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();

$headings = array("Lab Name", "Last Sync done on", "Latest Results Sync from Lab", "Latest Requests Sync from STS", "Version");
$sheet->fromArray($headings, null, 'A3');

$no = 1;

$today = new DateTimeImmutable();
$twoWeekExpiry = $today->sub(DateInterval::createFromDateString('2 weeks'));
$threeWeekExpiry = $today->sub(DateInterval::createFromDateString('4 weeks'));

$resultSet = $db->rawQueryGenerator($_SESSION['labSyncStatus']);
foreach ($resultSet as $aRow) {
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

    unset($row);

    $no++;
}

foreach ($output as $rowNo => $rowData) {
    $rRowCount = $rowNo + 2;
    $sheet->fromArray($rowData, null, 'A' . $rRowCount);
}
$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
$filename = 'VLSM-LAB-SYNC-STATUS-' . date('d-M-Y-H-i-s') . '-' . $general->generateRandomString(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
