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
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();

$headings = ["Lab Name", "Last Sync done on", "Latest Results Sync from Lab", "Latest Requests Sync from STS", "Version"];
$sheet->fromArray($headings, null, 'A3');

$no = 1;

$today = new DateTimeImmutable();
$twoWeekExpiry = $today->sub(DateInterval::createFromDateString('2 weeks'));
$threeWeekExpiry = $today->sub(DateInterval::createFromDateString('4 weeks'));

$resultSet = $db->rawQueryGenerator($_SESSION['labSyncStatus']);
foreach ($resultSet as $aRow) {
    $row = [];
    $color[]['color'] = $aRow['color'];

    $aRow['latest'] ??= $aRow['requested_on'];

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
$filename = 'VLSM-LAB-SYNC-STATUS-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomNumber(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo urlencode(basename($filename));
