<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;


$tableName = "r_vl_test_failure_reasons";
$primaryKey = "failure_id";


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$aColumns = array('failure_reason', 'status');

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
    $status = '<select class="form-control" name="status[]" id="' . $aRow['failure_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,\'' . $aRow['status'] . '\')">
               <option value="active" ' . ($aRow['status'] == "active" ? "selected=selected" : "") . '>' . _translate("Active") . '</option>
               <option value="inactive" ' . ($aRow['status'] == "inactive" ? "selected=selected" : "") . '>' . _translate("Inactive") . '</option>
               </select><br><br>';
    $row = [];
    $row[] = ($aRow['failure_reason']);
    if (_isAllowed("vl-art-code-details.php") && $general->isLISInstance() === false) {
        $row[] = $status;
    } else {
        $row[] = ($aRow['status']);
    }
    $row[] = '<a href="edit-vl-test-failure-reason.php?id=' . base64_encode((string) $aRow['failure_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
    $output['aaData'][] = $row;
}

echo json_encode($output);
