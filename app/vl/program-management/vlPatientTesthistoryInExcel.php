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

if (isset($_SESSION['patientTestHistoryResult']) && trim((string) $_SESSION['patientTestHistoryResult']) !== "") {

     $headings = ['Patient ID', 'Patient Name', "Age", "DoB", "Facility Name", "Requesting Clinican", "Sample Collection Date", "Sample Type", "Lab Name", "Sample Tested Date", "Result"];

     $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Patient-Test-History-report' . date('d-M-Y-H-i-s') . '.xlsx';

     $writer = new Writer();
     $writer->openToFile($filename);
     $writer->addRow(Row::fromValues($headings));

     $resultSet = $db->rawQueryGenerator($_SESSION['patientTestHistoryResult']);
     foreach ($resultSet as $aRow) {
          $row = [];
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

          $patientFname = $aRow['patient_first_name'] != '' ? $aRow['patient_first_name'] : '';
          $patientMname = $aRow['patient_middle_name'] != '' ? $aRow['patient_middle_name'] : '';
          $patientLname = $aRow['patient_last_name'] != '' ? $aRow['patient_last_name'] : '';

          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
               $patientMname = $general->crypto('decrypt', $patientMname, $key);
               $patientLname = $general->crypto('decrypt', $patientLname, $key);
          }
          $row[] = $aRow['patient_art_no'];
          $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
          $row[] = $aRow['patient_age_in_years'];
          $row[] = $aRow['patient_dob'];
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['request_clinician_name']);
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['sample_name'];
          $row[] = $aRow['labName'];
          $row[] = $sampleTestDate;
          $row[] = $aRow['result'];

          $writer->addRow(Row::fromValues($row));
     }

     $writer->close();
     echo urlencode(basename($filename));
}
