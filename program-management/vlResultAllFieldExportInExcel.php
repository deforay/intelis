<?php
session_start();
ob_start();
include('../includes/MysqliDb.php');
include('../General.php');
include ('../includes/PHPExcel.php');
 $general=new Deforay_Commons_General();

 $configQuery="SELECT * from global_config";
 $configResult=$db->query($configQuery);
 $arr = array();
 // now we create an associative array so that we can easily create view variables
 for ($i = 0; $i < sizeof($configResult); $i++) {
   $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
 }
 $country = $arr['vl_form'];
 
 $sQuery="SELECT vl.*,s.sample_name,s.status as sample_type_status,ts.*,f.facility_name,l_f.facility_name as labName,f.facility_code,f.facility_state,f.facility_district,f.facility_mobile_numbers,f.address,f.facility_hub_name,f.contact_person,f.report_email,f.country,f.longitude,f.latitude,f.facility_type,f.status as facility_status,ft.facility_type_name,lft.facility_type_name as labFacilityTypeName,l_f.facility_name as labName,l_f.facility_code as labCode,l_f.facility_state as labState,l_f.facility_district as labDistrict,l_f.facility_mobile_numbers as labPhone,l_f.address as labAddress,l_f.facility_hub_name as labHub,l_f.contact_person as labContactPerson,l_f.report_email as labReportMail,l_f.country as labCountry,l_f.longitude as labLongitude,l_f.latitude as labLatitude,l_f.facility_type as labFacilityType,l_f.status as labFacilityStatus,tr.test_reason_name,tr.test_reason_status FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id LEFT JOIN r_sample_type as s ON s.sample_id=vl.sample_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing LEFT JOIN facility_type as ft ON ft.facility_type_id=f.facility_type LEFT JOIN facility_type as lft ON lft.facility_type_id=l_f.facility_type WHERE vl.vlsm_country_id = $country";
  $rResult = $db->rawQuery($sQuery);
 
 $excel = new PHPExcel();
 $output = array();
 $sheet = $excel->getActiveSheet();
 
 $headings = array("Serial No.","Instance Id","Gender","Age In Years","Clinic Name","Clinic Code","Clinic State","Clinic District","Clinic Phone Number","Clinic Address","Clinic HUB Name","Clinic Contact Person","Clinic Report Mail","Clinic Country","Clinic Longitude","Clinic Latitude","Clinic Status","Clinic Type","Sample Type","Sample Type Status","Sample Collection Date","LAB Name","Lab Code","Lab State","Lab District","Lab Phone Number","Lab Address","Lab HUB Name","Lab Contact Person","Lab Report Mail","Lab Country","Lab Longitude","Lab Latitude","Lab Status","Lab Type","Lab Tested Date","Log Value","Absolute Value","Text Value","Absolute Decimal Value","Result","Testing Reason","Test Reason Status","Testing Status");
 
 $colNo = 0;
 
 $styleArray = array(
     'font' => array(
         'bold' => true,
         'size' => '13',
     ),
     'alignment' => array(
         'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
         'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER,
     ),
     'borders' => array(
         'outline' => array(
             'style' => \PHPExcel_Style_Border::BORDER_THIN,
         ),
     )
 );
 
 $borderStyle = array(
     'alignment' => array(
         'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
     ),
     'borders' => array(
         'outline' => array(
             'style' => \PHPExcel_Style_Border::BORDER_THIN,
         ),
     )
 );
 
 foreach ($headings as $field => $value) {
  
  $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode($value), PHPExcel_Cell_DataType::TYPE_STRING);
  $colNo++;
  
 }
 $sheet->getStyle('A1:AN1')->applyFromArray($styleArray);
 
 foreach ($rResult as $aRow) {
  $row = array();
  if($aRow['sample_tested_datetime']=='0000-00-00 00:00:00')
  {
   $aRow['sample_tested_datetime'] = '';
  }
  if($aRow['sample_collection_date']=='0000-00-00 00:00:00')
  {
   $aRow['sample_collection_date'] = '';
  }
  $row[] = $aRow['serial_no'];
  $row[] = $aRow['vlsm_instance_id'];
  $row[] = ucwords(str_replace("_"," ",$aRow['patient_gender']));
  $row[] = $aRow['patient_age_in_years'];
  $row[] = ucwords($aRow['facility_name']);
  $row[] = ucwords($aRow['facility_code']);
  $row[] = ucwords($aRow['facility_state']);
  $row[] = ucwords($aRow['facility_district']);
  $row[] = ucwords($aRow['facility_mobile_numbers']);
  $row[] = ucwords($aRow['address']);
  $row[] = ucwords($aRow['facility_hub_name']);
  $row[] = ucwords($aRow['contact_person']);
  $row[] = ucwords($aRow['report_email']);
  $row[] = ucwords($aRow['country']);
  $row[] = ucwords($aRow['longitude']);
  $row[] = ucwords($aRow['latitude']);
  $row[] = ucwords($aRow['facility_status']);
  $row[] = ucwords($aRow['facility_type_name']);
  $row[] = $aRow['sample_name'];
  $row[] = $aRow['sample_type_status'];
  $row[] = $aRow['sample_collection_date'];
  $row[] = ucwords($aRow['labName']);
  $row[] = ucwords($aRow['labCode']);
  $row[] = ucwords($aRow['labState']);
  $row[] = ucwords($aRow['labDistrict']);
  $row[] = $aRow['labPhone'];
  $row[] = $aRow['labAddress'];
  $row[] = $aRow['labHub'];
  $row[] = ucwords($aRow['labContactPerson']);
  $row[] = ucwords($aRow['labReportMail']);
  $row[] = ucwords($aRow['labCountry']);
  $row[] = ucwords($aRow['labLongitude']);
  $row[] = ucwords($aRow['labLatitude']);
  $row[] = ucwords($aRow['labFacilityStatus']);
  $row[] = ucwords($aRow['labFacilityTypeName']);
  $row[] = $aRow['sample_tested_datetime'];
  $row[] = $aRow['result_value_log'];
  $row[] = $aRow['result_value_absolute'];
  $row[] = $aRow['result_value_text'];
  $row[] = $aRow['result_value_absolute_decimal'];
  $row[] = $aRow['result'];
  $row[] = ucwords($aRow['test_reason_name']);
  $row[] = ucwords($aRow['test_reason_status']);
  $row[] = ucwords($aRow['status_name']);
  $output[] = $row;
 }

 $start = (count($output));
 foreach ($output as $rowNo => $rowData) {
  $colNo = 0;
  foreach ($rowData as $field => $value) {
    $rRowCount = $rowNo + 2;
    $cellName = $sheet->getCellByColumnAndRow($colNo,$rRowCount)->getColumn();
    $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
    $sheet->getStyle($cellName . $start)->applyFromArray($borderStyle);
    $sheet->getDefaultRowDimension()->setRowHeight(15);
    $sheet->getCellByColumnAndRow($colNo, $rowNo + 2)->setValueExplicit(html_entity_decode($value), PHPExcel_Cell_DataType::TYPE_STRING);
    $colNo++;
  }
 }
 $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
 $filename = 'vl-result-' . date('d-M-Y-H-i-s') . '.xls';
 $writer->save("../temporary". DIRECTORY_SEPARATOR . $filename);
 echo $filename;
?>