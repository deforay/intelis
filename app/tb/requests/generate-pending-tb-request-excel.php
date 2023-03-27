<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
$general = new \Vlsm\Models\General();

$tbResults = $general->getTbResults();
/* Global config data */
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*, f.*, rtbr.result as lamResult, ts.status_name, b.batch_code FROM form_tb as vl 
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
          LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
          LEFT JOIN r_tb_results as rtbr ON rtbr.result_id=vl.result 
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

if (isset($_SESSION['tbRequestData']['sWhere']) && !empty($_SESSION['tbRequestData']['sWhere'])) {
    $sQuery = $sQuery . ' WHERE ' . $_SESSION['tbRequestData']['sWhere'];
}
if (isset($_SESSION['tbRequestData']['sOrder']) && !empty($_SESSION['tbRequestData']['sOrder'])) {
    $sQuery = $sQuery . " ORDER BY " . $_SESSION['tbRequestData']['sOrder'];
}
// die($sQuery);
$rResult = $db->rawQuery($sQuery);
$excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$output = array();
$sheet = $excel->getActiveSheet();
if ($_SESSION['instanceType'] == 'standalone') {
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $headings = array("S. No.", "Sample Code", "Testing Lab Name", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Case ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Gender", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Status", "Specimen Type", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released");
    } else {
        $headings = array("S. No.", "Sample Code", "Testing Lab Name", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Patient DoB", "Patient Age", "Patient Gender", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Status", "Specimen Type", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released");
    }
} else {
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab Name", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Case ID", "Patient Name", "Patient DoB", "Patient Age", "Patient Gender", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Status", "Specimen Type", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released");
    } else {
        $headings = array("S. No.", "Sample Code", "Remote Sample Code", "Testing Lab Name", "Lab staff Assigned", "Health Facility/POE County", "Health Facility/POE State", "Health Facility/POE", "Patient DoB", "Patient Age", "Patient Gender", "Date specimen collected", "Reason for Test Request",  "Date specimen Received", "Date specimen Entered", "Specimen Status", "Specimen Type", "Date specimen Tested", "Testing Platform", "Test Method", "Result", "Date result released");
    }
}

$colNo = 1;

