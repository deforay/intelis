<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Services\UsersService;
use App\Registries\ContainerRegistry;

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = "r_test_types";
$primaryKey = "test_type_id";



$aColumns = ['test_standard_name', 'test_generic_name', 'test_short_code', 'test_loinc_code', 'test_status'];



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




$sQuery = "SELECT * FROM r_test_types";

if ($sWhere !== '' && $sWhere !== '0') {
    $sWhere = ' WHERE ' . $sWhere;
    $sQuery = $sQuery . ' ' . $sWhere;
}

if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
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

$aResultFilterTotal = $db->rawQuery("SELECT * FROM r_test_types $sWhere order by $sOrder");
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal = $db->rawQuery("SELECT * FROM r_test_types");
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = count($aResultTotal);

$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

foreach ($rResult as $aRow) {
    $row = [];
    $edit = '';
    $clone = '';
    $row[] = ($aRow['test_standard_name']);
    $row[] = ($aRow['test_generic_name']);
    $row[] = ($aRow['test_short_code']);
    $row[] = ($aRow['test_loinc_code']);
    $row[] = ucwords((string) $aRow['test_status']);
    if (_isAllowed("/generic-tests/configuration/edit-test-type.php") && $general->isLISInstance() === false) {
        $edit = '<a href="edit-test-type.php?id=' . base64_encode((string) $aRow['test_type_id']) . '" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
    }
    if (_isAllowed("/generic-tests/configuration/edit-test-type.php") && $general->isLISInstance() === false) {
        $clone = '<a href="clone-test-type.php?id=' . base64_encode((string) $aRow['test_type_id']) . '" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Clone") . '"><em class="fa-solid fa-copy"></em> ' . _translate("Clone") . '</em></a>';
    }
    if (($edit !== '' && $edit !== '0') || $clone !== '' && $clone !== '0') {
        $row[] = $edit . $clone;
    }
    $output['aaData'][] = $row;
}
echo json_encode($output);
