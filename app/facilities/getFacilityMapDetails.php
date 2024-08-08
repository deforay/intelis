<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

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

$aColumns = array('fd.facility_name');

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




$sQuery = "SELECT vlfm.*,fd.facility_name,GROUP_CONCAT(DISTINCT fds.facility_name ORDER BY fds.facility_name ASC SEPARATOR ',') as healthCenterName FROM testing_lab_health_facilities_map as vlfm JOIN facility_details as fd ON fd.facility_id=vlfm.vl_lab_id JOIN facility_details as fds ON fds.facility_id=vlfm.facility_id";

if (!empty($sWhere)) {
    $sWhere = ' WHERE ' . $sWhere;
    $sQuery = $sQuery . ' ' . $sWhere;
}
$sQuery = $sQuery . " group by vl_lab_id";
if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

[$rResult, $resultCount] = $db->getQueryResultAndCount($sQuery);

/*
 * Output
 */
$output = array(
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $resultCount,
    "iTotalDisplayRecords" => $resultCount,
    "aaData" => []
);



foreach ($rResult as $aRow) {
    $row = [];
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['healthCenterName']);
    if (_isAllowed("editVlFacilityMap.php") && (($general->isSTSInstance()) || ($general->isStandaloneInstance()))) {
        $row[] = '<a href="editVlFacilityMap.php?id=' . base64_encode((string) $aRow['vl_lab_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="Edit"><em class="fa-solid fa-pen-to-square"></em> Edit</em></a>';
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
