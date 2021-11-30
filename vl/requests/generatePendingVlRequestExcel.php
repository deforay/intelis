<?php
ob_start();
#require_once('../../startup.php');



$general = new \Vlsm\Models\General();
//get other config details
$geQuery = "SELECT * FROM other_config WHERE type = 'request'";
$geResult = $db->rawQuery($geQuery);
$mailconf = array();
foreach ($geResult as $row) {
   $mailconf[$row['name']] = $row['value'];
}

$filedGroup = array();
// if (isset($mailconf['rq_field']) && trim($mailconf['rq_field']) != '') {
//Excel code start
$excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $excel->getActiveSheet();
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
$headings = array("Sample ID", "Province/State", "District/County", "Clinic Name", "Clinician Name", "Sample Collection Date", "Sample Received Date", "Collected by (Initials)", "Gender", "Date Of Birth", "Age in years", "Is Patient Pregnant?", "Is Patient Breastfeeding?", "Patient ID/ART/TRACNET", "Date Of ART Initiation", "ART Regimen", "Date Of Last Viral Load Test", "Result Of Last Viral Load", "Viral Load Log", "Reason For VL Test", "Lab Name", "Specimen type", "Viral Load Result(copiesl/ml)");
//Set heading row
$colNo = 1;
foreach ($headings as $field => $value) {
   if ($value == 'Province') {
      $value = 'Province/State';
   } else if ($value == 'District Name') {
      $value = 'District/County';
   }
   $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
   $cellName = $sheet->getCellByColumnAndRow($colNo, 1)->getColumn();
   $sheet->getStyle($cellName . '1')->applyFromArray($styleArray);
   $colNo++;
}
//Set query and values
$sampleResult = $db->rawQuery($_SESSION['vlRequestSearchResultQuery']);
$output = array();
foreach ($sampleResult as $sample) {
   $row = array();
   for ($f = 0; $f < count($filedGroup); $f++) {
      $field = '';
      if ($filedGroup[$f] == "Sample ID") {
         $field = 'sample_code';
      } elseif ($filedGroup[$f] == "Urgency") {
         $field = 'test_urgency';
      } elseif ($filedGroup[$f] == "Province") {
         $field = 'facility_state';
      } elseif ($filedGroup[$f] == "District Name") {
         $field = 'facility_district';
      } elseif ($filedGroup[$f] == "Clinic Name") {
         $field = 'facility_name';
      } elseif ($filedGroup[$f] == "Clinician Name") {
         $field = 'lab_contact_person';
      } elseif ($filedGroup[$f] == "Sample Collection Date") {
         $field = 'sample_collection_date';
      } elseif ($filedGroup[$f] == "Sample Received Date") {
         $field = 'sample_received_at_vl_lab_datetime';
      } elseif ($filedGroup[$f] == "Collected by (Initials)") {
         $field = 'sample_collected_by';
      } elseif ($filedGroup[$f] == "Gender") {
         $field = 'patient_gender';
      } elseif ($filedGroup[$f] == "Date Of Birth") {
         $field = 'patient_dob';
      } elseif ($filedGroup[$f] == "Age in years") {
         $field = 'patient_age_in_years';
      } elseif ($filedGroup[$f] == "Age in months") {
         $field = 'patient_age_in_months';
      } elseif ($filedGroup[$f] == "Is Patient Pregnant?") {
         $field = 'is_patient_pregnant';
      } elseif ($filedGroup[$f] == "Is Patient Breastfeeding?") {
         $field = 'is_patient_breastfeeding';
      } elseif ($filedGroup[$f] == "Patient ID/ART/TRACNET") {
         $field = 'patient_art_no';
      } elseif ($filedGroup[$f] == "Date Of ART Initiation") {
         $field = 'date_of_initiation_of_current_regimen';
      } elseif ($filedGroup[$f] == "ART Regimen") {
         $field = 'current_regimen';
      } elseif ($filedGroup[$f] == "Patient consent to SMS Notification?") {
         $field = 'consent_to_receive_sms';
      } elseif ($filedGroup[$f] == "Patient Mobile Number") {
         $field = 'patient_mobile_number';
      } elseif ($filedGroup[$f] == "Date Of Last Viral Load Test") {
         $field = 'last_viral_load_date';
      } elseif ($filedGroup[$f] == "Result Of Last Viral Load") {
         $field = 'last_viral_load_result';
      } elseif ($filedGroup[$f] == "Viral Load Log") {
         $field = 'last_vl_result_in_log';
      } elseif ($filedGroup[$f] == "Reason For VL Test") {
         $field = 'test_reason_name';
      } elseif ($filedGroup[$f] == "Lab Name") {
         $field = 'lab_id';
      } elseif ($filedGroup[$f] == "Lab ID") {
         $field = 'lab_code';
      } elseif ($filedGroup[$f] == "VL Testing Platform") {
         $field = 'vl_test_platform';
      } elseif ($filedGroup[$f] == "Specimen type") {
         $field = 'sample_name';
      } elseif ($filedGroup[$f] == "Sample Testing Date") {
         $field = 'sample_tested_datetime';
      } elseif ($filedGroup[$f] == "Viral Load Result(copiesl/ml)") {
         $field = 'result_value_absolute';
      } elseif ($filedGroup[$f] == "Log Value") {
         $field = 'result_value_log';
      } elseif ($filedGroup[$f] == "Is Sample Rejected") {
         $field = 'is_sample_rejected';
      } elseif ($filedGroup[$f] == "Rejection Reason") {
         $field = 'rejection_reason_name';
      } elseif ($filedGroup[$f] == "Reviewed By") {
         $field = 'result_reviewed_by';
      } elseif ($filedGroup[$f] == "Approved By") {
         $field = 'result_approved_by';
      } elseif ($filedGroup[$f] == "Lab Tech. Comments") {
         $field = 'approver_comments';
      } elseif ($filedGroup[$f] == "Status") {
         $field = 'status_name';
      }

      if ($field == '') {
         continue;
      }
      if ($field ==  'result_reviewed_by') {
         $fValueQuery = "SELECT u.user_name as reviewedBy FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s_type ON s_type.sample_id=vl.sample_type LEFT JOIN r_vl_sample_rejection_reasons as s_r_r ON s_r_r.rejection_reason_id=vl.reason_for_sample_rejection LEFT JOIN user_details as u ON u.user_id = vl.result_reviewed_by where vl.vl_sample_id = '" . $sample['vl_sample_id'] . "'";
      } elseif ($field ==  'result_approved_by') {
         $fValueQuery = "SELECT u.user_name as approvedBy FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s_type ON s_type.sample_id=vl.sample_type LEFT JOIN r_vl_sample_rejection_reasons as s_r_r ON s_r_r.rejection_reason_id=vl.reason_for_sample_rejection LEFT JOIN user_details as u ON u.user_id = vl.result_approved_by where vl.vl_sample_id = '" . $sample['vl_sample_id'] . "'";
      } elseif ($field ==  'lab_id') {
         $fValueQuery = "SELECT f.facility_name as labName FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.lab_id=f.facility_id where vl.vl_sample_id = '" . $sample['vl_sample_id'] . "'";
      } elseif ($field ==  'test_reason_name') {
         $fValueQuery = "SELECT test_reason_name FROM vl_request_form as vl LEFT JOIN r_vl_test_reasons as rvl ON vl.reason_for_vl_testing=rvl.test_reason_id where vl.vl_sample_id = '" . $sample['vl_sample_id'] . "'";
      } else {
         $fValueQuery = "SELECT $field FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s_type ON s_type.sample_id=vl.sample_type LEFT JOIN r_vl_sample_rejection_reasons as s_r_r ON s_r_r.rejection_reason_id=vl.reason_for_sample_rejection LEFT JOIN r_sample_status as t_s ON t_s.status_id=vl.result_status where vl.vl_sample_id = '" . $sample['vl_sample_id'] . "'";
      }
      $fValueResult = $db->rawQuery($fValueQuery);
      $fieldValue = '';
      if (count($fValueResult) > 0) {
         if ($field == 'sample_collection_date' || $field == 'sample_received_at_vl_lab_datetime' || $field == 'sample_tested_datetime') {
            if (isset($fValueResult[0][$field]) && trim($fValueResult[0][$field]) != '' && trim($fValueResult[0][$field]) != '0000-00-00 00:00:00') {
               $xplodDate = explode(" ", $fValueResult[0][$field]);
               $fieldValue = $general->humanDateFormat($xplodDate[0]) . " " . $xplodDate[1];
            }
         } elseif ($field == 'patient_dob' || $field == 'date_of_initiation_of_current_regimen' || $field == 'last_viral_load_date') {
            if (isset($fValueResult[0][$field]) && trim($fValueResult[0][$field]) != '' && trim($fValueResult[0][$field]) != '0000-00-00') {
               $fieldValue = $general->humanDateFormat($fValueResult[0][$field]);
            }
         } elseif ($field ==  'vl_test_platform' || $field ==  'patient_gender' || $field == 'is_sample_rejected') {
            $fieldValue = ucwords(str_replace("_", " ", $fValueResult[0][$field]));
         } elseif ($field ==  'result_reviewed_by') {
            $fieldValue = $fValueResult[0]['reviewedBy'];
         } elseif ($field ==  'result_approved_by') {
            $fieldValue = $fValueResult[0]['approvedBy'];
         } elseif ($field ==  'lab_id') {
            $fieldValue = $fValueResult[0]['labName'];
         } else {
            $fieldValue = $fValueResult[0][$field];
         }
      }
      $row[] = $fieldValue;
   }
   $output[] = $row;
}
$start = (count($output));
foreach ($output as $rowNo => $rowData) {
   $colNo = 1;
   foreach ($rowData as $field => $value) {
      $rRowCount = $rowNo + 2;
      $cellName = $sheet->getCellByColumnAndRow($colNo, $rRowCount)->getColumn();
      $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
      $sheet->getStyle($cellName . $start)->applyFromArray($borderStyle);
      $sheet->getDefaultRowDimension()->setRowHeight(15);
      $sheet->getCellByColumnAndRow($colNo, $rowNo + 2)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
      $colNo++;
   }
}
$filename = '';
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
$filename = 'VLSM-Test-Requests-' . date('d-M-Y-H-i-s') . '.xlsx';
$pathFront = realpath(TEMP_PATH);
$writer->save($pathFront . DIRECTORY_SEPARATOR . $filename);
echo $filename;
/* } else {
   echo $filename = '';
} */
