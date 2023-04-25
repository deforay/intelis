<?php

use App\Services\CommonService;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new CommonService();
$tableName = "user_login_history";
$primaryKey = "history_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */

$aColumns = array('login_id', 'login_attempted_datetime', 'ip_address', 'browser', 'operating_system', 'login_status');

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
            $sOrder .= $aColumns[intval($_POST['iSortCol_' . $i])] . "
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

$sWhere = "";
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchArray = explode(" ", $_POST['sSearch']);
    $sWhereSub = "";
    foreach ($searchArray as $search) {
        if ($sWhereSub == "") {
            $sWhereSub .= "(";
        } else {
            $sWhereSub .= " AND (";
        }
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
    $sWhere .= $sWhereSub;
}

/* Individual column filtering */
for ($i = 0; $i < count($aColumns); $i++) {
    if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
        if ($sWhere == "") {
            $sWhere .= $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
        } else {
            $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
        }
    }
}

/*
         * SQL queries
         * Get data to display
        */
$sQuery = "SELECT SQL_CALC_FOUND_ROWS ul.history_id,ul.login_id,ul.login_attempted_datetime,ul.login_status,ul.ip_address,ul.browser,ul.operating_system FROM user_login_history as ul";

$start_date = '';
$end_date = '';
$cWhere = [];

if (isset($_POST['userDate']) && trim($_POST['userDate']) != '') {
    $s_c_date = explode("to", $_POST['userDate']);
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtils::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtils::isoDateFormat(trim($s_c_date[1]));
    }
}

if (isset($_POST['userDate']) && trim($_POST['userDate']) != '') {
    if (trim($start_date) == trim($end_date)) {
        $cWhere[] = ' DATE(ul.login_attempted_datetime) = "' . $start_date . '"';
    } else {
        $cWhere[] = ' DATE(ul.login_attempted_datetime) >= "' . $start_date . '" AND DATE(ul.login_attempted_datetime) <= "' . $end_date . '"';
    }
}
// print_r($cWhere);die;

if (isset($_POST['loginId']) && trim($_POST['loginId']) != '') {
    $cWhere[] = ' ul.login_id = "' . $_POST['loginId'] . '"';
}

if ((isset($sWhere) && $sWhere != "") || (count($cWhere) > 0)) {
    $sWhere = ' where ' . $sWhere;
    $sQuery = $sQuery . ' ' . $sWhere . implode(" AND ", $cWhere);
}


if (isset($sOrder) && $sOrder != "") {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' order by ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// die($sQuery);
// echo $sQuery;
$rResult = $db->rawQuery($sQuery);
// print_r($rResult);
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

foreach ($rResult as $aRow) {
    $row = [];
    $row[] = ($aRow['login_id']);
    $row[] = ($aRow['login_attempted_datetime']);
    $row[] = ($aRow['ip_address']);
    $row[] = ($aRow['browser']);
    $row[] = ($aRow['operating_system']);
    $row[] = ($aRow['login_status']);
    $output['aaData'][] = $row;
}

echo json_encode($output);
