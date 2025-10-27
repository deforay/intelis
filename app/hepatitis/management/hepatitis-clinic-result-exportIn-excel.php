<?php


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

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

$key = (string) $general->getGlobalConfig('key');

if (isset($_SESSION['highViralResult']) && trim((string) $_SESSION['highViralResult']) != "") {
     error_log($_SESSION['highViralResult']);

     $output = [];

     $headings = array('Sample ID', 'Remote Sample ID', "Facility Name", "Patient's Name", "Patient ART Number", "Patient Phone Number", "Sample Collection Date", "Sample Tested Date", "Lab Name", "VL Result in cp/mL");
     if ($general->isStandaloneInstance()) {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }

     $vlSampleId = [];
     $resultSet = $db->rawQueryGenerator($_SESSION['highViralResult']);
     foreach ($resultSet as $aRow) {
          $row = [];
          //sample collecion date
          $sampleCollectionDate = '';
          $sampleTestDate = '';
          if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_collection_date']);
               $sampleCollectionDate = date("d-m-Y", strtotime($expStr[0]));
          }
          if ($aRow['sample_tested_datetime'] != null && trim((string) $aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_tested_datetime']);
               $sampleTestDate = date("d-m-Y", strtotime($expStr[0]));
          }

          if ($aRow['remote_sample'] == 'yes') {
               $decrypt = 'remote_sample_code';
          } else {
               $decrypt = 'sample_code';
          }
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
          $row[] = ($patientFname);
          $row[] = $aRow['patient_id'];
          $row[] = $aRow['patient_phone_number'];
          $row[] = $sampleCollectionDate;
          $row[] = $sampleTestDate;
          $row[] = $aRow['labName'];
          $row[] = ($aRow['hcv_vl_count']);
          $row[] = ($aRow['hbv_vl_count']);
          $vlSampleId[] = $aRow['hepatitis_id'];
          $output[] = $row;
     }
     if ($_POST['markAsComplete'] == 'true') {
          $vlId = implode(",", $vlSampleId);
     }
     if (isset($_SESSION['highViralResultCount']) && $_SESSION['highViralResultCount'] > 50000) {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-High-Viral-Load-Report' . date('d-M-Y-H-i-s') . '.csv';
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
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-High-Viral-Load-Report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($fileName);
          echo urlencode(basename($fileName));
     }
}
