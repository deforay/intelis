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
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$sarr = $general->getSystemConfig();

if (isset($_SESSION['vlIncompleteForm']) && trim($_SESSION['vlIncompleteForm']) != "") {
     $rResult = $db->rawQuery($_SESSION['vlIncompleteForm']);

     $excel = new Spreadsheet();
     $output = [];
     $sheet = $excel->getActiveSheet();

     $headings = array('Sample Code', 'Remote Sample Code', "Sample Collection Date", "Batch Code", "Unique ART No.", "Patient's Name", "Facility Name", "Province/State", "District/County", "Sample Type", "Result", "Status");
     if ($sarr['sc_user_type'] == 'standalone') {
          if (($key = array_search("Remote Sample Code", $headings)) !== false) {
               unset($headings[$key]);
          }
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
               $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
          }
          // if($aRow['remote_sample']=='yes'){
          //   $sampleId = $aRow['remote_sample_code'];
          // }else{
          //   $sampleId = $aRow['sample_code'];
          // }

          if ($aRow['patient_first_name'] != '') {
               $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_art_no']));
          } else {
               $patientFname = '';
          }
          if ($aRow['patient_middle_name'] != '') {
               $patientMname = ($general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_art_no']));
          } else {
               $patientMname = '';
          }
          if ($aRow['patient_last_name'] != '') {
               $patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_art_no']));
          } else {
               $patientLname = '';
          }

          $row[] = $aRow['sample_code'];
          if ($sarr['sc_user_type'] != 'standalone') {
               $row[] = $aRow['remote_sample_code'];
          }
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['batch_code'];
          $row[] = $aRow['patient_art_no'];
          $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['facility_state']);
          $row[] = ($aRow['facility_district']);
          $row[] = ($aRow['sample_name']);
          $row[] = $aRow['result'];
          $row[] = ($aRow['status_name']);
          $output[] = $row;
     }

     if (isset($_SESSION['vlIncompleteFormCount']) && $_SESSION['vlIncompleteFormCount'] > 5000) 
	{
		$fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-Data-Quality-report' . date('d-M-Y-H-i-s') . '.csv';
		$file = new SplFileObject($fileName, 'w');
		$file->fputcsv($headings);
		foreach ($output as $row) {
			$file->fputcsv($row);
		}
		// we dont need the $file variable anymore
		$file = null;
		echo base64_encode($fileName);
	}
	else
	{

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
          $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'VLSM-Data-Quality-report' . date('d-M-Y-H-i-s') . '.xlsx';
          $writer->save($filename);
          echo base64_encode($filename);
     }
}
