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
$sarr = $general->getSystemConfig();

$arr = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');

$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['highTbResult']) && trim((string) $_SESSION['highTbResult']) !== "") {


     $output = [];
     $headings = ['Sample ID', 'Remote Sample ID', "Facility Name", "Patient ART Number", "Patient's Name", "Sample Collection Date", "Sample Tested Date", "Lab Name", "VL Result in cp/mL"];
     if ($general->isStandaloneInstance()) {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }

     $vlSampleId = [];
     $resultSet = $db->rawQueryGenerator($_SESSION['highTbResult']);
     foreach ($resultSet as $aRow) {
          $row = [];
          //sample collecion date
          $sampleCollectionDate = '';
          $sampleTestDate = '';
          if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_collection_date']);
               $sampleCollectionDate = date("d-m-Y", strtotime($expStr[0]));
          }
          if ($aRow['sample_tested_datetime'] != null && trim((string) $aRow['sample_tested_datetime']) !== '' && $aRow['sample_tested_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_tested_datetime']);
               $sampleTestDate = date("d-m-Y", strtotime($expStr[0]));
          }

          $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';
          $patientFname = $general->crypto('doNothing', $aRow['patient_name'], $aRow[$decrypt]);
          $patientMname = $general->crypto('doNothing', $aRow['patient_surname'], $aRow[$decrypt]);
          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
               $patientMname = $general->crypto('decrypt', $patientMname, $key);
          }
          $row[] = ($aRow['facility_name']);
          $row[] = $aRow['patient_id'];
          $row[] = ($patientFname . " " . $patientMname);
          $row[] = $sampleCollectionDate;
          $row[] = $sampleTestDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['result'];
          $vlSampleId[] = $aRow['vl_sample_id'];
          $output[] = $row;
     }
     if ($_POST['markAsComplete'] == 'true') {
          $vlId = implode(",", $vlSampleId);
     }
     if (isset($_SESSION['highTbResultCount']) && $_SESSION['highTbResultCount'] > 50000) {

          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-High-TB-Report-' . date('d-M-Y-H-i-s') . '.csv';
          $fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
          // we dont need the $output variable anymore
          unset($output);
          echo base64_encode((string) $fileName);
     } else {
          $excel = new Spreadsheet();
          $sheet = $excel->getActiveSheet();

          $styleArray = ['font' => ['bold' => true, 'size' => '13'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['outline' => ['style' => Border::BORDER_THIN]]];

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
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-High-TB-Report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($fileName);
          echo urlencode(basename($fileName));
     }
}
