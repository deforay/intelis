<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tableName = "r_implementation_partners";
$primaryKey = "i_partner_id";

$aColumns = array('i_partner_name', 'i_partner_status');

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

/*
 * Filtering
 * NOTE this does not match the built-in DataTables filtering which does it
 * word by word on any field. It's possible to do here, but concerned about efficiency
 * on very large tables, and MySQL's regex functionality is very limited
 */

$sWhere = "";
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
    $sWhere .= $sWhereSub;
}


/*
 * SQL queries
 * Get data to display
 */

$sQuery = "SELECT * FROM $tableName";

if (!empty($sWhere)) {
    $sWhere = ' WHERE ' . $sWhere;
    $sQuery = $sQuery . ' ' . $sWhere;
}

if (!empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' order by ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//die($sQuery);
// echo $sQuery;
$rResult = $db->rawQuery($sQuery);
// print_r($rResult);
/* Data set length after filtering */

$aResultFilterTotal = $db->rawQuery("SELECT * FROM $tableName $sWhere order by $sOrder");
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal = $db->rawQuery("select COUNT($primaryKey) as total FROM $tableName");
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = $aResultTotal[0]['total'];

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
    $status = '<select class="form-control" name="status[]" id="' . $aRow['i_partner_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,\'' . $aRow['i_partner_status'] . '\')">
               <option value="active" ' . ($aRow['i_partner_status'] == "active" ? "selected=selected" : "") . '>' . _translate("Active") . '</option>
               <option value="inactive" ' . ($aRow['i_partner_status'] == "inactive" ? "selected=selected" : "") . '>' . _translate("Inactive") . '</option>
               </select><br><br>';
    $row = [];
    $row[] = ($aRow['i_partner_name']);
    if (_isAllowed("/common/reference/add-implementation-partners.php") && $_SESSION['instanceType'] != 'vluser') {
        $row[] = $status;
    } else {
        $row[] = $aRow['i_partner_status'];
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
