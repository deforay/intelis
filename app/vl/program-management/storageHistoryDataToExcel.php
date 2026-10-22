<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['storageHistoryDataQuery'])) {
	return;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$globalConfig = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');


if (isset($_SESSION['storageHistoryDataQuery']) && trim((string) $_SESSION['storageHistoryDataQuery']) !== "") {

     $output = [];
     $headings = [_translate('Patient ID'), _translate('Sample Collection Date'), _translate("Freezer"), _translate("Volume of Sample(ml)"), _translate("Rack"), _translate("Box"), _translate("Position"), _translate("Date Out"), _translate("Comments"), _translate("Status"), _translate("Removal Reason")];

     $resultSet = $db->rawQuery($_SESSION['storageHistoryDataQuery']);
     foreach ($resultSet as $aRow) {
          ExportJobUtility::tick();
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

     $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Storage-History-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';

     $writer = new Writer();
     $writer->openToFile($filename);
     $writer->addRow(Row::fromValues($headings));
     foreach ($output as $rowData) {
          $writer->addRow(Row::fromValues($rowData));
     }
     $writer->close();

     echo _downloadToken($filename);
}
