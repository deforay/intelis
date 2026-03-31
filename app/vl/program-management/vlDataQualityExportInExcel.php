<?php

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

ini_set('memory_limit', '512M');
set_time_limit(300);
ini_set('max_execution_time', 300);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$key = (string) $general->getGlobalConfig('key');

if (isset($_SESSION['vlIncompleteForm']) && trim((string) $_SESSION['vlIncompleteForm']) !== "") {

     $headings = ['Sample ID', 'Remote Sample ID', "Sample Collection Date", "Batch Code", "Unique ART No.", "Patient's Name", "Facility Name", "Province/State", "District/County", "Sample Type", "Result", "Status"];
     if ($general->isStandaloneInstance()) {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }
     if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
          $headings = MiscUtility::removeMatchingElements($headings, ["Patient's Name"]);
     }

     $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Data-Quality-report' . date('d-M-Y-H-i-s') . '.xlsx';

     $writer = new Writer();
     $writer->openToFile($filename);
     $writer->addRow(Row::fromValues($headings));

     $resultSet = $db->rawQueryGenerator($_SESSION['vlIncompleteForm']);
     foreach ($resultSet as $aRow) {
          $row = [];
          $sampleCollectionDate = '';
          if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_collection_date']);
               $sampleCollectionDate = date("d-m-Y", strtotime($expStr[0]));
          }

          $patientFname = $aRow['patient_first_name'] != '' ? $aRow['patient_first_name'] : '';
          $patientMname = $aRow['patient_middle_name'] != '' ? $aRow['patient_middle_name'] : '';
          $patientLname = $aRow['patient_last_name'] != '' ? $aRow['patient_last_name'] : '';

          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['batch_code'];
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
               $patientMname = $general->crypto('decrypt', $patientMname, $key);
               $patientLname = $general->crypto('decrypt', $patientLname, $key);
          }
          $row[] = $aRow['patient_art_no'];
          if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
               $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
          }
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['facility_state']);
          $row[] = ($aRow['facility_district']);
          $row[] = ($aRow['sample_name']);
          $row[] = $aRow['result'];
          $row[] = ($aRow['status_name']);

          $writer->addRow(Row::fromValues($row));
     }

     $writer->close();
     echo urlencode(basename($filename));
}
