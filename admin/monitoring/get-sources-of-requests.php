<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new \Vlsm\Models\General();
$table = "vl_request_form";
$testType = 'vl';
if (isset($_POST['testType']) && !empty($_POST['testType'])) {
    $testType = $_POST['testType'];
}

if (isset($testType) && $testType == 'vl') {
    $table = "vl_request_form";
}
if (isset($testType) && $testType == 'eid') {
    $table = "eid_form";
}
if (isset($testType) && $testType == 'covid19') {
    $table = "form_covid19";
}
if (isset($testType) && $testType == 'hepatitis') {
    $table = "form_hepatitis";
}
if (isset($testType) && $testType == 'tb') {
    $table = "form_tb";
}

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$aColumns = array('l.facility_name', 'samples', 'samplesWithResults', 'rejected', 'source_of_request');
$orderColumns = array('l.facility_name', 'samples', 'samplesWithResults', 'rejected', 'source_of_request');

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

$sWhere = array();
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
    $sWhere[] .= $sWhereSub;
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

$sQuery = "SELECT l.facility_name as 'labname', vl.source_of_request,
        count(*) as 'samples',
        SUM( CASE WHEN (vl.result is not null AND vl.result not like '') THEN 1 ELSE 0 END) AS 'samplesWithResults',
        SUM( CASE WHEN (vl.is_sample_rejected is not null AND vl.is_sample_rejected like 'yes') THEN 1 ELSE 0 END) AS 'rejected'
        FROM $table as vl 
        LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id";

//echo $sQuery;die;
$start_date = '';
$end_date = '';
if (isset($_POST['dateRange']) && trim($_POST['dateRange']) != '') {
    $s_c_date = explode("to", $_POST['dateRange']);
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = $general->dateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = $general->dateFormat(trim($s_c_date[1]));
    }
}

if (isset($_POST['dateRange']) && trim($_POST['dateRange']) != '') {
    $sWhere[] = ' DATE(vl.sample_collection_date) BETWEEN "' . $start_date . '" AND "' . $end_date . '"';
}
if (isset($_POST['labName']) && trim($_POST['labName']) != '') {
    $sWhere[] = ' vl.lab_id IN (' . $_POST['labName'] . ')';
}
if (isset($_POST['srcRequest']) && trim($_POST['srcRequest']) != '') {
    $sWhere[] = ' vl.source_of_request = "' . $_POST['srcRequest'] . '"';
}

/* Implode all the where fields for filtering the data */
if (sizeof($sWhere) > 0) {
    $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
}

$sQuery = $sQuery . ' GROUP BY source_of_request, lab_id';
if (isset($sOrder) && $sOrder != "") {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . " ORDER BY " . $sOrder;
}
$_SESSION['sourceOfRequestsQuery'] = $sQuery;
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// echo $sQuery;die;
$rResult = $db->rawQuery($sQuery);

/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

/*
* Output
*/
$output = array(
    "sEcho" => intval($_POST['sEcho']),
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => array()
);

foreach ($rResult as $key => $aRow) {
    $row = array();
    $row[] = $aRow['labname'];
    $row[] = strtoupper($testType);
    $row[] = $aRow['samples'];
    $row[] = $aRow['samplesWithResults'];
    $row[] = $aRow['rejected'];
    $row[] = strtoupper($aRow['source_of_request']);

    $output['aaData'][] = $row;
}
echo json_encode($output);
