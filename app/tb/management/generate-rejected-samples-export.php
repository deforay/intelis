<?php
if (session_status() == PHP_SESSION_NONE) {
     session_start();
}


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_SESSION['rejectedSamples']) && trim($_SESSION['rejectedSamples']) != "") {
     $rResult = $db->rawQuery($_SESSION['rejectedSamples']);

     $output = [];
     $excel = new Spreadsheet();
     $sheet = $excel->getActiveSheet();
     $headings = array("Lab Name", "Facility Name", "Rejection Reason", "Reason Category", "No. of Samples");


     $colNo = 1;
     $nameValue = '';
     foreach ($_POST as $key => $value) {
          if (trim($value) != '' && trim($value) != '-- Select --') {
               $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
          }
     }
     $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($nameValue));
     foreach ($headings as $field => $value) {
          $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', html_entity_decode($value));
          $colNo++;
     }

     foreach ($rResult as $aRow) {
          $row = [];
          $row[] = ($aRow['labname']);
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['rejection_reason_name']);
          $row[] = strtoupper($aRow['rejection_type']);
          $row[] = $aRow['total'];
          $output[] = $row;
     }

     $start = (count($output)) + 2;
     foreach ($output as $rowNo => $rowData) {
          $colNo = 1;
          $rRowCount = $rowNo + 4;
          foreach ($rowData as $field => $value) {
               $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode($value));
               $colNo++;
          }
     }
     $writer = IOFactory::createWriter($excel, 'Xlsx');
     $filename = 'VLSM-TB-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
     $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
     echo $filename;
}
