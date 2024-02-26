<?php
if (session_status() == PHP_SESSION_NONE) {
     session_start();
}

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$sarr = $general->getSystemConfig();

$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';


if (isset($_SESSION['vlIncompleteForm']) && trim((string) $_SESSION['vlIncompleteForm']) != "") {
     // error_log($_SESSION['vlIncompleteForm']);

     $output = [];

     $headings = array('Sample ID', 'Remote Sample ID', "Sample Collection Date", "Batch Code", "Patient Id.", "Patient's Name", "Facility Name", "Province/State", "District/County", "Sample Type", 'CD4 Result', "Status");
     if ($_SESSION['instance']['type'] == 'standalone') {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }
     $resultSet = $db->rawQuery($_SESSION['vlIncompleteForm']);
     foreach ($resultSet as $aRow) {
          $row = [];
          //sample collecion date
          $sampleCollectionDate = '';
          if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_collection_date']);
               $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
          }

          if ($aRow['remote_sample'] == 'yes') {
               $decrypt = 'remote_sample_code';
          } else {
               $decrypt = 'sample_code';
          }

          $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow[$decrypt]));

          $row[] = $aRow['sample_code'];
          if ($sarr['sc_user_type'] != 'standalone') {
               $row[] = $aRow['remote_sample_code'];
          }
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
          }
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['batch_code'];
          $row[] = $aRow['patient_art_no'];
          $row[] = ($patientFname);
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['facility_state']);
          $row[] = ($aRow['facility_district']);
          $row[] = ($aRow['sample_name']);
          $row[] = ($aRow['cd4_result']);
          $row[] = ($aRow['status_name']);
          $output[] = $row;
     }

     if (isset($_SESSION['vlIncompleteFormCount']) && $_SESSION['vlIncompleteFormCount'] > 50000) {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-CD4-Data-Quality-report' . date('d-M-Y-H-i-s') . '.csv';
          $fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
          // we dont need the $output variable anymore
          unset($output);
          echo base64_encode((string) $fileName);
     } else {
          $excel = new Spreadsheet();
          $sheet = $excel->getActiveSheet();

          $sheet->fromArray($headings, null, 'A3');

          foreach ($output as $rowNo => $rowData) {
               $rRowCount = $rowNo + 4;
               $sheet->fromArray($rowData, null, 'A' . $rRowCount);
          }
          $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-CD4-Data-Quality-report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($fileName);
          echo base64_encode($fileName);
     }
}
