<?php



use App\Registries\ContainerRegistry;
use App\Utilities\SampleRejectionUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['rejectedSamples'])) {
	return;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$sarr = $general->getSystemConfig();

if (isset($_SESSION['rejectedSamples']) && trim((string) $_SESSION['rejectedSamples']) !== "") {
     $rResult = $db->rawQuery($_SESSION['rejectedSamples']);

     $output = [];
     $headings = ["Lab Name", "Facility Name", "Rejection Reason", "Reason Category", "Recommended Corrective Action", "No. of Samples"];

     $nameValue = '';
     foreach ($_POST as $key => $value) {
          if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
               $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
          }
     }

     foreach ($rResult as $aRow) {
          ExportJobUtility::tick();
          $row = [];
          $row[] = ($aRow['labname']);
          $row[] = ($aRow['facility_name']);
          $row[] = SampleRejectionUtility::reasonLabel($aRow['rejection_reason_name'] ?? null);
          $row[] = strtoupper((string) $aRow['rejection_type']);
          $row[] = ($aRow['recommended_corrective_action_name']);
          $row[] = $aRow['total'];
          $output[] = $row;
     }

     $filename = 'InteLIS-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';

     $writer = new Writer();
     $writer->openToFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
     $writer->addRow(Row::fromValues([html_entity_decode($nameValue)]));
     $writer->addRow(Row::fromValues(['']));
     $writer->addRow(Row::fromValues(array_map('html_entity_decode', $headings)));
     foreach ($output as $rowData) {
          $writer->addRow(Row::fromValues(array_map(static fn($value) => is_string($value) ? html_entity_decode($value) : $value, $rowData)));
     }
     $writer->close();

     echo _downloadToken($filename);
}
