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
$sarr = $general->getSystemConfig();

$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');

if (isset($_SESSION['highViralResult']) && trim((string) $_SESSION['highViralResult']) !== "") {

     $headings = ['Sample ID', 'Remote Sample ID', "Facility Name", "Patient ART Number", "Patient's Name", "Patient Phone Number", "Sample Collection Date", "Sample Tested Date", "Lab Name", "VL Result in cp/mL"];
     if ($general->isStandaloneInstance()) {
          $headings = ['Sample ID', "Facility Name", "Patient ART Number", "Patient's Name", "Patient Phone Number", "Sample Collection Date", "Sample Tested Date", "Lab Name", "VL Result in cp/mL"];
     }
     if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
          $headings = MiscUtility::removeMatchingElements($headings, ["Patient's Name"]);
     }

     $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-High-Viral-Load-Report' . date('d-M-Y-H-i-s') . '.xlsx';

     $writer = new Writer();
     $writer->openToFile($filename);
     $writer->addRow(Row::fromValues($headings));

     $vlSampleId = [];
     $resultSet = $db->rawQueryGenerator($_SESSION['highViralResult']);
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
          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
               $patientMname = $general->crypto('decrypt', $patientMname, $key);
               $patientLname = $general->crypto('decrypt', $patientLname, $key);
          }
          $row[] = ($aRow['facility_name']);
          $row[] = $aRow['patient_art_no'];
          if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
               $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
          }
          $row[] = $aRow['patient_mobile_number'];
          $row[] = $sampleCollectionDate;
          $row[] = $sampleTestDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['result'];
          $vlSampleId[] = $aRow['vl_sample_id'];

          $writer->addRow(Row::fromValues($row));
     }

     $writer->close();

     if ($_POST['markAsComplete'] == 'true') {
          $vlId = implode(",", $vlSampleId);
          if ($vlId !== '' && $vlId !== '0') {
              $db->rawQuery("UPDATE form_vl SET contact_complete_status = 'yes' WHERE vl_sample_id IN (" . $vlId . ")");
          }
     }

     echo urlencode(basename($filename));
}
