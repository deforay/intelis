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


if (isset($_SESSION['highViralResult']) && trim((string) $_SESSION['highViralResult']) != "") {
     $rResult = $db->rawQuery($_SESSION['highViralResult']);

     $output = [];
     $headings = array('Sample ID', 'Remote Sample ID', "Facility Name", "Patient ART Number", "Patient's Name", "Patient Phone Number", "Sample Collection Date", "Sample Tested Date", "Lab Name", "VL Result in cp/mL");
     if ($general->isStandaloneInstance()) {
          $headings = array('Sample ID', "Facility Name", "Patient ART Number", "Patient's Name", "Patient Phone Number", "Sample Collection Date", "Sample Tested Date", "Lab Name", "VL Result in cp/mL");
     }
     if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
          $headings = MiscUtility::removeMatchingElements($headings, ["Patient's Name"]);
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
          $output[] = $row;
     }
     if ($_POST['markAsComplete'] == 'true') {
          $vlId = implode(",", $vlSampleId);
          if (!empty($vlId))
               $db->rawQuery("UPDATE form_vl SET contact_complete_status = 'yes' WHERE vl_sample_id IN (" . $vlId . ")");
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

          $borderStyle = array(
               'alignment' => array(
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
               ),
               'borders' => array(
                    'outline' => array(
                         'style' => Border::BORDER_THIN,
                    ),
               )
          );

          $sheet->mergeCells('A1:AE1');

          $sheet->getStyle('A3:A3')->applyFromArray($styleArray);
          $sheet->getStyle('B3:B3')->applyFromArray($styleArray);
          $sheet->getStyle('C3:C3')->applyFromArray($styleArray);
          $sheet->getStyle('D3:D3')->applyFromArray($styleArray);
          $sheet->getStyle('E3:E3')->applyFromArray($styleArray);
          $sheet->getStyle('F3:F3')->applyFromArray($styleArray);
          $sheet->getStyle('G3:G3')->applyFromArray($styleArray);
          $sheet->getStyle('H3:H3')->applyFromArray($styleArray);
          $sheet->getStyle('I3:I3')->applyFromArray($styleArray);
          if (!$general->isStandaloneInstance()) {
               $sheet->getStyle('J3:J3')->applyFromArray($styleArray);
          }

          $sheet->fromArray($headings, null, 'A3');

          foreach ($output as $rowNo => $rowData) {
               $rRowCount = $rowNo + 4;
               $sheet->fromArray($rowData, null, 'A' . $rRowCount);
          }
          $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
          $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-High-Viral-Load-Report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($filename);
          echo urlencode(basename($filename));
     }
}
