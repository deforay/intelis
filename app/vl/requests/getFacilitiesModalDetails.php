<?php

use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tableName = "facility_details";
$primaryKey = "facility_id";



$aColumns = ['facility_id', 'facility_code', 'facility_name', 'facility_type_name'];

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




$sQuery = "SELECT * FROM facility_details as f_d LEFT JOIN facility_type as f_t ON f_t.facility_type_id=f_d.facility_type";

if ($sWhere !== '' && $sWhere !== '0') {
    $sWhere = ' WHERE ' . $sWhere;
    $sWhere .= ' AND status = "active"';
    if (isset($_POST['hub']) && trim((string) $_POST['hub']) !== '') {
        $sWhere = $sWhere . " AND f_d.facility_hub_name LIKE '%" . $_POST['hub'] . "%' ";
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $sWhere = $sWhere . " AND f_d.facility_district LIKE '%" . $_POST['district'] . "%' ";
    }
    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $sWhere = $sWhere . " AND f_d.facility_state LIKE '%" . $_POST['state'] . "%' ";
    }
    if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
        $sWhere = $sWhere . " AND f_t.facility_type_id='" . $_POST['facilityName'] . "'";
    }
    $sQuery = $sQuery . ' ' . $sWhere;
} else {
    $sWhere = ' where status = "active"';
    if (isset($_POST['hub']) && trim((string) $_POST['hub']) !== '') {
        $sWhere = $sWhere . " AND f_d.facility_hub_name LIKE '%" . $_POST['hub'] . "%' ";
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $sWhere = $sWhere . " AND f_d.facility_district LIKE '%" . $_POST['district'] . "%' ";
    }
    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $sWhere = $sWhere . " AND f_d.facility_state LIKE '%" . $_POST['state'] . "%' ";
    }
    if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
        $sWhere = $sWhere . " AND f_t.facility_type_id='" . $_POST['facilityName'] . "'";
    }
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

$aResultFilterTotal = $db->rawQuery("SELECT * FROM facility_details as f_d LEFT JOIN facility_type as f_t ON f_t.facility_type_id=f_d.facility_type $sWhere order by $sOrder");
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
if ($_POST['type'] == 'all') {
    $aResultTotal = $db->rawQuery("select COUNT(facility_id) as total FROM facility_details WHERE status = 'active'");
} else {
    $aResultTotal = $db->rawQuery("select COUNT(facility_id) as total FROM facility_details WHERE status = 'active' AND facility_type=2");
}
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = $aResultTotal[0]['total'];


$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

foreach ($rResult as $aRow) {
    $facilityDetails = $aRow['facility_id'] . "##" . $aRow['facility_name'] . "##" . $aRow['facility_state'] . "##" . $aRow['facility_hub_name'] . "##" . $aRow['contact_person'] . "##" . $aRow['facility_mobile_numbers'] . "##" . $aRow['facility_district'] . "##" . $aRow['facility_emails'];
    $row = [];
    if ($_POST['type'] == 'all') {
        $row[] = '<input type="radio" id="facility' . $aRow['facility_id'] . '" name="facility" value="' . $facilityDetails . '" onclick="getFacility(this.value);">';
    } else {
        $row[] = '<input type="radio" id="facility' . $aRow['facility_id'] . '" name="facility" value="' . $facilityDetails . '" onclick="getFacilityLab(this.value);">';
    }
    $row[] = $aRow['facility_code'];
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['facility_type_name']);
    $output['aaData'][] = $row;
}
echo json_encode($output);
