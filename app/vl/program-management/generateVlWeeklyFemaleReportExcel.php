<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();

$general = new \App\Models\General();

if (isset($_SESSION['vlStatisticsFemaleQuery']) && trim($_SESSION['vlStatisticsFemaleQuery']) != "") {
    $filename = '';
    $rResult = $db->rawQuery($_SESSION['vlStatisticsFemaleQuery']);

    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $output = array();
    $sheet = $excel->getActiveSheet();

    $headings = array("Province/State", "District/County", "Site Name", "Total Female", "Pregnant <=1000 cp/ml", "Pregnant >1000 cp/ml", "Breastfeeding <=1000 cp/ml", "Breastfeeding >1000 cp/ml", "Age > 15 <=1000 cp/ml", "Age > 15 >1000 cp/ml", "Age Unknown <=1000 cp/ml", "Age Unknown >1000 cp/ml", "Age <=15 <=1000 cp/ml", "Age <=15 >1000 cp/ml");

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
            //  'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        ),
        'borders' => array(
            'outline' => array(
                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ),
        )
    );

    $sheet->mergeCells('A1:N1');
    $nameValue = '';
    foreach ($_POST as $key => $value) {
        if (trim($value) != '' && trim($value) != '-- Select --') {
            $nameValue .= str_replace("_", " ", $key) . " : " . $value . ",&nbsp;&nbsp;";
        }
    }
    $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode(($nameValue)), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

    foreach ($headings as $field => $value) {
        $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $colNo++;
    }
    $sheet->getStyle('A3:N3')->applyFromArray($styleArray);

    foreach ($rResult as $aRow) {

        $row = array();
        $row[] = ($aRow['facility_state']);
        $row[] = ($aRow['facility_district']);
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['totalFemale'];
        $row[] = $aRow['pregSuppressed'];
        $row[] = $aRow['pregNotSuppressed'];
        $row[] = $aRow['bfsuppressed'];
        $row[] = $aRow['bfNotSuppressed'];
        $row[] = $aRow['gt15suppressedF'];
        $row[] = $aRow['gt15NotSuppressedF'];
        $row[] = $aRow['ltUnKnownAgesuppressed'];
        $row[] = $aRow['ltUnKnownAgeNotSuppressed'];
        $row[] = $aRow['lt15suppressed'];
        $row[] = $aRow['lt15NotSuppressed'];
        $output[] = $row;
    }

    $start = (count($output)) + 2;
    foreach ($output as $rowNo => $rowData) {
        $colNo = 1;
        foreach ($rowData as $field => $value) {
            $rRowCount = $rowNo + 4;
            $cellName = $sheet->getCellByColumnAndRow($colNo, $rRowCount)->getColumn();
            $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
            // $sheet->getDefaultRowDimension()->setRowHeight(18);
            // $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
            $value = html_entity_decode($value);
            if (is_numeric($value)) {
                $cellDataType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
            } else {
                $cellDataType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
            }
            $sheet->getCellByColumnAndRow($colNo, $rowNo + 4)->setValueExplicit($value, $cellDataType);
            $sheet->getStyleByColumnAndRow($colNo, $rowNo + 4)->getAlignment()->setWrapText(true);
            $colNo++;
        }
    }
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $filename = 'VLSM-VL-Lab-Female-Weekly-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    echo $filename;
} else {
    echo '';
}
