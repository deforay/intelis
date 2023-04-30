<?php
if (session_status() == PHP_SESSION_NONE) {
     session_start();
}


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$sarr = $general->getSystemConfig();

if (isset($_SESSION['rejectedSamples']) && trim($_SESSION['rejectedSamples']) != "") {
     $rResult = $db->rawQuery($_SESSION['rejectedSamples']);

     $excel = new Spreadsheet();
     $output = [];
     $sheet = $excel->getActiveSheet();
     $headings = array("Lab Name", "Facility Name", "Rejection Reason", "Reason Category", "No. of Samples");


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


     $sheet->mergeCells('A1:E1');
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
     $sheet->getStyle('A3:H3')->applyFromArray($styleArray);

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
               $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($colNo) . $rRowCount,
                    html_entity_decode($value)
               );
               $colNo++;
          }
     }
     $writer = IOFactory::createWriter($excel, 'Xlsx');
     $filename = 'VLSM-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
     $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
     echo $filename;
}