$styleArray = array(
    'font' => array(
        'bold' => true,
        'size' => 12,
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

$sheet->mergeCells('A1:AG1');
$nameValue = '';
foreach ($_POST as $key => $value) {
    if (trim($value) != '' && trim($value) != '-- Select --') {
        $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
    }
}
$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
    ->setValueExplicit(html_entity_decode($nameValue), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
if ($_POST['withAlphaNum'] == 'yes') {
    foreach ($headings as $field => $value) {
        $string = str_replace(' ', '', $value);
        $value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
        $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
            ->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $colNo++;
    }
} else {
    foreach ($headings as $field => $value) {
        $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
            ->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $colNo++;
    }
}
$sheet->getStyle('A3:AG3')->applyFromArray($styleArray);

$no = 1;
foreach ($rResult as $aRow) {
    $row = array();
    if ($arr['vl_form'] == 1) {
        // Get testing platform and test method 
        $tbTestQuery = "SELECT * from tb_tests where tb_id= " . $aRow['tb_id'] . " ORDER BY tb_test_id ASC";
        $tbTestInfo = $db->rawQuery($tbTestQuery);

        foreach ($tbTestInfo as $indexKey => $rows) {
            $testPlatform = $rows['testing_platform'];
            $testMethod = $rows['test_name'];
        }
    }

    //date of birth
    $dob = '';
    if ($aRow['patient_dob'] != null && trim($aRow['patient_dob']) != '' && $aRow['patient_dob'] != '0000-00-00') {
        $dob =  date("d-m-Y", strtotime($aRow['patient_dob']));
    }
    //set gender
    $gender = '';
    if ($aRow['patient_gender'] == 'male') {
        $gender = 'M';
    } else if ($aRow['patient_gender'] == 'female') {
        $gender = 'F';
    } else if ($aRow['patient_gender'] == 'not_recorded') {
        $gender = 'Unreported';
    }
    //sample collecion date
    $sampleCollectionDate = '';
    if ($aRow['sample_collection_date'] != null && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $expStr = explode(" ", $aRow['sample_collection_date']);
        $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
    }

    $sampleTestedOn = '';
    if ($aRow['sample_tested_datetime'] != null && trim($aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00') {
        $sampleTestedOn =  date("d-m-Y", strtotime($aRow['sample_tested_datetime']));
    }


    //set sample rejection
    $sampleRejection = 'No';
    if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
        $sampleRejection = 'Yes';
    }
    //result dispatched date
    $resultDispatchedDate = '';
    if ($aRow['result_printed_datetime'] != null && trim($aRow['result_printed_datetime']) != '' && $aRow['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
        $expStr = explode(" ", $aRow['result_printed_datetime']);
        $resultDispatchedDate =  date("d-m-Y", strtotime($expStr[0]));
    }

    if ($aRow['patient_name'] != '') {
        $patientFname = ($general->crypto('decrypt', $aRow['patient_name'], $aRow['patient_id']));
    } else {
        $patientFname = '';
    }
    if ($aRow['patient_surname'] != '') {
        $patientLname = ($general->crypto('decrypt', $aRow['patient_surname'], $aRow['patient_id']));
    } else {
        $patientLname = '';
    }

    // if (isset($aRow['source_of_alert']) && $aRow['source_of_alert'] != "others") {
    // 	$sourceOfArtPOE = str_replace("-", " ", $aRow['source_of_alert']);
    // } else {
    // 	$sourceOfArtPOE = $aRow['source_of_alert_other'];
    // }



    $row[] = $no;
    if ($_SESSION['instanceType'] == 'standalone') {
        $row[] = $aRow["sample_code"];
    } else {
        $row[] = $aRow["sample_code"];
        $row[] = $aRow["remote_sample_code"];
    }
    $row[] = ($aRow['lab_name']);
    $row[] = ($aRow['labTechnician']);
    $row[] = ($aRow['facility_district']);
    $row[] = ($aRow['facility_state']);
    $row[] = ($aRow['facility_name']);
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $row[] = $aRow['patient_id'];
        $row[] = $patientFname . " " . $patientLname;
    }
    $row[] = $general->humanReadableDateFormat($aRow['patient_dob']);
    $row[] = ($aRow['patient_age'] != null && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
    $row[] = ($aRow['patient_gender']);
    $row[] = $general->humanReadableDateFormat($aRow['sample_collection_date']);
    $row[] = ($aRow['test_reason_name']);
    $row[] = $general->humanReadableDateFormat($aRow['sample_received_at_lab_datetime']);
    $row[] = $general->humanReadableDateFormat($aRow['request_created_datetime']);
    $row[] = ($aRow['status_name']);
    $row[] = ($aRow['sample_name']);
    $row[] = $general->humanReadableDateFormat($aRow['sample_tested_datetime']);
    $row[] = ($testPlatform);
    $row[] = ($testMethod);
    $row[] = $tbResults[$aRow['result']];
    $row[] = $general->humanReadableDateFormat($aRow['result_printed_datetime']);

    $output[] = $row;
    $no++;
}

$start = (count($output)) + 2;
foreach ($output as $rowNo => $rowData) {
    $colNo = 1;
    foreach ($rowData as $field => $value) {
        $rRowCount = $rowNo + 4;
        $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo) . $rRowCount)
            ->applyFromArray($borderStyle);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo) . $start)
            ->applyFromArray($borderStyle);
        // // $sheet->getDefaultRowDimension($colNo)->setRowHeight(18);
        // // $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
        $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . ($rowNo + 4))
            ->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $colNo++;
    }
}
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
$filename = 'TB-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
