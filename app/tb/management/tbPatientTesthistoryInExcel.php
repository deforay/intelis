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


if (isset($_SESSION['patientTestHistoryResult']) && trim((string) $_SESSION['patientTestHistoryResult']) != "") {

     $output = [];

     $headings = array('Patient ID', 'Patient Name', "Age", "DoB", "Facility Name", "Requesting Clinican", "Sample Collection Date", "Sample Type", "Lab Name", "Sample Tested Date", "Result");

     $resultSet = $db->rawQuery($_SESSION['patientTestHistoryResult']);
     foreach ($resultSet as $aRow) {
          $row = [];
          //sample collecion date
          $sampleCollectionDate = '';
          $sampleTestDate = '';
          if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_collection_date']);
               $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
          }
          if ($aRow['sample_tested_datetime'] != null && trim((string) $aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_tested_datetime']);
               $sampleTestDate =  date("d-m-Y", strtotime($expStr[0]));
          }
          $patientName = $aRow['patient_name'] ?? '';
          $patientSurname = $aRow['patient_surname'] ?? '';

          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
               $patientName = $general->crypto('decrypt', $patientName, $key);
               $patientSurname = $general->crypto('decrypt', $patientSurname, $key);
          }
          $row[] = $aRow['patient_id'];
          $row[] = ($patientName . " " . $patientSurname);
          $row[] = $aRow['patient_age'];
          $row[] = $aRow['patient_dob'];
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['clinician_name']);
          $row[] = $aRow['sample_collection_date'];
          $row[] = $aRow['sample_name'];
          $row[] = $aRow['labName'];
          $row[] = $sampleTestDate;
          $row[] = $aRow['lamResult'];
          $output[] = $row;
     }

     if (isset($_SESSION['patientTestHistoryResultCount']) && $_SESSION['patientTestHistoryResultCount'] > 50000) {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Patient-Test-History-report' . date('d-M-Y-H-i-s') . '.csv';
          $fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
          // we dont need the $output variable anymore
          unset($output);
          echo base64_encode((string) $fileName);
     } else {

          $excel = new Spreadsheet();
          $sheet = $excel->getActiveSheet();


          $styleArray = array(
               'font' => array(
                    'bold' => true,
                    'size' => '13',
               ),
               'alignment' => array(
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
               ),
               'borders' => array(
                    'outline' => array(
                         'style' => Border::BORDER_THIN,
                    ),
               )
          );


          $sheet->mergeCells('A1:AE1');
          $sheet->getStyle('A3:M3')->applyFromArray($styleArray);

          $sheet->fromArray($headings, null, 'A3');

          foreach ($output as $rowNo => $rowData) {
               $rRowCount = $rowNo + 4;
               $sheet->fromArray($rowData, null, 'A' . $rRowCount);
          }
          $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
          $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Patient-Test-History-report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($filename);
          echo urlencode(basename($filename));
     }
}
