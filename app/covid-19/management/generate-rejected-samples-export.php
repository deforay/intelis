<?php
if (session_status() == PHP_SESSION_NONE) {
     session_start();
}
ob_start();




$general = new \Vlsm\Models\General();
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
     $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}


if (isset($_SESSION['rejectedSamples']) && trim($_SESSION['rejectedSamples']) != "") {
     $rResult = $db->rawQuery($_SESSION['rejectedSamples']);

     $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
     $output = array();
     $sheet = $excel->getActiveSheet();
     $headings = array("Lab Name", "Facility Name", "Rejection Reason", "Reason Category", "No. of Samples");


     $colNo = 1;

     $styleArray = array(
          'font' => array(
               'bold' => true,
               'size' => '13',
          ),
          'alignment' => array(
               'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
               'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
          ),
          'borders' => array(
               'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
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
     ->setValueExplicit(html_entity_decode($nameValue), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

     foreach ($headings as $field => $value) {
          $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
                    ->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
          $colNo++;
     }
     $sheet->getStyle('A3:H3')->applyFromArray($styleArray);

     foreach ($rResult as $aRow) {
          $row = array();
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
                html_entity_decode($value));
               $colNo++;
          }
     }
     $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
     $filename = 'VLSM-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
     $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
     echo $filename;
}
