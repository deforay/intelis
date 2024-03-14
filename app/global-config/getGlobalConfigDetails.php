<?php


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */

use App\Registries\AppRegistry;

$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = "global_config";



$aColumns = array('name', 'value');

/* Indexed column (used for fast and accurate table cardinality) */
//$sIndexColumn = $primaryKey;

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

$sQuery = "SELECT SQL_CALC_FOUND_ROWS * FROM global_config";

if (!empty($sWhere)) {
    $sWhere = "WHERE status = 'active' AND " . $sWhere;
    $sQuery = $sQuery . ' ' . $sWhere;
} else {
    $sQuery = $sQuery . " WHERE status = 'active' ";
}

if (isset($_POST['category']) && $_POST['category']) {
    $sQuery = $sQuery . 'AND category like "' . $_POST['category'] . '"';
}

if (!empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' order by ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

$rResult = $db->rawQuery($sQuery);

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
    $row = [];
    $row[] = $aRow['display_name'];
    if ($aRow['display_name'] == 'Patient ART Number Date' && $aRow['value'] == 'no') {
        $aRow['value'] = 'Month and Year';
    } elseif ($aRow['display_name'] == 'Patient ART Number Date' && $aRow['value'] == 'yes') {
        $aRow['value'] = 'Full Date';
    }
    if ($aRow['name'] == 'vl_form' && trim((string) $aRow['value']) != '') {
        $query = "SELECT * FROM s_available_country_forms WHERE vlsm_country_id= ?";
        $formResult = $db->rawQuery($query, [$aRow['value']]);
        $aRow['value'] = $formResult[0]['form_name'];
    }
    $row[] = $aRow['value'];
    $output['aaData'][] = $row;
}
echo json_encode($output);
