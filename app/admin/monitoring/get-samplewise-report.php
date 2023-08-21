<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$table = "form_vl";
$primaryKey = "vl_sample_id";

$testType = 'vl';

$sources = array(
    'vlsm' => 'VLSM',
    'vlsts' => 'STS',
    'app' => 'Tablet',
    'api' => 'API',
    'dhis2' => 'DHIS2'
);
$sampleReceivedfield = "sample_received_at_vl_lab_datetime";
if (!empty($_POST['testType'])) {
    $testType = $_POST['testType'];
}

if (isset($testType) && $testType == 'vl') {
    $url = "/vl/requests/vl-requests.php";
    $table = "form_vl";
    $testName = 'Viral Load';
}
if (isset($testType) && $testType == 'eid') {
    $url = "/eid/requests/eid-requests.php";
    $table = "form_eid";
    $testName = 'EID';
}
if (isset($testType) && $testType == 'covid19') {
    $url = "/covid-19/requests/covid-19-requests.php";
    $table = "form_covid19";
    $testName = 'Covid-19';
}
if (isset($testType) && $testType == 'hepatitis') {
    $url = "/hepatitis/requests/hepatitis-requests.php";
    $table = "form_hepatitis";
    $testName = 'Hepatitis';
}
if (isset($testType) && $testType == 'tb') {
    $url = "/tb/requests/tb-requests.php";
    $table = "form_tb";
    $testName = 'TB';
    $sampleReceivedfield = "sample_received_at_lab_datetime";
}

/* Array of database columns which should be read and sent back to DataTables. Use a space where
 * you want to insert a non-database field (for example a counter or static image)
 */
$aColumns = array(
    'l.facility_name', 'vl.external_sample_code', 'vl.request_created_datetime', 'vl.remote_sample_code',
    'vl.test_requested_on', 'vl.sample_received_at_vl_lab_datetime', 'b.request_created_datetime', 'vl.result', 'vl.result_reviewed_datetime',
    'vl.result_approved_datetime', 'vl.result_sent_to_source_datetime', 'vl.last_modified_datetime'
);
$orderColumns = array(
    'l.facility_name', 'vl.external_sample_code', 'vl.request_created_datetime', 'vl.remote_sample_code',
    'vl.test_requested_on', 'vl.sample_received_at_vl_lab_datetime', 'b.request_created_datetime', 'vl.result', 'vl.result_reviewed_datetime',
    'vl.result_approved_datetime', 'vl.result_sent_to_source_datetime', 'vl.last_modified_datetime'
);

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

/*
 * Paging
 */
$sLimit = "";
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
    $sOffset = $_POST['iDisplayStart'];
    $sLimit = $_POST['iDisplayLength'];
}

/*
 * Ordering
 */

$sOrder = "";
if (isset($_POST['iSortCol_0'])) {
    $sOrder = "";
    for ($i = 0; $i < intval($_POST['iSortingCols']); $i++) {
        if ($_POST['bSortable_' . intval($_POST['iSortCol_' . $i])] == "true") {
            $sOrder .= $orderColumns[intval($_POST['iSortCol_' . $i])] . "
               " . ($_POST['sSortDir_' . $i]) . ", ";
        }
    }
    $sOrder = substr_replace($sOrder, "", -2);
}

/*
 * Filtering
 * NOTE this does not match the built-in DataTables filtering which does it
 * word by word on any field. It's possible to do here, but concerned about efficiency
 * on very large tables, and MySQL's regex functionality is very limited
 */

$sWhere = [];
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchArray = explode(" ", $_POST['sSearch']);
    $sWhereSub = "";
    foreach ($searchArray as $search) {
        $sWhereSub .= " (";
        $colSize = count($aColumns);

        for ($i = 0; $i < $colSize; $i++) {
            if ($i < $colSize - 1) {
                $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
            } else {
                $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
            }
        }
        $sWhereSub .= ")";
    }
    $sWhere[] = $sWhereSub;
}

/* Individual column filtering */
for ($i = 0; $i < count($aColumns); $i++) {
    if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
        $sWhere[] = $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
    }
}

/*
 * SQL queries
 * Get data to display
 */
$aWhere = '';
$sQuery = '';

$sQuery = "SELECT l.facility_name as 'labname',vl.external_sample_code,vl.request_created_datetime as request_created,vl.remote_sample_code,
            vl.test_requested_on,vl.sample_received_at_vl_lab_datetime,b.request_created_datetime as batch_request_created,vl.result,vl.result_reviewed_datetime,
            vl.result_approved_datetime,vl.result_sent_to_source_datetime,vl.last_modified_datetime
            FROM $table as vl
        LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id
        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
        LEFT JOIN batch_details as b ON vl.sample_batch_id=b.batch_id";

