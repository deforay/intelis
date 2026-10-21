<?php

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['tbRejectedResult', 'tbRejectedResultCount'])) {
	return;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$key = (string) $general->getGlobalConfig('key');

$arr = $general->getGlobalConfig();

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['tbRejectedResult']) && trim((string) $_SESSION['tbRejectedResult']) !== "") {

     $output = [];
     $headings = ['Sample ID', 'Remote Sample ID', "Facility Name", "Patient ID.", "Patient's Name", "Sample Collection Date", "Lab Name", "Rejection Reason", "Recommended Corrective Action"];
     if ($general->isStandaloneInstance()) {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }

     $resultSet = $db->rawQuery($_SESSION['tbRejectedResult']);
     foreach ($resultSet as $aRow) {
          ExportJobUtility::tick();
          $row = [];
          //sample collecion date
          $sampleCollectionDate = '';
          if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_collection_date']);
               $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
          }

          $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';

          $patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow[$decrypt]));

          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
          }
          $row[] = ($aRow['facility_name']);
          $row[] = $aRow['patient_id'];
          $row[] = ($patientFname);
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['rejection_reason_name'];
          $row[] = $aRow['recommended_corrective_action_name'];
          $output[] = $row;
     }
     if (isset($_SESSION['tbRejectedResultCount']) && $_SESSION['tbRejectedResultCount'] > 50000) {

          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-TB-Rejected-Data-report-' . date('d-M-Y-H-i-s') . '.csv';
          $fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
          // we dont need the $output variable anymore
          unset($output);
          echo _downloadToken($fileName);
     } else {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-TB-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';

          $writer = new Writer();
          $writer->openToFile($fileName);
          $writer->addRow(Row::fromValues($headings));
          foreach ($output as $rowData) {
               $writer->addRow(Row::fromValues($rowData));
          }
          $writer->close();

          echo _downloadToken($fileName);
     }
}
