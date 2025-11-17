<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_SESSION['rejectedSamples']) && trim((string) $_SESSION['rejectedSamples']) !== "") {
     $rResult = $db->rawQuery($_SESSION['rejectedSamples']);

     $excel = new Spreadsheet();
     $output = [];
     $sheet = $excel->getActiveSheet();
     $headings = ["Lab Name", "Facility Name", "Rejection Reason", "Reason Category", "No. of Samples"];


     $colNo = 1;


     $sheet->mergeCells('A1:E1');
     $nameValue = '';
     foreach ($_POST as $key => $value) {
          if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
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
          $row[] = ucwords((string) $aRow['labname']);
          $row[] = ucwords((string) $aRow['facility_name']);
          $row[] = ucwords((string) $aRow['rejection_reason_name']);
          $row[] = strtoupper((string) $aRow['rejection_type']);
          $row[] = $aRow['total'];
          $output[] = $row;
     }

     $start = (count($output)) + 2;
     foreach ($output as $rowNo => $rowData) {
          $colNo = 1;
          foreach ($rowData as $field => $value) {
               $rRowCount = $rowNo + 4;
               $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode((string) $value));

               $colNo++;
          }
     }
     $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
     $filename = 'LAB-TESTS-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
     $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
     echo $filename;
}