[$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '');

if (isset($_POST['dateRange']) && trim($_POST['dateRange']) != '') {
    $sWhere[] = ' DATE(vl.request_created_datetime) BETWEEN "' . $start_date . '" AND "' . $end_date . '"';
}
if (isset($_POST['labName']) && trim($_POST['labName']) != '') {
    $sWhere[] = ' vl.lab_id IN (' . $_POST['labName'] . ')';
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
    $provinceId = implode(',', $_POST['state']);
    $sWhere[] = ' f.facility_state_id  IN (' . $provinceId . ')';
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
    $districtId = implode(',', $_POST['district']);
    $sWhere[] = ' f.facility_district_id  IN (' . $districtId . ')';
}
if (isset($_POST['facilityId']) && trim($_POST['facilityId']) != '') {
    $facilityId = implode(',', $_POST['facilityId']);
    $sWhere[] = ' vl.facility_id  IN (' . $facilityId . ')';
}

if (isset($_POST['srcRequest']) && trim($_POST['srcRequest']) != '') {
    $sWhere[] = ' vl.source_of_request = "' . $_POST['srcRequest'] . '"';
}

/* Implode all the where fields for filtering the data */
if (!empty($sWhere)) {
    $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
}

$sQuery = $sQuery . ' GROUP BY source_of_request, lab_id, DATE(vl.request_created_datetime)';
if (!empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . " ORDER BY " . $sOrder;
}

$_SESSION['samplewiseReportsQuery'] = $sQuery;

[$rResult, $resultCount] = $general->getQueryResultAndCount($sQuery, null, $sLimit, $sOffset);


$calcValueQuery = "SELECT SUM(CASE WHEN (vl.test_requested_on is not null AND vl.test_requested_on not like '') THEN 1 ELSE 0 END) AS 'totalSamplesRequested',
            SUM(CASE WHEN (vl.request_created_datetime is not null AND vl.request_created_datetime not like '') THEN 1 ELSE 0 END) AS 'totalSamplesAcknowledged',
            SUM(CASE WHEN (vl.sample_received_at_vl_lab_datetime is not null AND vl.sample_received_at_vl_lab_datetime not like '') THEN 1 ELSE 0 END) AS 'totalSamplesReceived',
            SUM(CASE WHEN (vl.sample_tested_datetime is not null AND vl.sample_tested_datetime not like '') THEN 1 ELSE 0 END) AS 'totalSamplesTested',
            SUM(CASE WHEN (vl.result_dispatched_datetime is not null AND vl.result_dispatched_datetime not like '') THEN 1 ELSE 0 END) AS 'totalSamplesDispatched'
            FROM $table as vl
        LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id
        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
        LEFT JOIN batch_details as b ON vl.sample_batch_id=b.batch_id";

if (!empty($sWhere)) {
    $calcValueQuery = $calcValueQuery . ' WHERE ' . implode(" AND ", $sWhere);
}

$calcValueQuery = $calcValueQuery . ' GROUP BY source_of_request, lab_id, DATE(vl.request_created_datetime)';

$calculateFields = $db->rawQuery($calcValueQuery);


//echo count($rResult); die;
/*
 * Output
 */
$output = array(
    "sEcho" => intval($_POST['sEcho']),
    "iTotalRecords" => count($rResult),
    "iTotalDisplayRecords" => count($rResult),
    "calculation" => [],
    "aaData" => []
);
foreach ($calculateFields as $row) {
    $r = [];
    $r[] = $row['totalSamplesRequested'];
    $r[] = $row['totalSamplesAcknowledged'];
    $r[] = $row['totalSamplesReceived'];
    $r[] = $row['totalSamplesTested'];
    $r[] = $row['totalSamplesDispatched'];
    $output['calculation'][] = $r;
}
foreach ($rResult as $key => $aRow) {

    $row = [];
    $row[] = $aRow['labname'];
    $row[] = $aRow['external_sample_code'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created']);
    $row[] = $aRow['remote_sample_code'];
    $row[] = $aRow['test_requested_on'];
    $row[] = $aRow['sample_received_at_vl_lab_datetime'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['batch_request_created']);
    $row[] = $aRow['result'];
    $row[] = $aRow['result_reviewed_datetime'];
    $row[] = $aRow['result_approved_datetime'];
    $row[] = $aRow['result_sent_to_source_datetime'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime']);

    $output['aaData'][] = $row;
}


echo json_encode($output);
