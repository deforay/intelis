<?php



$tableName = "qc_covid19";
$primaryKey = "qc_id";

$aColumns = ['qc_code', 'testkit_name', 'lot_no', 'expiry_date', 'facility_name', 'user_name', 'qc_tested_datetime', 'qc.updated_datetime'];

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



$sWhere = [];
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
    $sWhere[] = $sWhereSub;
}



/*
    * SQL queries
    * Get data to display
*/
$sQuery = "SELECT SQL_CALC_FOUND_ROWS qc.*, kit.testkit_name, l_f.facility_name, u_d.user_name FROM $tableName as qc
            INNER JOIN r_covid19_qc_testkits as kit ON kit.testkit_id=qc.testkit
            LEFT JOIN user_details as u_d ON u_d.user_id=qc.tested_by
            LEFT JOIN facility_details as l_f ON qc.lab_id=l_f.facility_id";

if ($sWhere !== []) {
    $sQuery = $sQuery . ' where ' . implode(' AND ', $sWhere);
}

if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//die($sQuery);
$rResult = $db->rawQuery($sQuery);

$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

/*
    * Output
*/
$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

foreach ($rResult as $aRow) {

    $row = [];

    $row[] = $aRow['qc_code'];
    $row[] = ($aRow['testkit_name']);
    $row[] = $aRow['lot_no'];
    $row[] = date("d-M-Y", strtotime((string) $aRow['expiry_date']));
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['user_name']);
    $row[] = date("d-m-Y H:i:s", strtotime((string) $aRow['qc_tested_datetime']));
    $row[] = date("d-m-Y H:i:s", strtotime((string) $aRow['updated_datetime']));
    if (_isAllowed("edit-covid-19-qc-data.php")) {
        $edit = '<a href="edit-covid-19-qc-data.php?id=' . base64_encode((string) $aRow['qc_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
        $row[] = $edit;
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
