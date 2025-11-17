<?php



$tableName = "facility_details";
$primaryKey = "facility_id";

$aColumns = ['facility_code', 'facility_name', 'facility_type_name', 'status', 'p.geo_name', 'd.geo_name'];

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


$facilityType = $_POST['facilityType'];
if (isset($facilityType) && trim((string) $facilityType) !== '') {
    $sWhere[] = ' f_t.facility_type_id = "' . $_POST['facilityType'] . '"';
}
if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
    $sWhere[] = " d.geo_id = '" . $_POST['district'] . "' ";
}
if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
    $sWhere[] = " p.geo_id = '" . $_POST['state'] . "' ";
}
$qry = "";
if (isset($_POST['testType']) && trim((string) $_POST['testType']) !== '' && !empty($facilityType)) {
    if ($facilityType == '2') {
        $qry = " LEFT JOIN testing_labs tl ON tl.facility_id=f_d.facility_id";
        $sWhere[] = ' tl.test_type = "' . $_POST['testType'] . '"';
    } else {
        $qry = " LEFT JOIN health_facilities hf ON hf.facility_id=f_d.facility_id";
        $sWhere[] = ' hf.test_type = "' . $_POST['testType'] . '"';
    }
}
if (isset($_POST['activeFacility']) && trim((string) $_POST['activeFacility']) !== '') {
    $sWhere[] = " f_d.status = '" . $_POST['activeFacility'] . "' ";
}
$orphanFacility = $_POST['orphanFacility'] ?? '';
if ($orphanFacility === 'yes') {
    $sWhere[] = "(f_d.status = 'active' AND (p.geo_status IS NULL OR p.geo_status != 'active' OR d.geo_status IS NULL OR d.geo_status != 'active'))";
}

$sQuery = "SELECT SQL_CALC_FOUND_ROWS f_d.*, f_t.*,p.geo_name as province ,d.geo_name as district,
            p.geo_status as province_status, d.geo_status as district_status
            FROM facility_details as f_d
            LEFT JOIN facility_type as f_t ON f_t.facility_type_id=f_d.facility_type
            LEFT JOIN geographical_divisions as p ON f_d.facility_state_id = p.geo_id
            LEFT JOIN geographical_divisions as d ON f_d.facility_district_id = d.geo_id $qry ";

if ($sWhere !== []) {
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
// echo $sQuery;
$rResult = $db->rawQuery($sQuery);
// print_r($rResult);

$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

foreach ($rResult as $aRow) {
    $provinceName = $aRow['province'];
    $districtName = $aRow['district'];
    $provinceStatus = strtolower((string) ($aRow['province_status'] ?? ''));
    $districtStatus = strtolower((string) ($aRow['district_status'] ?? ''));

    if (!empty($provinceName) && $provinceStatus !== 'active') {
        $provinceName .= ' (' . _translate(ucwords($provinceStatus ?: 'missing')) . ')';
    }
    if (!empty($districtName) && $districtStatus !== 'active') {
        $districtName .= ' (' . _translate(ucwords($districtStatus ?: 'missing')) . ')';
    }

    $isOrphan = ($aRow['status'] === 'active') && (
        empty($provinceStatus) || $provinceStatus !== 'active' ||
        empty($districtStatus) || $districtStatus !== 'active'
    );

    $row = [];
    $row[] = $aRow['facility_code'];
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['facility_type_name']);
    $row[] = ($aRow['status']);
    $row[] = $provinceName;
    $row[] = $districtName;
    if (_isAllowed("editFacility.php") && ($general->isSTSInstance() || $general->isStandaloneInstance())) {
        $row[] = '<a href="editFacility.php?id=' . base64_encode((string) $aRow['facility_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
    }
    if ($isOrphan) {
        $row['DT_RowClass'] = 'orphan-facility';
        $row['DT_RowAttr'] = ['data-orphan' => 'yes'];
    } else {
        $row['DT_RowAttr'] = ['data-orphan' => 'no'];
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
