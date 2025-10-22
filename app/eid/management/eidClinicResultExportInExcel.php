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

$arr = $general->getGlobalConfig();

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';


if (isset($_SESSION['highViralResult']) && trim((string) $_SESSION['highViralResult']) != "") {

     $output = [];
     $headings = array('Sample ID', 'Remote Sample ID', "Facility Name", "Child's ID", "Child's Name", "Caretaker phone no.", "Sample Collection Date", "Sample Tested Date", "Lab Name", "Result");
     if ($general->isStandaloneInstance()) {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }


     $vlSampleId = [];
     $resultSet = $db->rawQuery($_SESSION['highViralResult']);
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

          if ($aRow['remote_sample'] == 'yes') {
               $decrypt = 'remote_sample_code';
          } else {
               $decrypt = 'sample_code';
          }
          $childName = ($general->crypto('doNothing', $aRow['child_name'], $aRow[$decrypt]));
          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          $row[] = ($aRow['facility_name']);
          $row[] = $aRow['child_id'];
          $row[] = ($childName);
          $row[] = $aRow['caretaker_phone_number'];
          $row[] = $sampleCollectionDate;
          $row[] = $sampleTestDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['result'];
          $vlSampleId[] = $aRow['eid_id'];
          $output[] = $row;
     }
     if ($_POST['markAsComplete'] == 'true') {
          $vlId = implode(",", $vlSampleId);
     }

     if (isset($_SESSION['highViralResultCount']) && $_SESSION['highViralResultCount'] > 50000) {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Data-Quality-report' . date('d-M-Y-H-i-s') . '.csv';
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

          /*$filters = array(
                         'hvlSampleTestDate' => 'Sample Test Date',
                         'hvlBatchCode' => 'Batch Code',
                         'hvlSampleType' => 'Sample Type',
                         'hvlFacilityName' => 'Facility Name',
                         'hvlContactStatus' => 'Contact Status',
                         'hvlGender' => 'Sex'
                    );*/


          $sheet->fromArray($headings, null, 'A3');

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

          foreach ($output as $rowNo => $rowData) {
               $rRowCount = $rowNo + 4;
               $sheet->fromArray($rowData, null, 'A' . $rRowCount);
          }


          $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
          $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-EID-CLINIC-RESULT-EXPORT-' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($filename);
          echo urlencode(basename($filename));
     }
}
