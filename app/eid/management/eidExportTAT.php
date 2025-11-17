<?php


use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
try {

	$sQuery = "SELECT vl.sample_collection_date,
				vl.sample_tested_datetime,
				vl.sample_received_at_lab_datetime,
				vl.result_printed_datetime,
				vl.sample_code,
				vl.remote_sample_code,
				vl.external_sample_code,
				vl.sample_dispatched_datetime,
				vl.request_created_by,
				vl.result_printed_on_lis_datetime,
				vl.result_printed_on_sts_datetime
			FROM form_eid as vl
			INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
			LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
			LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
				WHERE (vl.sample_collection_date IS NOT NULL)
                        AND (vl.sample_tested_datetime IS NOT NULL
						AND IFNULL(vl.result, '') != ''  ";

	if (!empty($_SESSION['eidTatData']['sWhere'])) {
		$sQuery = $sQuery . " AND " . $_SESSION['eidTatData']['sWhere'];
	}

	if (!empty($_SESSION['eidTatData']['sOrder'])) {
		$sQuery = $sQuery . " ORDER BY " . $_SESSION['eidTatData']['sOrder'];
	}

	$rResult = $db->rawQuery($sQuery);

	$excel = new Spreadsheet();
	$output = [];
	$sheet = $excel->getActiveSheet();

	$headings = ["Sample ID", "Remote Sample ID", "External Sample ID", "Sample Collection Date", "Sample Dispatch Date", "Sample Received Date in Lab", "Sample Test Date", "Result Print Date", "STS Result Print Date", "LIS Result Print Date"];

	$colNo = 1;

	$sheet->mergeCells('A1:AE1');
	$nameValue = '';
	foreach ($_POST as $key => $value) {
		if (trim((string) $value) !== '' && trim((string) $value) !== '-- Select --') {
			$nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
		}
	}

	$sheet->setCellValue('A1', $nameValue);

	$sheet->fromArray($headings, null, 'A3');

	$sheet = $general->centerAndBoldRowInSheet($sheet, 'A3');

	$no = 1;
	foreach ($rResult as $aRow) {
		$row = [];

		$row[] = $aRow['sample_code'];
		$row[] = $aRow['remote_sample_code'];
		$row[] = $aRow['external_sample_code'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_dispatched_datetime']);
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_on_sts_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_on_lis_datetime'] ?? '');
		$output[] = $row;
		$no++;
	}

	$sheet->fromArray($output, null, 'A4');

	$sheet = $general->centerAndBoldRowInSheet($sheet, 'A3');
	$sheet = $general->applyBordersToSheet($sheet);


	$writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
	$filename = 'InteLIS-EID-TAT-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
	$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
	echo urlencode(basename($filename));
} catch (Exception $e) {
	LoggerUtility::logError($e->getMessage(), [
		'code' => $e->getCode(),
		'line' => $e->getLine(),
		'file' => $e->getFile(),
		'last_db_query' => $db->getLastQuery(),
		'last_db_error' => $db->getLastError(),
		'trace' => $e->getTraceAsString()
	]);
}
