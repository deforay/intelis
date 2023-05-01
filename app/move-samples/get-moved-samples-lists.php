<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
  


$tableName = "move_samples";
$primaryKey = "move_sample_id";

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
/* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */

$aColumns = array('lff.facility_name', "DATE_FORMAT(b.list_request_created_datetime,'%d-%b-%Y %H:%i:%s')");
$orderColumns = array('lff.facility_name', '', '', '', '', '', 'b.list_request_created_datetime');

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

            $sOrder .= $orderColumns[intval($_POST['iSortCol_' . $i])] . "
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

$sQuery = "select ms.*,lff.facility_name as labNameFrom,lft.facility_name as labNameTo,count(msm.test_type_sample_id) as sample_code from move_samples as ms inner join move_samples_map msm on msm.move_sample_id = ms.move_sample_id LEFT JOIN facility_details as lff ON ms.moved_from_lab_id=lff.facility_id LEFT JOIN facility_details as lft ON ms.moved_to_lab_id=lft.facility_id";
if (isset($sWhere) && $sWhere != "") {
    $sWhere = ' where ' . $sWhere;
}
$sQuery = $sQuery . ' ' . $sWhere;
$sQuery = $sQuery . ' group by ms.move_sample_id';
if (isset($sOrder) && $sOrder != "") {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' order by ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
$rResult = $db->rawQuery($sQuery);

/* Data set length after filtering */
$aResultFilterTotal = $db->rawQuery("select ms.*,lff.facility_name as labNameFrom,lft.facility_name as labNameTo,count(msm.test_type_sample_id) as sample_code from move_samples as ms inner join move_samples_map msm on msm.move_sample_id = ms.move_sample_id LEFT JOIN facility_details as lff ON ms.moved_from_lab_id=lff.facility_id LEFT JOIN facility_details as lft ON ms.moved_to_lab_id=lft.facility_id $sWhere group by ms.move_sample_id order by $sOrder");
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal =  $db->rawQuery("select ms.*,lff.facility_name as labNameFrom,lft.facility_name as labNameTo,count(msm.test_type_sample_id) as sample_code from move_samples as ms inner join move_samples_map msm on msm.move_sample_id = ms.move_sample_id LEFT JOIN facility_details as lff ON ms.moved_from_lab_id=lff.facility_id LEFT JOIN facility_details as lft ON ms.moved_to_lab_id=lft.facility_id group by ms.move_sample_id");
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
    $humanDate = "";
    if (trim($aRow['list_request_created_datetime']) != "" && $aRow['list_request_created_datetime'] != '0000-00-00 00:00:00') {
        $date = $aRow['list_request_created_datetime'];
        $humanDate =  date("d-M-Y H:i:s", strtotime($date));
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
