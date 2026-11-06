<?php

use App\Utilities\DataTableUtility;


$tableName = "instruments";
$primaryKey = "instrument_id";



// Qualified: facility_details has a status column too. In the order of the list's columns.
$aColumns = ['i.machine_name', 'f.facility_name', 'i.status'];



$sTable = $tableName;
/*
 * Paging
 */
[$sOffset, $sLimit] = DataTableUtility::paging($_POST);



$sOrder = "";
if (isset($_POST['iSortCol_0'])) {
    $sOrder = "";
    for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
        if ($_POST['bSortable_' . (int) $_POST['iSortCol_' . $i]] == "true") {
            $sOrder .= $aColumns[(int) $_POST['iSortCol_' . $i]] . "
				 	" . (strtolower(trim((string) ($_POST['sSortDir_' . $i] ?? ''))) === 'desc' ? 'DESC' : 'ASC') . ", ";
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
                $sWhereSub .= $aColumns[$i] . " LIKE '%" . $db->escape($search) . "%' OR ";
            } else {
                $sWhereSub .= $aColumns[$i] . " LIKE '%" . $db->escape($search) . "%' ";
            }
        }
        $sWhereSub .= ")";
    }
    $sWhere .= $sWhereSub;
}




// Lab scope (instruments): LIS = own lab + unassigned; cloud-LIS = strictly own
// lab (fail closed); STS = all. AJAX endpoint -- bypasses ACL, so it self-guards.
$generalSvc = \App\Registries\ContainerRegistry::get(\App\Services\CommonService::class);
$instrLabScope = $generalSvc->labAdminScopeWhere('lab_id', 'i');

// An instrument with no id cannot be opened, so it is not listed.
$whereParts = ["COALESCE(i.instrument_id, '') != ''"];
if ($sWhere !== '' && $sWhere !== '0') {
    $whereParts[] = $sWhere;
}
if ($instrLabScope !== '') {
    $whereParts[] = $instrLabScope;
}
$sWhere = empty($whereParts) ? '' : ' WHERE ' . implode(' AND ', $whereParts);

// The lab, so instruments of the same name in different labs can be told apart.
$sFrom = "FROM instruments AS i LEFT JOIN facility_details AS f ON f.facility_id = i.lab_id";
$sQuery = "SELECT i.instrument_id, i.machine_name, i.status, f.facility_name $sFrom" . $sWhere;

// The last added first, unless a column is sorted; the name settles ties.
if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
} else {
    $sQuery .= ' ORDER BY i.added_on DESC, i.machine_name ASC';
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//die($sQuery);
// echo $sQuery;
$rResult = $db->rawQuery($sQuery);
// print_r($rResult);
/* Data set length after filtering */

$iFilteredTotal = (int) ($db->rawQueryOne("SELECT COUNT(*) AS c $sFrom $sWhere")['c'] ?? 0);

/* Total data set length */
$iTotal = (int) ($db->rawQueryOne("SELECT COUNT(*) AS c FROM instruments AS i WHERE COALESCE(i.instrument_id, '') != ''" . ($instrLabScope !== '' ? " AND $instrLabScope" : ""))['c'] ?? 0);
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);

$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

foreach ($rResult as $aRow) {
    $row = [];
    $row[] = ($aRow['machine_name']);
    $row[] = ($aRow['facility_name'] ?? '') !== '' ? $aRow['facility_name'] : _translate('Not assigned to a lab');
    $row[] = ($aRow['status']);
    $row[] = '<a href="edit-instrument.php?id=' . base64_encode((string) $aRow['instrument_id']) . '" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
    $output['aaData'][] = $row;
}
echo json_encode($output);
