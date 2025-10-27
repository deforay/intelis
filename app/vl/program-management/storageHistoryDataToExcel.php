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


if (isset($_SESSION['storageHistoryDataQuery']) && trim((string) $_SESSION['storageHistoryDataQuery']) != "") {

     $output = [];
     $headings = [_translate('Patient ID'), _translate('Sample Collection Date'), _translate("Freezer"), _translate("Volume of Sample(ml)"), _translate("Rack"), _translate("Box"), _translate("Position"), _translate("Date Out"), _translate("Comments"), _translate("Status"), _translate("Removal Reason")];

     $resultSet = $db->rawQuery($_SESSION['storageHistoryDataQuery']);
     foreach ($resultSet as $aRow) {
          $row = [];
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
          }

          $row[] = $aRow['patient_art_no'];
          $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
          $row[] = ($aRow['storage_code']);
          $row[] = ($aRow['volume']);
          $row[] = $aRow['rack'];
          $row[] = $aRow['box'];
          $row[] = $aRow['position'];
          $row[] = DateUtility::humanReadableDateFormat($aRow['date_out']);
          $row[] = $aRow['comments'];
          $row[] = $aRow['sample_status'];
          $row[] = $aRow['removal_reason_name'];
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
     $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Storage-History-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
     $writer->save($filename);
     echo urlencode(basename($filename));
}
