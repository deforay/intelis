<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$formId = (int) $general->getGlobalConfig('vl_form');

$tableName = "form_eid";
$primaryKey = "eid_id";

$aColumns = ['vl.sample_tested_datetime', 'f.facility_name'];
$orderColumns = ['vl.sample_tested_datetime', 'f.facility_name'];

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;

$sOffset = $sLimit = null;
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
     $sOffset = (int) $_POST['iDisplayStart'];
     $sLimit = (int) $_POST['iDisplayLength'];
}



$sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

$sWhere = [];
$columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? null, $aColumns);
if (!empty($columnSearch)) {
     $sWhere[] = $columnSearch;
}





$sQuery = "SELECT SQL_CALC_FOUND_ROWS
          DATE_FORMAT(DATE(vl.sample_tested_datetime), '%b-%Y') as monthrange,
          f.facility_id,
          f.facility_name,
          vl.is_sample_rejected,
          vl.sample_tested_datetime,
          vl.sample_collection_date,
          tl.monthly_target,
          SUM(CASE WHEN (is_sample_rejected IS NOT NULL AND is_sample_rejected LIKE 'yes%') THEN 1 ELSE 0 END) as totalRejected,
          SUM(CASE WHEN (sample_tested_datetime IS NULL AND sample_collection_date IS NOT NULL) THEN 1 ELSE 0 END) as totalReceived,
          SUM(CASE WHEN (sample_collection_date IS NOT NULL) THEN 1 ELSE 0 END) as totalCollected FROM testing_labs as tl INNER JOIN form_eid as vl ON vl.lab_id=tl.facility_id LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          RIGHT JOIN testing_lab_health_facilities_map as fm ON vl.lab_id=fm.vl_lab_id";

[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
$sTestDate = '';
$eTestDate = '';
if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
     $s_t_date = explode("to", (string) $_POST['sampleTestDate']);
     if (isset($s_t_date[0]) && trim($s_t_date[0]) !== "") {
          $sTestDate = DateUtility::isoDateFormat(trim($s_t_date[0]));
     }
     if (isset($s_t_date[1]) && trim($s_t_date[1]) !== "") {
          $eTestDate = DateUtility::isoDateFormat(trim($s_t_date[1]));
     }
}


if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
     if (trim((string) $sTestDate) === trim((string) $eTestDate)) {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
     }
}

if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
     $sWhere[] = ' vl.lab_id IN (' . $db->inIntList($_POST['facilityName']) . ')';
}
if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
     $sWhere[] = " f.facility_district LIKE '%" . $db->escapeLike($_POST['district']) . "%' ";
}
if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
     $sWhere[] = " f.facility_state LIKE '%" . $db->escapeLike($_POST['state']) . "%' ";
}

$sWhere[] = ' vl.result!="" AND vl.result_status != ' . RECEIVED_AT_CLINIC;

$sWhere[] = "  tl.test_type = 'eid'";

if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
     $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}

if ($labScope = $general->labScopeWhere('vl')) {
    $sWhere[] = $labScope;
}

if ($sWhere !== []) {
     $sWhere = ' WHERE ' . implode(' AND ', $sWhere);
}


$sQuery = $sQuery . ' ' . $sWhere . ' GROUP BY f.facility_id, YEAR(vl.sample_tested_datetime), MONTH(vl.sample_tested_datetime)';
if (($_POST['targetType'] ?? '') == 1) {
     $sQuery .= ' HAVING tl.monthly_target > SUM(CASE WHEN (sample_collection_date IS NOT NULL) THEN 1 ELSE 0 END) ';
} elseif (($_POST['targetType'] ?? '') == 2) {
     $sQuery .= ' HAVING tl.monthly_target < SUM(CASE WHEN (sample_collection_date IS NOT NULL) THEN 1 ELSE 0 END) ';
}

$_SESSION['eidMonitoringThresholdReportQuery'] = $sQuery;

$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */

$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];


$output = ["sEcho" => (int) $_POST['sEcho'], "aaData" => []];

$cnt = 0;
foreach ($rResult as $rowData) {
     $cnt++;
     $data = [];
     $data[] = ($rowData['facility_name']);
     $data[] = $rowData['monthrange'];
     $data[] = $rowData['totalReceived'];
     $data[] = $rowData['totalRejected'];
     $data[] = $rowData['totalCollected'];
     $data[] = $rowData['monthly_target'];
     $output['aaData'][] = $data;
}
$output['iTotalDisplayRecords'] = $cnt;
$output['iTotalRecords'] = $cnt;
echo json_encode($output);
