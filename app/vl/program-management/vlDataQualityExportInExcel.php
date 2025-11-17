<?php

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$sarr = $general->getSystemConfig();

$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';


if (isset($_SESSION['vlIncompleteForm']) && trim((string) $_SESSION['vlIncompleteForm']) !== "") {

     $output = [];

     $headings = ['Sample ID', 'Remote Sample ID', "Sample Collection Date", "Batch Code", "Unique ART No.", "Patient's Name", "Facility Name", "Province/State", "District/County", "Sample Type", "Result", "Status"];
     if ($general->isStandaloneInstance()) {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }
     if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
          $headings = MiscUtility::removeMatchingElements($headings, ["Patient's Name"]);
     }

     $resultSet = $db->rawQuery($_SESSION['vlIncompleteForm']);
     foreach ($resultSet as $aRow) {
          $row = [];
          //sample collecion date
          $sampleCollectionDate = '';
          if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_collection_date']);
               $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
          }
          // if($aRow['remote_sample']=='yes'){
          //   $sampleId = $aRow['remote_sample_code'];
          // }else{
          //   $sampleId = $aRow['sample_code'];
          // }

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
          $output[] = $row;
     }

     if (isset($_SESSION['vlIncompleteFormCount']) && $_SESSION['vlIncompleteFormCount'] > 50000) {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Data-Quality-report' . date('d-M-Y-H-i-s') . '.csv';
          $fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
          // we dont need the $output variable anymore
          unset($output);
          echo base64_encode((string) $fileName);
     } else {

          $excel = new Spreadsheet();
          $sheet = $excel->getActiveSheet();


          $styleArray = ['font' => ['bold' => true, 'size' => '13'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['outline' => ['style' => Border::BORDER_THIN]]];


          $sheet->mergeCells('A1:AE1');
          $sheet->getStyle('A3:M3')->applyFromArray($styleArray);

          $sheet->fromArray($headings, null, 'A3');

          foreach ($output as $rowNo => $rowData) {
               $rRowCount = $rowNo + 4;
               $sheet->fromArray($rowData, null, 'A' . $rRowCount);
          }
          $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
          $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Data-Quality-report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($filename);
          echo urlencode(basename($filename));
     }
}
