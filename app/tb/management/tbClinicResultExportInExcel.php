<?php



use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['highTbResult', 'highTbResultCount'])) {
	return;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$sarr = $general->getSystemConfig();

$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['highTbResult']) && trim((string) $_SESSION['highTbResult']) !== "") {


     $output = [];
     $headings = ['Sample ID', 'Remote Sample ID', "Facility Name", "Patient ID", "Patient's Name", "Sample Collection Date", "Sample Tested Date", "Lab Name", "TB Result"];
     if ($general->isStandaloneInstance()) {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }

     $tbSampleIds = [];
     $resultSet = $db->rawQueryGenerator($_SESSION['highTbResult']);
     foreach ($resultSet as $aRow) {
          ExportJobUtility::tick();
          $row = [];
          //sample collecion date
          $sampleCollectionDate = '';
          $sampleTestDate = '';
          if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_collection_date']);
               $sampleCollectionDate = date("d-m-Y", strtotime($expStr[0]));
          }
          if ($aRow['sample_tested_datetime'] != null && trim((string) $aRow['sample_tested_datetime']) !== '' && $aRow['sample_tested_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_tested_datetime']);
               $sampleTestDate = date("d-m-Y", strtotime($expStr[0]));
          }

          $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';
          $patientFname = $general->crypto('doNothing', $aRow['patient_name'], $aRow[$decrypt]);
          $patientMname = $general->crypto('doNothing', $aRow['patient_surname'], $aRow[$decrypt]);
          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
               $patientMname = $general->crypto('decrypt', $patientMname, $key);
          }
          $row[] = ($aRow['facility_name']);
          $row[] = $aRow['patient_id'];
          $row[] = ($patientFname . " " . $patientMname);
          $row[] = $sampleCollectionDate;
          $row[] = $sampleTestDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['result'];
          $tbSampleIds[] = $aRow['tb_id'];
          $output[] = $row;
     }
     if ($_POST['markAsComplete'] == 'true') {
          $tbId = implode(",", $tbSampleIds);
     }
     if (isset($_SESSION['highTbResultCount']) && $_SESSION['highTbResultCount'] > 50000) {

          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-High-TB-Report-' . date('d-M-Y-H-i-s') . '.csv';
          $fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
          // we dont need the $output variable anymore
          unset($output);
          echo _downloadToken($fileName);
     } else {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-High-TB-Report' . date('d-M-Y-H-i-s') . '.xlsx';

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
