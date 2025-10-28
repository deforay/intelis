<?php

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {

	$sampleCode = ($general->isSTSInstance()) ? 'remote_sample_code' : 'sample_code';
	$aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'vl.external_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", "DATE_FORMAT(vl.sample_dispatched_datetime,'%d-%b-%Y')", "DATE_FORMAT(vl.sample_received_at_lab_datetime,'%d-%b-%Y')", "DATE_FORMAT(vl.sample_tested_datetime,'%d-%b-%Y')", "DATE_FORMAT(vl.result_printed_datetime,'%d-%b-%Y')", "DATE_FORMAT(vl.result_printed_on_sts_datetime,'%d-%b-%Y')", "DATE_FORMAT(vl.result_printed_on_lis_datetime,'%d-%b-%Y')"];
	$orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'vl.external_sample_code', 'vl.sample_collection_date', 'vl.sample_dispatched_datetime', 'vl.sample_received_at_lab_datetime', 'vl.sample_tested_datetime', 'vl.result_printed_datetime', 'vl.result_printed_on_sts_datetime', 'vl.result_printed_on_lis_datetime'];



	$sOffset = $sLimit = null;
	if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
		$sOffset = $_POST['iDisplayStart'];
		$sLimit = $_POST['iDisplayLength'];
	}


	$sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

	$columnSearch = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);
	$sWhere = [];
	if (!empty($columnSearch) && $columnSearch != '') {
		$sWhere[] = $columnSearch;
	}

	$sQuery = "SELECT vl.sample_code,
				vl.remote_sample_code,
				vl.sample_collection_date,
				vl.sample_tested_datetime,
				vl.sample_received_at_lab_datetime,
				vl.result_printed_datetime,
				vl.result_mail_datetime,
				vl.request_created_by,
				vl.result_printed_on_lis_datetime,
				vl.result_printed_on_sts_datetime
				FROM form_eid as vl INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
				WHERE
				(vl.sample_collection_date is NOT NULL) AND
				(vl.sample_tested_datetime IS NOT NULL) AND
				vl.result is not null AND vl.result != ''";
	if ($general->isSTSInstance()) {
		if (!empty($_SESSION['facilityMap'])) {
			$sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")";
		}
	} else {
		$sWhere[] = " vl.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
	}

	[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '', includeTime: true);
	[$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDateAtLab'] ?? '', includeTime: true);
	[$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate'] ?? '', includeTime: true);

	if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
		$sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
	}
	if (!empty($_POST['sampleCollectionDate'])) {
		$sWhere[] = " vl.sample_collection_date BETWEEN '$start_date' AND '$end_date'";
	}
	if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) != '') {
		$sWhere[] = " vl.sample_received_at_lab_datetime BETWEEN '$labStartDate' AND '$labEndDate'";
	}

	if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) != '') {
		$sWhere[] = " vl.sample_tested_datetime BETWEEN '$start$testedStartDate_date' AND '$testedEndDate'";
	}
	if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) != '') {
		$sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
	}
	if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) != '') {
		$sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
	}

	if (!empty($sWhere)) {
		$_SESSION['eidTatData']['sWhere'] = $sWhere = implode(" AND ", $sWhere);
		$sQuery = "$sQuery AND $sWhere";
	}

	if (!empty($sOrder) && $sOrder !== '') {
		$_SESSION['eidTatData']['sOrder'] = $sOrder = preg_replace('/\s+/', ' ', $sOrder);
		$sQuery = $sQuery . " ORDER BY " . $sOrder;
	}

	if (isset($sLimit) && isset($sOffset)) {
		$sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
	}

	[$rResult, $resultCount] = $db->getDataAndCount($sQuery);


	$output = [
		"sEcho" => (int) $_POST['sEcho'],
		"iTotalRecords" => $resultCount,
		"iTotalDisplayRecords" => $resultCount,
		"aaData" => []
	];

	foreach ($rResult as $aRow) {
		$row = [];
		$row[] = $aRow['sample_code'];
		$row[] = $aRow['remote_sample_code'];
		$row[] = $aRow['external_sample_code'];
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_dispatched_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_on_sts_datetime'] ?? '');
		$row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_on_lis_datetime'] ?? '');

		$output['aaData'][] = $row;
	}

	echo JsonUtility::encodeUtf8Json($output);
} catch (Exception $e) {
	LoggerUtility::log('error', $e->getMessage(), [
		'code' => $e->getCode(),
		'line' => $e->getLine(),
		'file' => $e->getFile(),
		'last_db_query' => $db->getLastQuery(),
		'last_db_error' => $db->getLastError(),
		'trace' => $e->getTraceAsString()
	]);
}
