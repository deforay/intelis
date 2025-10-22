<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

$headings = [
    "Facility Name",
    "Test Type",
    "Province",
    "District",
    "Latest Results Sync from LIS",
    "Latest Requests Sync from STS"
];
$colNo = 1;

foreach ($headings as $field => $value) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($value));
    $colNo++;
}


$rResult = $db->rawQuery($_SESSION['labSyncStatusDetails']);
$no = 1;

foreach ($rResult as $aRow) {
    $row = [];
    $row[] = $aRow['facility_name'];
    $row[] = $aRow['testType'];
    $row[] = $aRow['province'];
    $row[] = $aRow['district'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['lastResultsSync']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['lastRequestsSync']);
    $output[] = $row;

    $no++;
}
$start = (count($output)) + 2;
$colorNo = 0;
foreach ($output as $rowNo => $rowData) {
    $colNo = 1;
    foreach ($rowData as $field => $value) {
        $rRowCount = ($rowNo + 2);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode((string) $value));
        $colNo++;
    }
    $colorNo++;
}

$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
$filename = 'InteLIS-LAB-SYNC-STATUS-DETAILS-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomNumber(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo urlencode(basename($filename));
