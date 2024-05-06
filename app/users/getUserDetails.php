<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tableName = "user_details";
$primaryKey = "user_id";



$aColumns = array('ud.user_name', 'ud.login_id', 'ud.email', 'r.role_name', 'ud.status');

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;
/*
         * Paging
         */
$sOffset = $sLimit = null;
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
    for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
        if ($_POST['bSortable_' . (int) $_POST['iSortCol_' . $i]] == "true") {
            $sOrder .= $aColumns[(int) $_POST['iSortCol_' . $i]] . "
				" . ($_POST['sSortDir_' . $i]) . ", ";
        }
    }
    $sOrder = substr_replace($sOrder, "", -2);
}



$sWhere = [];
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchArray = explode(" ", (string) $_POST['sSearch']);
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
    $sWhere[] = $sWhereSub;
}



/*
         * SQL queries
         * Get data to display
        */
$sQuery = "SELECT SQL_CALC_FOUND_ROWS ud.user_id,
                            ud.user_name,
                            ud.login_id,
                            ud.interface_user_name,
                            ud.email,
                            ud.status,
                            r.role_name
                            FROM user_details as ud
                            LEFT JOIN roles as r ON ud.role_id=r.role_id ";

if (!empty($sWhere)) {
    $sWhere = ' where ' . implode(' AND ', $sWhere);
} else {
    $sWhere = "";
}
$sQuery = $sQuery . ' ' . $sWhere;
if (!empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//die($sQuery);
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
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => []
);



foreach ($rResult as $aRow) {

    if (!empty($aRow['interface_user_name'])) {
        $interfaceUsers = implode(", ", json_decode((string) $aRow['interface_user_name'], true));
        $aRow['user_name'] = $aRow['user_name'] . "<br><small>[" . $interfaceUsers . "]</small>";
    }
    $row = [];
    $row[] = ($aRow['user_name']);
    $row[] = ($aRow['login_id']);
    $row[] = $aRow['email'];
    $row[] = ($aRow['role_name']);
    $row[] = ($aRow['status']);
    if (_isAllowed("/users/editUser.php")) {
        $row[] = '<a href="editUser.php?id=' . base64_encode((string) $aRow['user_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
