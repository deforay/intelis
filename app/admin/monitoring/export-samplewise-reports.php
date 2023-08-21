<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;


ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$dateTimeUtil = new DateUtility();

$sQuery = $_SESSION['samplewiseReportsQuery'];
$rResult = $db->rawQuery($sQuery);

$headings = array('Name of the Clinic', 'External ID', "Electronic Test request Date and Time", "STS Sample Code", "Request Acknowledged Date Time", "Samples Received At Lab", "Date Time of Sample added to Batch", "Test Result", "Result Received/Entered Date and Time", "Result Approved Date and Time","Result Return Date and Time","Last Modified On");

$output = [];


	//$start = (count($output)) + 2;
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
        ),
    );
	$nameValue = '';
	foreach ($_POST as $key => $value) {
		if (trim($value) != '' && trim($value) != '-- Select --') {
			$nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
		}
	}

	$excel = new Spreadsheet();
	$sheet = $excel->getActiveSheet();
	$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($nameValue));
	
		foreach ($headings as $field => $value) {
			$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', html_entity_decode($value));
			$colNo++;
		}

        $sheet->getStyle('A3:A3')->applyFromArray($styleArray);
        $sheet->getStyle('B3:B3')->applyFromArray($styleArray);
        $sheet->getStyle('C3:C3')->applyFromArray($styleArray);
        $sheet->getStyle('D3:D3')->applyFromArray($styleArray);
        $sheet->getStyle('E3:E3')->applyFromArray($styleArray);
        $sheet->getStyle('F3:F3')->applyFromArray($styleArray);
        $sheet->getStyle('G3:G3')->applyFromArray($styleArray);
        $sheet->getStyle('H3:H3')->applyFromArray($styleArray);
        $sheet->getStyle('I3:I3')->applyFromArray($styleArray);
        $sheet->getStyle('J3:J3')->applyFromArray($styleArray);
        $sheet->getStyle('K3:K3')->applyFromArray($styleArray);
        $sheet->getStyle('L3:L3')->applyFromArray($styleArray);


$no = 1;
foreach ($rResult as $aRow) {
	$row = [];
    $row[] = $aRow['labname'];
    $row[] = $aRow['external_sample_code'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
    $row[] = $aRow['remote_sample_code'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['batch_request_created']);
    $row[] = $aRow['result'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['result_reviewed_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['result_approved_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['result_sent_to_source_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime']);
	
	$output[] = $row;
	$no++;
}


	foreach ($output as $rowNo => $rowData) {
		$colNo = 1;
		$rRowCount = $rowNo + 4;
		foreach ($rowData as $field => $value) {
			$sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode($value));
			$colNo++;
		}
	}
	$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
	$filename = 'VLSM-SAMPLEWISE-REPORT-' . date('d-M-Y-H-i-s') . '-' . $general->generateRandomString(6) . '.xlsx';
	$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
	echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);

