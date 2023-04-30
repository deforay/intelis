<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
     session_start();
}

/** @var MysqliDb $db */
$db = \App\Registries\ContainerRegistry::get('db');

/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$tableName = "track_api_requests";
$primaryKey = "api_track_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$aColumns = array('transaction_id', 'number_of_records', 'request_type', 'test_type', "api_url", "DATE_FORMAT(requested_on,'%d-%b-%Y')");
$orderColumns = array('transaction_id', 'number_of_records', 'request_type', 'test_type', 'api_url', 'requested_on');

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;
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
$sWhere[] = ' number_of_records > 0 ';
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

$sQuery = "SELECT SQL_CALC_FOUND_ROWS a.* FROM $tableName as a";

//echo $sQuery;die;
$start_date = '';
$end_date = '';
if (isset($_POST['dateRange']) && trim($_POST['dateRange']) != '') {
     $s_c_date = explode("to", $_POST['dateRange']);
     if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
          $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
     }
     if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
          $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
     }
}
if (isset($_POST['dateRange']) && trim($_POST['dateRange']) != '') {
     $sWhere[] = ' DATE(a.requested_on) >= "' . $start_date . '" AND DATE(a.requested_on) <= "' . $end_date . '"';
}

if (isset($_POST['syncedType']) && trim($_POST['syncedType']) != '') {
     $sWhere[] = ' a.request_type like "' . $_POST['syncedType'] . '"';
}
if (isset($_POST['testType']) && trim($_POST['testType']) != '') {
     $sWhere[] = ' a.test_type like "' . $_POST['testType'] . '"';
}

/* Implode all the where fields for filtering the data */
if (!empty($sWhere)) {
     $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
}

if (isset($sOrder) && $sOrder != "") {
     $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . " ORDER BY " . $sOrder;
}
$_SESSION['auditLogQuery'] = $sQuery;
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
     $row = [];
     $row[] = $aRow['transaction_id'];
     $row[] = $aRow['number_of_records'];
     $row[] = str_replace("-", " ", ($aRow['request_type']));
     $row[] = strtoupper($aRow['test_type']);
     $row[] = $aRow['api_url'];
     $row[] = date("d-M-Y h:i:s", strtotime($aRow['requested_on']));
     $row[] = '<a href="javascript:void(0);" class="btn btn-success btn-xs" style="margin-right: 2px;" title="Result" onclick="showModal(\'show-params.php?id=' . base64_encode($aRow[$primaryKey]) . '\',1200,720);"> Show Params</a>';
     $output['aaData'][] = $row;
}
echo json_encode($output);
