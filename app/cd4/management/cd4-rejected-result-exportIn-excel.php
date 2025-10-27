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

$globalConfig = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');

$delimiter = $globalConfig['default_csv_delimiter'] ?? ',';
$enclosure = $globalConfig['default_csv_enclosure'] ?? '"';


if (isset($_SESSION['rejectedViralLoadResult']) && trim((string) $_SESSION['rejectedViralLoadResult']) != "") {

     $output = [];
     $headings = array('Sample ID', 'Remote Sample ID', "Facility Name", "Patient ID.", "Patient's Name", "Sample Collection Date", "Lab Name", "Rejection Reason");
     if ($general->isStandaloneInstance()) {
          $headings = MiscUtility::removeMatchingElements($headings, ['Remote Sample ID']);
     }

     $resultSet = $db->rawQuery($_SESSION['rejectedViralLoadResult']);
     foreach ($resultSet as $aRow) {
          $row = [];
          //sample collecion date
          $sampleCollectionDate = '';
          if ($aRow['sample_collection_date'] != null && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $aRow['sample_collection_date']);
               $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
          }

          if ($aRow['remote_sample'] == 'yes') {
               $decrypt = 'remote_sample_code';
          } else {
               $decrypt = 'sample_code';
          }

          $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow[$decrypt]));

          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
          }
          $row[] = ($aRow['facility_name']);
          $row[] = $aRow['patient_art_no'];
          $row[] = ($patientFname);
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['rejection_reason_name'];
          $output[] = $row;
     }
     if (isset($_SESSION['rejectedViralLoadResultCount']) && $_SESSION['rejectedViralLoadResultCount'] > 50000) {
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Hepatitis-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.csv';
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
          $sheet->getStyle('A3:H3')->applyFromArray($styleArray);

          $sheet->fromArray($headings, null, 'A3');

          foreach ($output as $rowNo => $rowData) {
               $rRowCount = $rowNo + 4;
               $sheet->fromArray($rowData, null, 'A' . $rRowCount);
          }
          $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
          $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-CD4-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($fileName);
          echo urlencode(basename($fileName));
     }
}
