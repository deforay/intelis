<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../../startup.php');   



$general = new \Vlsm\Models\General();

//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}

if (isset($_SESSION['resultNotAvailable']) && trim($_SESSION['resultNotAvailable']) != "") {
    $rResult = $db->rawQuery($_SESSION['resultNotAvailable']);

    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $output = array();
    $sheet = $excel->getActiveSheet();
    $headings = array('Sample Code', 'Remote Sample Code', "Facility Name", "Patient ART no.", "Patient Name", "Sample Collection Date", "Lab Name");
    if ($sarr['sc_user_type'] == 'standalone') {
        $headings = array('Sample Code', "Facility Name", "Patient ART no.", "Patient Name", "Sample Collection Date", "Lab Name");
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
        ),
    );

    $borderStyle = array(
        'alignment' => array(
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        ),
        'borders' => array(
            'outline' => array(
                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ),
        ),
    );

    $sheet->mergeCells('A1:AE1');
    $nameValue = '';
    foreach ($_POST as $key => $value) {
        if (trim($value) != '' && trim($value) != '-- Select --') {
            $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
        }
    }
    $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode($nameValue), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

    foreach ($headings as $field => $value) {
        $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $colNo++;
    }
    $sheet->getStyle('A3:A3')->applyFromArray($styleArray);
    $sheet->getStyle('B3:B3')->applyFromArray($styleArray);
    $sheet->getStyle('C3:C3')->applyFromArray($styleArray);
    $sheet->getStyle('D3:D3')->applyFromArray($styleArray);
    $sheet->getStyle('E3:E3')->applyFromArray($styleArray);
    $sheet->getStyle('F3:F3')->applyFromArray($styleArray);
    if ($sarr['sc_user_type'] != 'standalone') {
        $sheet->getStyle('G3:G3')->applyFromArray($styleArray);
    }

    foreach ($rResult as $aRow) {
        $row = array();
        //sample collecion date
        $sampleCollectionDate = '';
        if ($aRow['sample_collection_date'] != null && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $aRow['sample_collection_date']);
            $sampleCollectionDate = date("d-m-Y", strtotime($expStr[0]));
        }
        // if($aRow['remote_sample']=='yes'){
        //   $sampleId = $aRow['remote_sample_code'];
        // }else{
        //   $sampleId = $aRow['sample_code'];
        // }

        if ($aRow['patient_first_name'] != '') {
            $patientFname = ucwords($general->crypto('decrypt', $aRow['patient_first_name'], $aRow['patient_art_no']));
        } else {
            $patientFname = '';
        }
        if ($aRow['patient_middle_name'] != '') {
            $patientMname = ucwords($general->crypto('decrypt', $aRow['patient_middle_name'], $aRow['patient_art_no']));
        } else {
            $patientMname = '';
        }
        if ($aRow['patient_last_name'] != '') {
            $patientLname = ucwords($general->crypto('decrypt', $aRow['patient_last_name'], $aRow['patient_art_no']));
        } else {
            $patientLname = '';
        }
        $row[] = $aRow['sample_code'];
        if ($sarr['sc_user_type'] != 'standalone') {
            $row[] = $aRow['remote_sample_code'];
        }
        $row[] = ucwords($aRow['facility_name']);
        $row[] = $aRow['patient_art_no'];
        $row[] = ucwords($patientFname . " " . $patientMname . " " . $patientLname);
        $row[] = $sampleCollectionDate;
        $row[] = ucwords($aRow['labName']);
        $output[] = $row;
    }

    $start = (count($output)) + 2;
    foreach ($output as $rowNo => $rowData) {
        $colNo = 1;
        foreach ($rowData as $field => $value) {
            $rRowCount = $rowNo + 4;
            $cellName = $sheet->getCellByColumnAndRow($colNo, $rRowCount)->getColumn();
            $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
            $sheet->getDefaultRowDimension()->setRowHeight(18);
            $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
            $sheet->getCellByColumnAndRow($colNo, $rowNo + 4)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($colNo, $rowNo + 4)->getAlignment()->setWrapText(true);
            $colNo++;
        }
    }
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $filename = 'VLSM-Results-Not-Available-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    echo $filename;

}
