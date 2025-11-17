<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "user_login_history";
$primaryKey = "history_id";


$aColumns = ['login_id', 'login_attempted_datetime', 'ip_address', 'browser', 'operating_system', 'login_status'];

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;

$sOffset = $sLimit = null;
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
    $sOffset = $_POST['iDisplayStart'];
    $sLimit = $_POST['iDisplayLength'];
}



$sOrder = "";
if (isset($_POST['iSortCol_0'])) {
    $sOrder = "";
    for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
        if ($_POST['bSortable_' . (int) $_POST['iSortCol_' . $i]] == "true") {
            $sOrder .= $aColumns[(int) $_POST['iSortCol_' . $i]] . "
                             " . ($_POST['sSortDir_' . $i]) . ", ";
        }
    }
    $sOrder = substr_replace($sOrder, "", -2);
}


$sWhere = "";
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchArray = explode(" ", (string) $_POST['sSearch']);
    $sWhereSub = "";
    foreach ($searchArray as $search) {
        if ($sWhereSub === "") {
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


$sQuery = "SELECT ul.history_id,
                    ul.login_id,
                    ul.login_attempted_datetime,
                    ul.login_status,
                    ul.ip_address,
                    ul.browser,
                    ul.operating_system
                FROM user_login_history AS ul";

$start_date = '';
$end_date = '';
$cWhere = [];

if (isset($_POST['userDate']) && trim((string) $_POST['userDate']) !== '') {
    [$start_date, $end_date] = DateUtility::convertDateRange($_POST['userDate'] ?? '');
}

if (isset($_POST['userDate']) && trim((string) $_POST['userDate']) !== '') {
    if (trim((string) $start_date) === trim((string) $end_date)) {
        $cWhere[] = " DATE(ul.login_attempted_datetime) = '$start_date'";
    } else {
        $cWhere[] = " DATE(ul.login_attempted_datetime) BETWEEN '$start_date' AND '$end_date'";
    }
}
// print_r($cWhere);die;

if (isset($_POST['loginId']) && trim((string) $_POST['loginId']) !== '') {
    $cWhere[] = ' ul.login_id = "' . $_POST['loginId'] . '"';
}

if ((isset($sWhere) && $sWhere !== "") || ($cWhere !== [])) {
    $sWhere = " WHERE $sWhere";
    $sQuery = $sQuery . ' ' . $sWhere . implode(" AND ", $cWhere);
}


if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = "$sQuery ORDER BY $sOrder";
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
}

$rResult = $db->rawQuery($sQuery);


[$rResult, $resultCount] = $db->getDataAndCount($sQuery);


$output = [
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $resultCount,
    "iTotalDisplayRecords" => $resultCount,
    "aaData" => []
];

foreach ($rResult as $aRow) {
    $row = [];
    $row[] = $aRow['login_id'];
    $row[] = $aRow['login_attempted_datetime'];
    $row[] = $aRow['ip_address'];
    $row[] = $aRow['browser'];
    $row[] = $aRow['operating_system'];
    $row[] = $aRow['login_status'];
    $output['aaData'][] = $row;
}

echo json_encode($output);
