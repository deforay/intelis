<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
   



$general = new \App\Models\General();
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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
    $headings = array('Sample Code', 'Remote Sample Code', "Facility Name", "Patient Id.", "Patient's Name", "Sample Collection Date", "Lab Name","Sample Status");
    if ($_SESSION['instanceType'] == 'standalone') {
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
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
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
    $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
    ->setValueExplicit(html_entity_decode($nameValue), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
foreach ($headings as $field => $value) {
$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
            ->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
$colNo++;
}
    $sheet->getStyle('A3:A3')->applyFromArray($styleArray);
    $sheet->getStyle('B3:B3')->applyFromArray($styleArray);
    $sheet->getStyle('C3:C3')->applyFromArray($styleArray);
    $sheet->getStyle('D3:D3')->applyFromArray($styleArray);
    $sheet->getStyle('E3:E3')->applyFromArray($styleArray);
    $sheet->getStyle('F3:F3')->applyFromArray($styleArray);
    $sheet->getStyle('G3:G3')->applyFromArray($styleArray);
    if ($_SESSION['instanceType'] != 'standalone') {
        $sheet->getStyle('H3:H3')->applyFromArray($styleArray);
    }

    foreach ($rResult as $aRow) {
        $row = array();
        //sample collecion date
        $sampleCollectionDate = '';
        if ($aRow['sample_collection_date'] != null && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $aRow['sample_collection_date']);
            $sampleCollectionDate = date("d-m-Y", strtotime($expStr[0]));
        }
        if($aRow['remote_sample']=='yes'){
            $decrypt = 'remote_sample_code';
            
        }else{
            $decrypt = 'sample_code';
        }
        //$patientFname = ($general->crypto('doNothing',$aRow['patient_first_name'],$aRow[$decrypt]));
        $row[] = $aRow['sample_code'];
        if ($_SESSION['instanceType'] != 'standalone') {
            $row[] = $aRow['remote_sample_code'];
        }
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['patient_id'];
        $row[] = ($aRow['patient_name']);
        $row[] = $sampleCollectionDate;
        $row[] = ($aRow['labName']);
        $row[] = ($aRow['status_name']);
        $output[] = $row;
    }

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
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $filename = 'VLSM-Results-Not-Available-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    echo $filename;

}
