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
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
     $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}

$arr = $general->getGlobalConfig();

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['rejectedViralLoadResult']) && trim((string) $_SESSION['rejectedViralLoadResult']) != "") {

     $output = [];

     $headings = array('Sample ID', 'Remote Sample ID', "Facility Name", "Patient's ID.", "Patient's Name", "Sample Collection Date", "Lab Name", "Rejection Reason", "Recommended Corrective Action");
     if ($_SESSION['instanceType'] == 'standalone') {
          if (($key = array_search("Remote Sample ID", $headings)) !== false) {
               unset($headings[$key]);
          }
     }


     foreach ($db->rawQueryGenerator($_SESSION['rejectedViralLoadResult']) as $aRow) {
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

          $patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow[$decrypt]));
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $key = (string) $general->getGlobalConfig('key');
               $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
          }
          $row[] = $aRow['sample_code'];
          if ($_SESSION['instanceType'] != 'standalone') {
               $row[] = $aRow['remote_sample_code'];
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

     if (isset($_SESSION['rejectedViralLoadResultCount']) && $_SESSION['rejectedViralLoadResultCount'] > 75000) {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-COVID19-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.csv';
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
          $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-COVID19-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($filename);
          echo base64_encode($filename);
     }
}
