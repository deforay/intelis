<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

   




$general = new \Vlsm\Models\General();

$excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$output = array();
$sheet = $excel->getActiveSheet();

$headings = array("Lab Name", "Facility Name", "Rejection Reason", "Reason Category", "No. of Samples");

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
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
    ),
    'borders' => array(
        'outline' => array(
            'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ),
    ),
);

$sheet->mergeCells('A1:E1');
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
$sheet->getStyle('A3:E3')->applyFromArray($styleArray);
$general = new \Vlsm\Models\General();
$configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
$configFormResult = $db->rawQuery($configFormQuery);
//date

$start_date = '';
$end_date = '';
$sWhere = '';
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $s_c_date = explode("to", $_POST['sampleCollectionDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = $general->dateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = $general->dateFormat(trim($s_c_date[1]));
    }
    //get value by rejection reason id
    $vlQuery = "select count(*) as `total`, vl.reason_for_sample_rejection,sr.rejection_reason_name,sr.rejection_type,sr.rejection_reason_code,fd.facility_name, lab.facility_name as `labname`
                FROM eid_form as vl
                INNER JOIN r_eid_sample_rejection_reasons as sr ON sr.rejection_reason_id=vl.reason_for_sample_rejection
                INNER JOIN facility_details as fd ON fd.facility_id=vl.facility_id
                INNER JOIN facility_details as lab ON lab.facility_id=vl.lab_id";
    $sWhere .= ' where DATE(vl.sample_collection_date) <= "' . $end_date . '" AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND vl.vlsm_country_id = "' . $configFormResult[0]['value'] . '" AND reason_for_sample_rejection!="" AND reason_for_sample_rejection IS NOT NULL';

    if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
        $sWhere .= ' AND s.sample_id = "' . $_POST['sampleType'] . '"';
    }
    if (isset($_POST['labName']) && trim($_POST['labName']) != '') {
        $sWhere .= ' AND vl.lab_id = "' . $_POST['labName'] . '"';
    }
    if (isset($_POST['clinicName']) && is_array($_POST['clinicName']) && count($_POST['clinicName']) > 0) {
        $sWhere .= " AND vl.facility_id IN (" . implode(',', $_POST['clinicName']) . ")";
    }

    $vlQuery = $vlQuery . $sWhere . " group by vl.reason_for_sample_rejection,vl.lab_id,vl.facility_id";

    $tableResult = $db->rawQuery($vlQuery);

    foreach ($tableResult as $tableRow) {
        $row = array();

        $row[] = ($tableRow['labname']);
        $row[] = ($tableRow['facility_name']);
        $row[] = ($tableRow['rejection_reason_name']);
        $row[] = strtoupper($tableRow['rejection_type']);
        $row[] = $tableRow['total'];
        $output[] = $row;
    }

    $start = (count($output)) + 2;

    $sheet->getDefaultColumnDimension()->setWidth(20);

    foreach ($output as $rowNo => $rowData) {
        $colNo = 1;
        foreach ($rowData as $field => $value) {

            $cellName = $sheet->getCellByColumnAndRow($colNo, $rowNo + 4)->getColumn();
            $sheet->getStyle($cellName . $rowNo + 4)->applyFromArray($borderStyle);

            if ($colNo == 5) {
                $sheet->getCellByColumnAndRow($colNo, $rowNo + 4)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $sheet->getCellByColumnAndRow($colNo, $rowNo + 4)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }

            $sheet->getStyleByColumnAndRow($colNo, $rowNo + 4)->getAlignment()->setWrapText(true);
            $colNo++;
        }
    }
}
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
$filename = 'VLSM-EID-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo $filename;
