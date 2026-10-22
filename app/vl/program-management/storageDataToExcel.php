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
if (ExportJobUtility::queueRequested(__FILE__, null, ['storageDataQuery'])) {
	return;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$globalConfig = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');


if (isset($_SESSION['storageDataQuery']) && trim((string) $_SESSION['storageDataQuery']) !== "") {

     $output = [];
     $headings = [_translate("Sample ID"), _translate("Storage Date"), _translate("Volume of Sample(ml)"), _translate("Rack"), _translate("Box"), _translate("Position"), _translate("Status")];

     $resultSet = $db->rawQuery($_SESSION['storageDataQuery']);
     foreach ($resultSet as $aRow) {
          ExportJobUtility::tick();
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

     $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Storage-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';

     $writer = new Writer();
     $writer->openToFile($filename);
     $writer->addRow(Row::fromValues($headings));
     foreach ($output as $rowData) {
          $writer->addRow(Row::fromValues($rowData));
     }
     $writer->close();

     echo _downloadToken($filename);
}
