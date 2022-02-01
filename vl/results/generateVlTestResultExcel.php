<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();




$general = new \Vlsm\Models\General();
$formConfigQuery = "SELECT * from global_config where name='vl_form'";
$configResult = $db->query($formConfigQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
     $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
$filedGroup = array();
if ($arr['vl_form'] == 2) {
     $rs_field = 'Lab Name,Lab ID,VL Testing Platform,Specimen Type,Sample Testing Date,Viral Load Result(copiesl/ml),Log Value,Is Sample Rejected,Rejection Reason,Reviewed By,Approved By,Lab Tech. Comments,Status';
} else if ($arr['vl_form'] == 3) {
     $rs_field = 'Sample Received Date,Lab ID,VL Testing Platform,Specimen Type,Sample Testing Date,Viral Load Result(copiesl/ml),Log Value,Is Sample Rejected,Rejection Reason,Reviewed By,Approved By,Lab Tech. Comments,Status';
} else if ($arr['vl_form'] == 4) {
     $rs_field = 'Lab Name,Lab ID,VL Testing Platform,Specimen Type,Sample Testing Date,Viral Load Result(copiesl/ml),Is Sample Rejected,Rejection Reason,Reviewed By,Approved By,Lab Tech. Comments,Status';
} else if ($arr['vl_form'] == 7) {
     $rs_field = 'Lab Name,VL Testing Platform,Specimen Type,Sample Testing Date,Viral Load Result(copiesl/ml),Is Sample Rejected,Rejection Reason,Reviewed By,Approved By,Lab Tech. Comments,Status';
} else {
     $rs_field = 'Lab,Lab ID,Lab Contact Person,Lab Phone No,Sample Received Date,Result Dispatched Date,Test Method,Sample Testing Date,Log Value,Absolute Value,Text Value,Viral Load Result(copiesl/ml),Reviewed By,Reviewed Date,Approved By,Lab Tech. Comments,Status';
}
if (isset($rs_field) && trim($rs_field) != '') {
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
     $filedGroup = explode(",", $rs_field);
     $headings = $filedGroup;
     //Set heading row
     $sheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode('Sample'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
     $cellName = $sheet->getCellByColumnAndRow(0, 1)->getColumn();
     $sheet->getStyle($cellName . '1')->applyFromArray($styleArray);
     $colNo = 1;
     foreach ($headings as $field => $value) {
          $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
          $cellName = $sheet->getCellByColumnAndRow($colNo, 1)->getColumn();
          $sheet->getStyle($cellName . '1')->applyFromArray($styleArray);
          $colNo++;
     }
     //Set query and values
     $sampleResult = $db->rawQuery($_SESSION['vlResultQuery']);
     $output = array();
     foreach ($sampleResult as $sample) {
          $row = array();
          $row[] = $sample['sample_code'];
          for ($f = 0; $f < count($filedGroup); $f++) {
               if ($filedGroup[$f] == "Lab") {
                    $field = 'lab_name';
               } elseif ($filedGroup[$f] == "Lab Name") {
                    $field = 'lab_id';
               } elseif ($filedGroup[$f] == "Lab ID") {
                    $field = 'lab_code';
               } elseif ($filedGroup[$f] == "Lab Contact Person") {
                    $field = 'lab_contact_person';
               } elseif ($filedGroup[$f] == "Lab Phone No") {
                    $field = 'lab_phone_number';
               } elseif ($filedGroup[$f] == "Sample Received Date") {
                    $field = 'sample_received_at_vl_lab_datetime';
               } elseif ($filedGroup[$f] == "Result Dispatched Date") {
                    $field = 'result_dispatched_datetime';
               } elseif ($filedGroup[$f] == "Sample Testing Date") {
                    $field = 'sample_tested_datetime';
               } elseif ($filedGroup[$f] == "VL Testing Platform") {
                    $field = 'vl_test_platform';
               } elseif ($filedGroup[$f] == "Test Method") {
                    $field = 'test_methods';
               } elseif ($filedGroup[$f] == "Specimen Type") {
                    $field = 'sample_name';
               } elseif ($filedGroup[$f] == "Log Value") {
                    $field = 'result_value_log';
               } elseif ($filedGroup[$f] == "Absolute Value") {
                    $field = 'result_value_absolute';
               } elseif ($filedGroup[$f] == "Text Value") {
                    $field = 'result_value_text';
               } elseif ($filedGroup[$f] == "Viral Load Result(copiesl/ml)") {
                    $field = 'result';
               } elseif ($filedGroup[$f] == "Is Sample Rejected") {
                    $field = 'is_sample_rejected';
               } elseif ($filedGroup[$f] == "Rejection Reason") {
                    $field = 'rejection_reason_name';
               } elseif ($filedGroup[$f] == "Reviewed By") {
                    $field = 'result_reviewed_by';
               } elseif ($filedGroup[$f] == "Reviewed Date") {
                    $field = 'result_reviewed_datetime';
               } elseif ($filedGroup[$f] == "Approved By") {
                    $field = 'result_approved_by';
               } elseif ($filedGroup[$f] == "Lab Tech. Comments") {
                    $field = 'approver_comments';
               } elseif ($filedGroup[$f] == "Status") {
                    $field = 'status_name';
               }

               if ($field ==  'result_reviewed_by') {
                    $fValueQuery = "SELECT u.user_name as reviewedBy FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s_type ON s_type.sample_id=vl.sample_type LEFT JOIN r_vl_sample_rejection_reasons as s_r_r ON s_r_r.rejection_reason_id=vl.reason_for_sample_rejection LEFT JOIN user_details as u ON u.user_id = vl.result_reviewed_by where vl.vl_sample_id = '" . $sample['vl_sample_id'] . "'";
               } elseif ($field ==  'result_approved_by') {
                    $fValueQuery = "SELECT u.user_name as approvedBy FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s_type ON s_type.sample_id=vl.sample_type LEFT JOIN r_vl_sample_rejection_reasons as s_r_r ON s_r_r.rejection_reason_id=vl.reason_for_sample_rejection LEFT JOIN user_details as u ON u.user_id = vl.result_approved_by where vl.vl_sample_id = '" . $sample['vl_sample_id'] . "'";
               } elseif ($field ==  'lab_id') {
                    $fValueQuery = "SELECT f.facility_name as labName FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.lab_id=f.facility_id where vl.vl_sample_id = '" . $sample['vl_sample_id'] . "'";
               } else {
                    $fValueQuery = "SELECT $field FROM vl_request_form as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s_type ON s_type.sample_id=vl.sample_type LEFT JOIN r_vl_sample_rejection_reasons as s_r_r ON s_r_r.rejection_reason_id=vl.reason_for_sample_rejection LEFT JOIN r_sample_status as t_s ON t_s.status_id=vl.result_status where vl.vl_sample_id = '" . $sample['vl_sample_id'] . "'";
               }
               $fValueResult = $db->rawQuery($fValueQuery);
               $fieldValue = '';
               if (count($fValueResult) > 0) {
                    if ($field == 'sample_received_at_vl_lab_datetime' || $field == 'result_dispatched_datetime' || $field == 'sample_tested_datetime' || $field == 'result_reviewed_datetime' || $field == 'result_printed_datetime') {
                         if (isset($fValueResult[0][$field]) && trim($fValueResult[0][$field]) != '' && trim($fValueResult[0][$field]) != '0000-00-00 00:00:00') {
                              $xplodDate = explode(" ", $fValueResult[0][$field]);
                              $fieldValue = $general->humanDateFormat($xplodDate[0]) . " " . $xplodDate[1];
                         }
                    } elseif ($field ==  'vl_test_platform' || $field == 'is_sample_rejected') {
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
               $sheet->getDefaultRowDimension()->setRowHeight(18);
               $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
               $sheet->getCellByColumnAndRow($colNo, $rowNo + 2)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
               $sheet->getStyleByColumnAndRow($colNo, $rowNo + 2)->getAlignment()->setWrapText(true);
               $colNo++;
          }
     }
     $filename = '';
     $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
     $filename = 'VLSM-Test-Results-' . date('d-M-Y-H-i-s') . '.xlsx';
     $pathFront = realpath(TEMP_PATH);
     $writer->save($pathFront . DIRECTORY_SEPARATOR . $filename);
     echo $filename;
} else {
     echo $filename = '';
}
