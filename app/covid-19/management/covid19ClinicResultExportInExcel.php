<?php



use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['highViralResult', 'highViralResultCount'])) {
	return;
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sarr = $general->getSystemConfig();
$arr = $general->getGlobalConfig();

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';


if (isset($_SESSION['highViralResult']) && trim((string) $_SESSION['highViralResult']) !== "") {
     error_log($_SESSION['highViralResult']);
     // $rResult = $db->rawQuery($_SESSION['highViralResult']);

     $output = [];
     $headings = ['Sample ID', 'Remote Sample ID', "Facility Name", "Patient ART Number", "Patient's Name", "Patient Phone Number", "Sample Collection Date", "Sample Tested Date", "Lab Name", "VL Result in cp/mL"];
     if ($general->isStandaloneInstance()) {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }

     $filters = ['hvlSampleTestDate' => 'Sample Test Date', 'hvlBatchCode' => 'Batch Code', 'hvlSampleType' => 'Sample Type', 'hvlFacilityName' => 'Facility Name', 'hvlContactStatus' => 'Contact Status', 'hvlGender' => 'Sex'];

     foreach ($_POST as $key => $value) {
          if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --' && trim($key) !== 'markAsComplete') {
               $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
          }
     }
     $vlSampleId = [];
     $resultSet = $db->rawQuery($_SESSION['highViralResult']);
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
          $patientFname = $aRow['patient_first_name'] ?? '';
          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          $row[] = ($aRow['facility_name']);
          $row[] = $aRow['patient_id'];
          $row[] = ($patientFname);
          $row[] = $aRow['caretaker_phone_number'];
          $row[] = $sampleCollectionDate;
          $row[] = $sampleTestDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['result'];
          $vlSampleId[] = $aRow['covid19_id'];
          $output[] = $row;
     }
     if ($_POST['markAsComplete'] == 'true') {
          $vlId = implode(",", $vlSampleId);
     }

     if (isset($_SESSION['highViralResultCount']) && $_SESSION['highViralResultCount'] > 50000) {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Covid-19-Report' . date('d-M-Y-H-i-s') . '.csv';
          $fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
          // we dont need the $output variable anymore
          unset($output);
          echo _downloadToken($fileName);
     } else {
          $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Covid-19-Report' . date('d-M-Y-H-i-s') . '.xlsx';

          $writer = new Writer();
          $writer->openToFile($filename);
          $writer->addRow(Row::fromValues($headings));
          foreach ($output as $rowData) {
               $writer->addRow(Row::fromValues($rowData));
          }
          $writer->close();

          echo _downloadToken($filename);
     }
}
