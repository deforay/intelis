<?php
if (session_status() == PHP_SESSION_NONE) {
     session_start();
}





use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$sarr = $general->getSystemConfig();

if (isset($_SESSION['vlIncompleteForm']) && trim($_SESSION['vlIncompleteForm']) != "") {
     // error_log($_SESSION['vlIncompleteForm']);
     $rResult = $db->rawQuery($_SESSION['vlIncompleteForm']);

     $excel = new Spreadsheet();
     $output = [];
     $sheet = $excel->getActiveSheet();

     $headings = array('Sample Code', 'Remote Sample Code', "Sample Collection Date", "Batch Code", "Patient Id.", "Patient Name", "Facility Name", "Province/State", "District/County", "Sample Type", "Result", "Status");
     if ($sarr['sc_user_type'] == 'standalone') {
          $headings = array("Sample Code", "Sample Collection Date", "Batch Code", "Patient Id.",  "Patient Name", "Facility Name", "Province/State", "District/County", "Sample Type", "Result", "Status");
     }

     $colNo = 1;

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
     $nameValue = '';
     foreach ($_POST as $key => $value) {
          if (trim($value) != '' && trim($value) != '-- Select --') {
               $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
          }
     }
     $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
          ->setValueExplicit(html_entity_decode($nameValue));
     foreach ($headings as $field => $value) {
          $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
               ->setValueExplicit(html_entity_decode($value));
          $colNo++;
     }
     $sheet->getStyle('A3:M3')->applyFromArray($styleArray);

     foreach ($rResult as $aRow) {
          $row = [];
          //sample collecion date
          $sampleCollectionDate = '';
          if ($aRow['sample_collection_date'] != null && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $aRow['sample_collection_date']);
               $sampleCollectionDate =  DateUtility::humanReadableDateFormat($expStr[0]);
          }

          if ($aRow['remote_sample'] == 'yes') {
               $decrypt = 'remote_sample_code';
          } else {
               $decrypt = 'sample_code';
          }

          $patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow[$decrypt]));

          $row[] = $aRow['sample_code'];
          if ($sarr['sc_user_type'] != 'standalone') {
               $row[] = $aRow['remote_sample_code'];
          }
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['batch_code'];
          $row[] = $aRow['patient_id'];
          $row[] = ($patientFname);
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['facility_state']);
          $row[] = ($aRow['facility_district']);
          $row[] = ($aRow['sample_name']);
          $row[] = $aRow['result'];
          $row[] = ($aRow['status_name']);
          $output[] = $row;
     }
     if (isset($_SESSION['vlIncompleteFormCount']) && $_SESSION['vlIncompleteFormCount'] > 5000) {

          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-Data-Quality-report-' . date('d-M-Y-H-i-s') . '.csv';
          $file = new SplFileObject($fileName, 'w');
          $file->setCsvControl("\t", "\r\n");
          $file->fputcsv($headings);
          foreach ($output as $row) {
               $file->fputcsv($row);
          }
          // we dont need the $file variable anymore
          $file = null;
          echo base64_encode($fileName);
     } else {
          $start = (count($output)) + 2;
          foreach ($output as $rowNo => $rowData) {
               $colNo = 1;
               $rRowCount = $rowNo + 4;
               foreach ($rowData as $field => $value) {
                    $sheet->setCellValue(
                         Coordinate::stringFromColumnIndex($colNo) . $rRowCount,
                         html_entity_decode($value)
                    );
                    $colNo++;
               }
          }
          $writer = IOFactory::createWriter($excel, 'Xlsx');
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-Data-Quality-report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($fileName);
          echo base64_encode($fileName);
     }
}
