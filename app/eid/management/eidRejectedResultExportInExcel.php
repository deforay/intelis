<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
  


 
$general=new General();

use App\Models\General;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

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

     $excel = new Spreadsheet();
     $output = array();
     $sheet = $excel->getActiveSheet();
     $headings = array('Sample Code','Remote Sample Code',"Facility Name","Child Id.","Child's Name","Sample Collection Date","Lab Name","Rejection Reason");
     if($sarr['sc_user_type']=='standalone') {
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
     foreach($_POST as $key=>$value){
          if(trim($value)!='' && trim($value)!='-- Select --'){
               $nameValue .= str_replace("_"," ",$key)." : ".$value."&nbsp;&nbsp;";
          }
     }
     $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
		->setValueExplicit(html_entity_decode($nameValue), DataType::TYPE_STRING);

     foreach ($headings as $field => $value) {
          $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
				->setValueExplicit(html_entity_decode($value), DataType::TYPE_STRING);
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

          if($aRow['remote_sample']=='yes'){
               $decrypt = 'remote_sample_code';
               
           }else{
               $decrypt = 'sample_code';
           }

           $patientFname = ($general->crypto('doNothing', $aRow['child_name'], $aRow[$decrypt]));

          $row[] = $aRow['sample_code'];
          if($sarr['sc_user_type']!='standalone'){
           $row[] = $aRow['remote_sample_code'];
            }
          $row[] = ($aRow['facility_name']);
          $row[] = $aRow['child_id'];
          $row[] = ($patientFname);
          $row[] = $sampleCollectionDate;
          $row[] = $aRow['labName'];
          $row[] = $aRow['rejection_reason_name'];
          $output[] = $row;
     }

     $start = (count($output))+2;
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
?>
