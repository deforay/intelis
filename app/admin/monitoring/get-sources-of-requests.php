<?php

use App\Services\CommonService;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new CommonService();
$table = "form_vl";
$primaryKey = "vl_sample_id";

$testType = 'vl';

$sources = array(
    'vlsm' => 'VLSM',
    'vlsts' => 'VLSTS',
    'app' => 'Tablet',
    'api' => 'API',
    'dhis2' => 'DHIS2'
);
$sampleReceivedfield = "sample_received_at_vl_lab_datetime";
if (isset($_POST['testType']) && !empty($_POST['testType'])) {
    $testType = $_POST['testType'];
}

if (isset($testType) && $testType == 'vl') {
    $url = "/vl/requests/vlRequest.php";
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
$aColumns = array('l.facility_name',  'vl.source_of_request', 'vl.request_created_datetime');
$orderColumns = array('l.facility_name', '', '', '', '', 'vl.source_of_request', 'vl.request_created_datetime');

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

$sQuery = "SELECT SQL_CALC_FOUND_ROWS l.facility_name as 'labname',
        vl.source_of_request,
        vl.sample_collection_date, 
        count(*) as 'samples',
        vl.lab_id,
        SUM(CASE WHEN (vl.result is not null AND vl.result not like '' AND result_status = 7) THEN 1 ELSE 0 END) AS 'samplesWithResults',
        SUM(CASE WHEN (vl.is_sample_rejected is not null AND vl.is_sample_rejected like 'yes') THEN 1 ELSE 0 END) AS 'rejected',
        SUM(CASE WHEN (vl." . $sampleReceivedfield . " is not null AND vl." . $sampleReceivedfield . " not like '') THEN 1 ELSE 0 END) AS 'noOfSampleReceivedAtLab',
        SUM(CASE WHEN (vl.result_sent_to_source is not null and vl.result_sent_to_source = 'sent') THEN 1 ELSE 0 END) AS 'noOfResultsReturned',
        MAX(request_created_datetime) AS 'lastRequest'
        FROM $table as vl 
        LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id";

//echo $sQuery;die;
$start_date = '';
$end_date = '';
if (isset($_POST['dateRange']) && trim($_POST['dateRange']) != '') {
    $s_c_date = explode("to", $_POST['dateRange']);
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtils::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtils::isoDateFormat(trim($s_c_date[1]));
    }
}

$sWhere[] = " (lab_id is not null AND lab_id not like '' AND lab_id > 0) ";


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
if (!empty($sWhere)) {
    $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
}

$sQuery = $sQuery . ' GROUP BY source_of_request, lab_id, DATE(vl.sample_collection_date)';
if (isset($sOrder) && $sOrder != "") {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . " ORDER BY " . $sOrder;
}
$_SESSION['sourceOfRequestsQuery'] = $sQuery;
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// die($sQuery);
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
    $params = array($aRow['sample_collection_date'], $aRow['lab_id'], $aRow['source_of_request']);
    if (isset($aRow['samples']) && $aRow['samples'] > 0) {
        $samples = $params;
        $samples[] = 9;
        $samplesParams = implode("##", $samples);
    }

    if (isset($aRow['noOfSampleReceivedAtLab']) && $aRow['noOfSampleReceivedAtLab'] > 0) {
        $register = $params;
        $register[] = 6;
        $registerParams = implode("##", $register);
    }

    if (isset($aRow['samplesWithResults']) && $aRow['samplesWithResults'] > 0) {
        $tested = $params;
        $tested[] = 7;
        $testedParams = implode("##", $tested);
    }

    if (isset($aRow['rejected']) && $aRow['rejected'] > 0) {
        $rejected = $params;
        $rejected[] = 4;
        $rejectedParams = implode("##", $rejected);
    }

    if (isset($aRow['noOfResultsReturned']) && $aRow['noOfResultsReturned'] > 0) {
        $returned = $params;
        $returned[] = "sent";
        $returnedParams = implode("##", $returned);
    }

    $row = [];
    $row[] = $aRow['labname'];
    $row[] = $testName;
    if (isset($aRow['samples']) && $aRow['samples'] > 0) {
        $row[] = '<a href="javascript:void(0);" class="" style="margin-right: 2px;" title="View History" onclick="showModal(\'' . $url . '?id=' . base64_encode($registerParams) . '\',1200,700);"> ' . $aRow['samples'] . '</a>';
    } else {
        $row[] = $aRow['samples'];
    }
    if (isset($aRow['noOfSampleReceivedAtLab']) && $aRow['noOfSampleReceivedAtLab'] > 0) {
        $row[] = '<a href="javascript:void(0);" class="" style="margin-right: 2px;" title="View History" onclick="showModal(\'' . $url . '?id=' . base64_encode($registerParams) . '\',1200,700);"> ' . $aRow['noOfSampleReceivedAtLab'] . '</a>';
    } else {
        $row[] = $aRow['noOfSampleReceivedAtLab'];
    }
    if (isset($aRow['samplesWithResults']) && $aRow['samplesWithResults'] > 0) {
        $row[] = '<a href="javascript:void(0);" class="" style="margin-right: 2px;" title="View History" onclick="showModal(\'' . $url . '?id=' . base64_encode($testedParams) . '\',1200,700);"> ' . $aRow['samplesWithResults'] . '</a>';
    } else {
        $row[] = $aRow['samplesWithResults'];
    }
    if (isset($aRow['rejected']) && $aRow['rejected'] > 0) {
        $row[] = '<a href="javascript:void(0);" class="" style="margin-right: 2px;" title="View History" onclick="showModal(\'' . $url . '?id=' . base64_encode($rejectedParams) . '\',1200,700);"> ' . $aRow['rejected'] . '</a>';
    } else {
        $row[] = $aRow['rejected'];
    }
    if (isset($aRow['noOfResultsReturned']) && $aRow['noOfResultsReturned'] > 0) {
        $row[] = '<a href="javascript:void(0);" class="" style="margin-right: 2px;" title="View History" onclick="showModal(\'' . $url . '?id=' . base64_encode($returnedParams) . '\',1200,700);"> ' . $aRow['noOfResultsReturned'] . '</a>';
    } else {
        $row[] = $aRow['noOfResultsReturned'];
    }
    $row[] = !empty($sources[$aRow['source_of_request']]) ? $sources[$aRow['source_of_request']] : strtoupper($aRow['source_of_request']);
    $row[] = DateUtils::humanReadableDateFormat($aRow['lastRequest']);
    // $row[] = '<a href="javascript:void(0);" class="btn btn-success btn-xs" style="margin-right: 2px;" title="View History" onclick="showModal(\'' . $url . '\',?id=' . base64_encode() . ');"> View more</a>';

    $output['aaData'][] = $row;
}
echo json_encode($output);
