<?php


use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$sarr = $general->getSystemConfig();

$arr = $general->getGlobalConfig();

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';


if (isset($_SESSION['rejectedViralLoadResult']) && trim((string) $_SESSION['rejectedViralLoadResult']) != "") {


     $output = [];
     $headings = array('Sample ID', 'Remote Sample ID', "Facility Name", "Patient ART no.", "Patient Name", "Sample Collection Date", "Lab Name", "Rejection Reason", "Recommended Corrective Action");
     if ($sarr['sc_user_type'] == 'standalone') {
          if (($key = array_search("Remote Sample ID", $headings)) !== false) {
               unset($headings[$key]);
          }
     }
     if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
          if (($key = array_search("Patient Name", $headings)) !== false) {
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

          if ($aRow['patient_first_name'] != '') {
               $patientFname = $aRow['patient_first_name'];
          } else {
               $patientFname = '';
          }
          if ($aRow['patient_middle_name'] != '') {
               $patientMname = $aRow['patient_middle_name'];
          } else {
               $patientMname = '';
          }
          if ($aRow['patient_last_name'] != '') {
               $patientLname = $aRow['patient_last_name'];
          } else {
               $patientLname = '';
          }
          $row[] = $aRow['sample_code'];
          if ($sarr['sc_user_type'] != 'standalone') {
               $row[] = $aRow['remote_sample_code'];
          }
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $key = base64_decode((string) $general->getGlobalConfig('key'));
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
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['rejection_reason_name'];
          $row[] = $aRow['recommended_corrective_action_name'];
          $output[] = $row;
     }


     if (isset($_SESSION['rejectedViralLoadResultCount']) && $_SESSION['rejectedViralLoadResultCount'] > 75000) {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.csv';
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
          $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($filename);
          echo base64_encode($filename);
     }
}
