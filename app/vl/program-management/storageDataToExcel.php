<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$globalConfig = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');


if (isset($_SESSION['storageDataQuery']) && trim((string) $_SESSION['storageDataQuery']) != "") {

     $output = [];
     $headings = [_translate("Sample ID"), _translate("Storage Date"), _translate("Volume of Sample(ml)"), _translate("Rack"), _translate("Box"), _translate("Position"), _translate("Status")];

     $resultSet = $db->rawQuery($_SESSION['storageDataQuery']);
     foreach ($resultSet as $aRow) {
          $row = [];

          $row[] = $aRow['sample_code'];
          $row[] = DateUtility::humanReadableDateFormat($aRow['updated_datetime'] ?? '', true);
          $row[] = ($aRow['volume']);
          $row[] = $aRow['rack'];
          $row[] = $aRow['box'];
          $row[] = $aRow['position'];
          $row[] = $aRow['sample_status'];
          $output[] = $row;
     }

     $excel = new Spreadsheet();
     $sheet = $excel->getActiveSheet();

     $sheet->fromArray($headings, null, 'A3');

     foreach ($output as $rowNo => $rowData) {
          $rRowCount = $rowNo + 4;
          $sheet->fromArray($rowData, null, 'A' . $rRowCount);
     }
     $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
     $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Storage-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
     $writer->save($filename);
     echo urlencode(basename($filename));
}
