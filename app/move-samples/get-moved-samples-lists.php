<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;





$tableName = "move_samples";
$primaryKey = "move_sample_id";

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$aColumns = ['lff.facility_name', "DATE_FORMAT(b.list_request_created_datetime,'%d-%b-%Y %H:%i:%s')"];
$orderColumns = ['lff.facility_name', '', '', '', '', '', 'b.list_request_created_datetime'];

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

            $sOrder .= $orderColumns[(int) $_POST['iSortCol_' . $i]] . "
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




$sQuery = "select ms.*,lff.facility_name as labNameFrom,lft.facility_name as labNameTo,count(msm.test_type_sample_id) as sample_code from move_samples as ms inner join move_samples_map msm on msm.move_sample_id = ms.move_sample_id LEFT JOIN facility_details as lff ON ms.moved_from_lab_id=lff.facility_id LEFT JOIN facility_details as lft ON ms.moved_to_lab_id=lft.facility_id";
if ($sWhere !== '' && $sWhere !== '0') {
    $sWhere = ' WHERE ' . $sWhere;
}
$sQuery = $sQuery . ' ' . $sWhere;
$sQuery .= ' group by ms.move_sample_id';
if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
$rResult = $db->rawQuery($sQuery);

/* Data set length after filtering */
$aResultFilterTotal = $db->rawQuery("select ms.*,lff.facility_name as labNameFrom,lft.facility_name as labNameTo,count(msm.test_type_sample_id) as sample_code from move_samples as ms inner join move_samples_map msm on msm.move_sample_id = ms.move_sample_id LEFT JOIN facility_details as lff ON ms.moved_from_lab_id=lff.facility_id LEFT JOIN facility_details as lft ON ms.moved_to_lab_id=lft.facility_id $sWhere group by ms.move_sample_id order by $sOrder");
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal = $db->rawQuery("select ms.*,lff.facility_name as labNameFrom,lft.facility_name as labNameTo,count(msm.test_type_sample_id) as sample_code from move_samples as ms inner join move_samples_map msm on msm.move_sample_id = ms.move_sample_id LEFT JOIN facility_details as lff ON ms.moved_from_lab_id=lff.facility_id LEFT JOIN facility_details as lft ON ms.moved_to_lab_id=lft.facility_id group by ms.move_sample_id");
$iTotal = count($aResultTotal);

$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

foreach ($rResult as $aRow) {
    $humanDate = "";
    if (trim((string) $aRow['list_request_created_datetime']) !== "" && $aRow['list_request_created_datetime'] != '0000-00-00 00:00:00') {
        $date = $aRow['list_request_created_datetime'];
        $humanDate = date("d-M-Y H:i:s", strtotime((string) $date));
    }

    $row = [];
    $date = '';
    if ($aRow['moved_on'] != '0000-00-00' && $aRow['moved_on'] != null) {
        $date = DateUtility::humanReadableDateFormat($aRow['moved_on']);
    }
    $row[] = ($aRow['labNameFrom']);
    $row[] = ($aRow['labNameTo']);
    $row[] = $date;
    $row[] = $aRow['reason_for_moving'];
    $row[] = $aRow['move_approved_by'];
    $row[] = $humanDate;
    $output['aaData'][] = $row;
}
echo json_encode($output);
