<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);


if (isset($_SESSION['rejectedSamples']) && trim((string) $_SESSION['rejectedSamples']) !== "") {
     $rResult = $db->rawQuery($_SESSION['rejectedSamples']);

     $excel = new Spreadsheet();
     $output = [];
     $sheet = $excel->getActiveSheet();
     $headings = [_translate("Lab Name"), _translate("Facility Name"), _translate("Rejection Reason"), _translate("Reason Category"), _translate("No. of Rejected Samples")];


     $colNo = 1;


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
          $row[] = $aRow['labname'];
          $row[] = $aRow['facility_name'];
          $row[] = $aRow['rejection_reason_name'] ?? _translate("Unspecified reason for rejection");
          $row[] = $aRow['rejection_type'] ?? _translate("Unspecified");
          $row[] = $aRow['total'];
          $output[] = $row;
     }

     $sheet->fromArray($headings, null, 'A1'); // Write headings
     $sheet->fromArray($output, null, 'A2');  // Write data starting from row 2
     $sheet = $general->centerAndBoldRowInSheet($sheet, 'A1');
     $sheet = $general->applyBordersToSheet($sheet);

     $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
     $filename = 'InteLIS-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
     $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
     echo $filename;
}
