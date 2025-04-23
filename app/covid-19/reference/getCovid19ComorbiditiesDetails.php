<?php



$tableName = "r_covid19_comorbidities";
$primaryKey = "comorbidity_id";

$aColumns = array('comorbidity_name', 'comorbidity_status');

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




$sQuery = "SELECT * FROM r_covid19_comorbidities";

if (!empty($sWhere)) {
    $sWhere = ' where ' . implode(' AND ', $sWhere);
    $sQuery = $sQuery . ' ' . $sWhere;
}

if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

[$rResult, $resultCount] = $db->getQueryResultAndCount($sQuery);


$output = [
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $resultCount,
    "iTotalDisplayRecords" => $resultCount,
    "aaData" => []
];

foreach ($rResult as $aRow) {
    $status = '<select class="form-control" name="status[]" id="' . $aRow['comorbidity_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,\'' . $aRow['comorbidity_status'] . '\')">
               <option value="active" ' . ($aRow['comorbidity_status'] == "active" ? "selected=selected" : "") . '>' . _translate("Active") . '</option>
               <option value="inactive" ' . ($aRow['comorbidity_status'] == "inactive"  ? "selected=selected" : "") . '>' . _translate("Inactive") . '</option>
               </select><br><br>';
    $row = [];
    $row[] = ($aRow['comorbidity_name']);
    if (_isAllowed("covid19-sample-type.php") && $general->isLISInstance() === false) {
        $row[] = $status;
    } else {
        $row[] = $aRow['comorbidity_status'];
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
