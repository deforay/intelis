<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tableName = "qc_covid19";
$primaryKey = "qc_id";
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
/* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */

$aColumns = array('qc_code', 'testkit_name', 'lot_no', 'expiry_date', 'facility_name', 'user_name', 'qc_tested_datetime', 'qc.updated_datetime');

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

$sWhere = [];
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
$sQuery = "SELECT SQL_CALC_FOUND_ROWS qc.*, kit.testkit_name, l_f.facility_name, u_d.user_name FROM $tableName as qc 
            INNER JOIN r_covid19_qc_testkits as kit ON kit.testkit_id=qc.testkit 
            LEFT JOIN user_details as u_d ON u_d.user_id=qc.tested_by 
            LEFT JOIN facility_details as l_f ON qc.lab_id=l_f.facility_id";

if (isset($sWhere) && !empty($sWhere)) {
    $sQuery = $sQuery . ' where ' . implode(' AND ', $sWhere);
}

if (isset($sOrder) && !empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
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
$output = array(
    "sEcho" => intval($_POST['sEcho']),
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => array()
);

foreach ($rResult as $aRow) {

    $row = [];

    $row[] = $aRow['qc_code'];
    $row[] = ($aRow['testkit_name']);
    $row[] = $aRow['lot_no'];
    $row[] = date("d-M-Y", strtotime($aRow['expiry_date']));
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['user_name']);
    $row[] = date("d-m-Y H:i:s", strtotime($aRow['qc_tested_datetime']));
    $row[] = date("d-m-Y H:i:s", strtotime($aRow['updated_datetime']));
    if (isset($_SESSION['privileges']) && in_array("edit-covid-19-qc-data.php", $_SESSION['privileges'])) {
        $edit = '<a href="edit-covid-19-qc-data.php?id=' . base64_encode($aRow['qc_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _("Edit") . '</em></a>';
        $row[] = $edit;
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
