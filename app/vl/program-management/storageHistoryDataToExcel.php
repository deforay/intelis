<?php


use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$globalConfig = $general->getGlobalConfig();
$key = (string) $general->getGlobalConfig('key');


if (isset($_SESSION['storageHistoryDataQuery']) && trim((string) $_SESSION['storageHistoryDataQuery']) != "") {

    $output = [];
    $headings = array('Patient Name', "Freezer","Volume of Sample(ml)", "Rack", "Box", "Position", "Date Out","Comments","Status","Removal Reason");

    $resultSet = $db->rawQuery($_SESSION['storageHistoryDataQuery']);
     foreach ($resultSet as $aRow) {
          $row = [];
          if ($aRow['patient_first_name'] != '') {
            $patientFname = $aRow['patient_first_name'];
       } else {
            $patientFname = '';
       }
       if ($aRow['patient_middle_name'] != '') {
            $patientMname = $aRow['patient_middle_name'];
       } else {
            $patientMname = '';
       }
       if ($aRow['patient_last_name'] != '') {
            $patientLname = $aRow['patient_last_name'];
       } else {
            $patientLname = '';
       }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
            $patientMname = $general->crypto('decrypt', $patientMname, $key);
            $patientLname = $general->crypto('decrypt', $patientLname, $key);
        }
       
          $row[] = $patientFname.' '.$patientMname.' '.$patientLname;
          $row[] = ($aRow['storage_code']);
          $row[] = ($aRow['volume']);
          $row[] = $aRow['rack'];
          $row[] = $aRow['box'];
          $row[] = $aRow['position'];
          $row[] = $aRow['date_out'];
          $row[] = $aRow['comments'];
          $row[] = $aRow['sample_status'];
          $row[] = $aRow['removal_reason_name'];
          $output[] = $row;
     }

          $excel = new Spreadsheet();
          $sheet = $excel->getActiveSheet();

          $sheet->fromArray($headings, null, 'A3');

          foreach ($output as $rowNo => $rowData) {
               $rRowCount = $rowNo + 4;
               $sheet->fromArray($rowData, null, 'A' . $rRowCount);
          }
          $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
          $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-Storage-History-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($filename);
          echo base64_encode($filename);
}
