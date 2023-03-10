<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
  


 
$general=new \Vlsm\Models\General();

//system config
$systemConfigQuery ="SELECT * from system_config";
$systemConfigResult=$db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
     $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}


if(isset($_SESSION['rejectedViralLoadResult']) && trim($_SESSION['rejectedViralLoadResult'])!=""){
     $rResult = $db->rawQuery($_SESSION['rejectedViralLoadResult']);

     $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
     $output = array();
     $sheet = $excel->getActiveSheet();
     $headings = array('Sample Code','Remote Sample Code',"Facility Name","Patient ART no.","Patient Name","Sample Collection Date","Lab Name","Rejection Reason");
     if($sarr['sc_user_type']=='standalone') {
     $headings = array('Sample Code',"Facility Name","Patient ART no.","Patient Name","Sample Collection Date","Lab Name","Rejection Reason");
     }

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

     $borderStyle = array(
          'alignment' => array(
               'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
          ),
          'borders' => array(
               'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
               ),
          )
     );

     $sheet->mergeCells('A1:AE1');
     $nameValue = '';
     foreach($_POST as $key=>$value){
          if(trim($value)!='' && trim($value)!='-- Select --'){
               $nameValue .= str_replace("_"," ",$key)." : ".$value."&nbsp;&nbsp;";
          }
     }
     $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode($nameValue), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

     foreach ($headings as $field => $value) {
          $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
          $colNo++;
     }
     $sheet->getStyle('A3:H3')->applyFromArray($styleArray);

     foreach ($rResult as $aRow) {
          $row = array();
          //sample collecion date
          $sampleCollectionDate = '';
          if($aRow['sample_collection_date']!= null && trim($aRow['sample_collection_date'])!='' && $aRow['sample_collection_date']!='0000-00-00 00:00:00'){
               $expStr = explode(" ",$aRow['sample_collection_date']);
               $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
          }

          if($aRow['patient_first_name']!=''){
               $patientFname = ($general->crypto('decrypt',$aRow['patient_first_name'],$aRow['patient_art_no']));
          }else{
               $patientFname = '';
          }
          if($aRow['patient_middle_name']!=''){
               $patientMname = ($general->crypto('decrypt',$aRow['patient_middle_name'],$aRow['patient_art_no']));
          }else{
               $patientMname = '';
          }
          if($aRow['patient_last_name']!=''){
               $patientLname = ($general->crypto('decrypt',$aRow['patient_last_name'],$aRow['patient_art_no']));
          }else{
               $patientLname = '';
          }
          $row[] = $aRow['sample_code'];
          if($sarr['sc_user_type']!='standalone'){
           $row[] = $aRow['remote_sample_code'];
            }
          $row[] = ($aRow['facility_name']);
          $row[] = $aRow['patient_art_no'];
          $row[] = ($patientFname." ".$patientMname." ".$patientLname);
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['rejection_reason_name'];
          $output[] = $row;
     }

     $start = (count($output))+2;
     foreach ($output as $rowNo => $rowData) {
          $colNo = 1;
          foreach ($rowData as $field => $value) {
               $rRowCount = $rowNo + 4;
               $cellName = $sheet->getCellByColumnAndRow($colNo,$rRowCount)->getColumn();
               $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
               $sheet->getDefaultRowDimension()->setRowHeight(18);
               $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
               $sheet->getCellByColumnAndRow($colNo, $rowNo + 4)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
               $sheet->getStyleByColumnAndRow($colNo, $rowNo + 4)->getAlignment()->setWrapText(true);
               $colNo++;
          }
     }
     $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
     $filename = 'VLSM-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
     $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
     echo $filename;

}
?>
