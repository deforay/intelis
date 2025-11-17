<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (isset($_SESSION['covid19TATQuery']) && trim((string) $_SESSION['covid19TATQuery']) !== "") {

  $rResult = $db->rawQuery($_SESSION['covid19TATQuery']);

  $excel = new Spreadsheet();
  $output = [];
  $sheet = $excel->getActiveSheet();

  $headings = ["Covid-19 Sample ID", "Sample Collection Date", "Sample Received Date in Lab", "Sample Test Date", "Result Print Date", "Sample Email Date", "STS Result Print Date", "LIS Result Print Date"];

  $colNo = 1;

  $styleArray = ['font' => ['bold' => true, 'size' => '13'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['outline' => ['style' => Border::BORDER_THICK]]];

  $sheet->mergeCells('A1:AG1');
  $nameValue = '';
  foreach ($_POST as $key => $value) {
    if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
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

  $no = 1;
  foreach ($rResult as $aRow) {
    $row = [];
    //sample collecion date
    $sampleCollectionDate = '';
    if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
      $expStr = explode(" ", (string) $aRow['sample_collection_date']);
      $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
    }
    if (isset($aRow['sample_received_at_lab_datetime']) && trim((string) $aRow['sample_received_at_lab_datetime']) !== '' && $aRow['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
      $sampleRecievedDate = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
    } else {
      $sampleRecievedDate = '';
    }
    if (isset($aRow['sample_tested_datetime']) && trim((string) $aRow['sample_tested_datetime']) !== '' && $aRow['sample_tested_datetime'] != '0000-00-00 00:00:00') {
      $testDate = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
    } else {
      $testDate = '';
    }
    if (isset($aRow['result_printed_datetime']) && trim((string) $aRow['result_printed_datetime']) !== '' && $aRow['result_printed_datetime'] != '0000-00-00 00:00:00') {
      $printDate = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime']);
    } else {
      $printDate = '';
    }
    if (isset($aRow['result_mail_datetime']) && trim((string) $aRow['result_mail_datetime']) !== '' && $aRow['result_mail_datetime'] != '0000-00-00 00:00:00') {
      $mailDate = DateUtility::humanReadableDateFormat($aRow['result_mail_datetime']);
    } else {
      $mailDate = '';
    }

    if (isset($aRow['result_printed_on_sts_datetime']) && trim((string) $aRow['result_printed_on_sts_datetime']) !== '' && $aRow['result_printed_on_sts_datetime'] != '0000-00-00 00:00:00') {
      $printDateSts = DateUtility::humanReadableDateFormat($aRow['result_printed_on_sts_datetime']);
    } else {
      $printDateSts = '';
    }

    if (isset($aRow['result_printed_on_lis_datetime']) && trim((string) $aRow['result_printed_on_lis_datetime']) !== '' && $aRow['result_printed_on_lis_datetime'] != '0000-00-00 00:00:00') {
      $printDateLis = DateUtility::humanReadableDateFormat($aRow['result_printed_on_lis_datetime']);
    } else {
      $printDateLis = '';
    }


    $row[] = $aRow['sample_code'];
    $row[] = $sampleCollectionDate;
    $row[] = $sampleRecievedDate;
    $row[] = $testDate;
    $row[] = $printDate;
    $row[] = $mailDate;
    $row[] = $printDateSts;
    $row[] = $printDateLis;
    $output[] = $row;
    $no++;
  }

  $start = (count($output)) + 2;
  foreach ($output as $rowNo => $rowData) {
    $colNo = 1;
    $rRowCount = $rowNo + 4;
    foreach ($rowData as $field => $value) {
      $sheet->setCellValue(
        Coordinate::stringFromColumnIndex($colNo) . $rRowCount,
        html_entity_decode((string) $value)
      );
      $colNo++;
    }
  }
  $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
  $filename = 'COVID-19-TAT-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
  $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
  echo urlencode(basename($filename));
}
