<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}





$general = new General();

use App\Models\General;
use App\Utilities\DateUtils;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$sQuery = "select vl.sample_collection_date,vl.sample_tested_datetime,vl.sample_received_at_vl_lab_datetime,vl.result_printed_datetime,vl.result_mail_datetime,vl.request_created_by,vl.sample_code, vl.remote_sample_code from form_eid as vl INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id where (vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) NOT LIKE '0000-00-00')
                        AND (vl.sample_tested_datetime is not null AND vl.sample_tested_datetime not like '' AND DATE(vl.sample_tested_datetime) !='1970-01-01' AND DATE(vl.sample_tested_datetime) NOT LIKE '0000-00-00')
                        AND vl.result is not null
                        AND vl.result != '' ";

if (isset($_SESSION['eidTatData']['sWhere']) && !empty($_SESSION['eidTatData']['sWhere'])) {
	$sQuery = $sQuery . " AND " . $_SESSION['eidTatData']['sWhere'];
}

if (isset($_SESSION['eidTatData']['sOrder']) && !empty($_SESSION['eidTatData']['sOrder'])) {
	$sQuery = $sQuery . " ORDER BY " . $_SESSION['eidTatData']['sOrder'];
}
$rResult = $db->rawQuery($sQuery);

$excel = new Spreadsheet();
$output = [];
$sheet = $excel->getActiveSheet();

$headings = array("EID Sample Id", "Sample Collection Date", "Sample Received Date in Lab", "Sample Test Date", "Sample Print Date", "Sample Email Date");

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
			'style' => Border::BORDER_THICK,
		),
	)
);

$sheet->mergeCells('A1:AE1');
$nameValue = '';
foreach ($_POST as $key => $value) {
	if (trim($value) != '' && trim($value) != '-- Select --') {
		$nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
	}
}
$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
	->setValueExplicit(html_entity_decode($nameValue), DataType::TYPE_STRING);
foreach ($headings as $field => $value) {
	$sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '3')
		->setValueExplicit(html_entity_decode($value), DataType::TYPE_STRING);
	$colNo++;
}
$sheet->getStyle('A3:F3')->applyFromArray($styleArray);

$no = 1;
foreach ($rResult as $aRow) {
	$row = [];
	//sample collecion date
	$sampleCollectionDate = '';
	if ($aRow['sample_collection_date'] != null && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
		$sampleCollectionDate =  DateUtils::humanReadableDateFormat($aRow['sample_collection_date']);
	}
	if (isset($aRow['sample_received_at_vl_lab_datetime']) && trim($aRow['sample_received_at_vl_lab_datetime']) != '' && $aRow['sample_received_at_vl_lab_datetime'] != '0000-00-00 00:00:00') {
		$sampleRecievedDate = DateUtils::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
	} else {
		$sampleRecievedDate = '';
	}
	if (isset($aRow['sample_tested_datetime']) && trim($aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00 00:00:00') {
		$testDate = DateUtils::humanReadableDateFormat($aRow['sample_tested_datetime']);
	} else {
		$testDate = '';
	}
	if (isset($aRow['result_printed_datetime']) && trim($aRow['result_printed_datetime']) != '' && $aRow['result_printed_datetime'] != '0000-00-00 00:00:00') {
		$printDate = DateUtils::humanReadableDateFormat($aRow['result_printed_datetime']);
	} else {
		$printDate = '';
	}
	if (isset($aRow['result_mail_datetime']) && trim($aRow['result_mail_datetime']) != '' && $aRow['result_mail_datetime'] != '0000-00-00 00:00:00') {
		$mailDate = DateUtils::humanReadableDateFormat($aRow['result_mail_datetime']);
	} else {
		$mailDate = '';
	}

	$row[] = $aRow['sample_code'];
	$row[] = $sampleCollectionDate;
	$row[] = $sampleRecievedDate;
	$row[] = $testDate;
	$row[] = $printDate;
	$row[] = $mailDate;
	$output[] = $row;
	$no++;
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
$writer = IOFactory::createWriter($excel, 'Xlsx');
$filename = 'VLSM-EID-TAT-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
