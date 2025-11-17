<?php


use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$globalConfig = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');

$delimiter = $globalConfig['default_csv_delimiter'] ?? ',';
$enclosure = $globalConfig['default_csv_enclosure'] ?? '"';


if (isset($_SESSION['rejectedViralLoadResult']) && trim((string) $_SESSION['rejectedViralLoadResult']) !== "") {


     $output = [];
     $headings = ['Sample ID', 'Remote Sample ID', "Facility Name", "Patient ART Number", "Patient Name", "Sample Collection Date", "Lab Name", "Rejection Reason", "Recommended Corrective Action"];
     if ($general->isStandaloneInstance()) {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }
     if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
          $headings = MiscUtility::removeMatchingElements($headings, ['Patient Name']);
     }

     $resultSet = $db->rawQuery($_SESSION['rejectedViralLoadResult']);
     foreach ($resultSet as $aRow) {
          $row = [];
          //sample collecion date
          $sampleCollectionDate = '';
          if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_collection_date']);
               $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
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
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['rejection_reason_name'];
          $row[] = $aRow['recommended_corrective_action_name'];
          $output[] = $row;
     }


     if (isset($_SESSION['rejectedViralLoadResultCount']) && $_SESSION['rejectedViralLoadResultCount'] > 50000) {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.csv';
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
          $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($filename);
          echo urlencode(basename($filename));
     }
}
