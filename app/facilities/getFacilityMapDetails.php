<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$tableName = "testing_lab_health_facilities_map";
$primaryKey = "facility_map_id";
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

$aColumns = array('fd.facility_name');

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

$sWhere = "";
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
    $sWhere .= $sWhereSub;
}

/* Individual column filtering */
for ($i = 0; $i < count($aColumns); $i++) {
    if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
        if ($sWhere == "") {
            $sWhere .= $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
        } else {
            $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
        }
    }
}

/*
         * SQL queries
         * Get data to display
        */

$sQuery = "SELECT vlfm.*,fd.facility_name,GROUP_CONCAT(DISTINCT fds.facility_name ORDER BY fds.facility_name ASC SEPARATOR ',') as healthCenterName FROM testing_lab_health_facilities_map as vlfm JOIN facility_details as fd ON fd.facility_id=vlfm.vl_lab_id JOIN facility_details as fds ON fds.facility_id=vlfm.facility_id";

if (isset($sWhere) && !empty($sWhere)) {
    $sWhere = ' where ' . $sWhere;
    $sQuery = $sQuery . ' ' . $sWhere;
}
$sQuery = $sQuery . " group by vl_lab_id";
if (isset($sOrder) && !empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' order by ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//die($sQuery);
//echo $sQuery;die;
$rResult = $db->rawQuery($sQuery);
// print_r($rResult);
/* Data set length after filtering */

$aResultFilterTotal = $db->rawQuery("SELECT vlfm.*,fd.facility_name,GROUP_CONCAT(DISTINCT fds.facility_name ORDER BY fds.facility_name ASC SEPARATOR ',') as healthCenterName FROM testing_lab_health_facilities_map as vlfm JOIN facility_details as fd ON fd.facility_id=vlfm.vl_lab_id JOIN facility_details as fds ON fds.facility_id=vlfm.facility_id $sWhere  group by vl_lab_id order by $sOrder");
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal =  $db->rawQuery("SELECT vlfm.*,fd.facility_name,GROUP_CONCAT(DISTINCT fds.facility_name ORDER BY fds.facility_name ASC SEPARATOR ',') as healthCenterName FROM testing_lab_health_facilities_map as vlfm JOIN facility_details as fd ON fd.facility_id=vlfm.vl_lab_id JOIN facility_details as fds ON fds.facility_id=vlfm.facility_id group by vl_lab_id");
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = count($aResultTotal);

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
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['healthCenterName']);
    if (isset($_SESSION['privileges']) && in_array("editVlFacilityMap.php", $_SESSION['privileges']) && (($sarr['sc_user_type'] == 'remoteuser') || ($sarr['sc_user_type'] == 'standalone'))) {
        $row[] = '<a href="editVlFacilityMap.php?id=' . base64_encode($aRow['vl_lab_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="Edit"><em class="fa-solid fa-pen-to-square"></em> Edit</em></a>';
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
