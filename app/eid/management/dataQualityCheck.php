<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$gconfig = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();
$key = (string) $general->getGlobalConfig('key');

$tableName = "form_eid";
$primaryKey = "eid_id";


$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.child_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.child_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'ts.status_name');
if ($general->isStandaloneInstance()) {
     $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
     $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}

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
     for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
          if ($_POST['bSortable_' . (int) $_POST['iSortCol_' . $i]] == "true") {
               $sOrder .= $orderColumns[(int) $_POST['iSortCol_' . $i]] . "
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




$sQuery = "SELECT SQL_CALC_FOUND_ROWS * FROM form_eid as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_eid_sample_type as s ON s.sample_id=vl.specimen_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

if (!empty($_POST['sampleCollectionDate'])) {
     if (trim((string) $start_date) == trim((string) $end_date)) {
          $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
     } else {
          $sWhere[] =  ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}
if (isset($_POST['formField']) && trim((string) $_POST['formField']) != '') {
     $sWhereSubC = "  (";
     $sWhereSub = '';
     $searchArray = explode(",", (string) $_POST['formField']);
     foreach ($searchArray as $search) {
          if ($sWhereSub == "") {
               $sWhereSub .= $sWhereSubC;
               $sWhereSub .= "(";
          } else {
               $sWhereSub .= " AND (";
          }
          if ($search == 'sample_collection_date')
               $sWhereSub .=  'vl.' . $search . " IS NULL";
          else
               $sWhereSub .= 'vl.' . $search . " ='' OR " . 'vl.' . $search . " IS NULL";
          $sWhereSub .= ")";
     }
     $sWhereSub .= ")";
     $sWhere[] = $sWhereSub;
}
//echo $sWhereSub; die;
$dWhere = '';
if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
     $dWhere = $dWhere . " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}

if (!empty($sWhere)) {
     $sWhere = ' WHERE ' . implode(' AND ', $sWhere);
} else {
     $sWhere = "";
}

$sQuery = $sQuery . ' ' . $sWhere;

$_SESSION['vlIncompleteForm'] = $sQuery;
if (!empty($sOrder) && $sOrder !== '') {
     $sOrder = preg_replace('/\s+/', ' ', $sOrder);
     $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */
$aResultFilterTotal = $db->rawQuery("SELECT vl.eid_id,vl.facility_id,vl.child_name,vl.result,f.facility_name,f.facility_code,s.sample_name,b.batch_code,vl.sample_batch_id,ts.status_name FROM form_eid as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_eid_sample_type as s ON s.sample_id=vl.specimen_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id $sWhere ORDER BY vl.last_modified_datetime DESC, $sOrder");
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal =  $db->rawQuery("select COUNT(eid_id) as total FROM form_eid as vl where vlsm_country_id='" . $gconfig['vl_form'] . "' $dWhere");
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = $aResultTotal[0]['total'];
$_SESSION['vlIncompleteFormCount'] = $iTotal;

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
     $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');

     if ($aRow['remote_sample'] == 'yes') {
          $decrypt = 'remote_sample_code';
     } else {
          $decrypt = 'sample_code';
     }

     $childName = ($general->crypto('doNothing', $aRow['child_name'], $aRow[$decrypt]));

     $row = [];
     $row[] = $aRow['sample_code'];
     if (!$general->isStandaloneInstance()) {
          $row[] = $aRow['remote_sample_code'];
     }
     if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
          $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
          $childName = $general->crypto('decrypt', $childName, $key);
     }
     $row[] = $aRow['sample_collection_date'];
     $row[] = $aRow['batch_code'];
     $row[] = ($childName);
     $row[] = ($aRow['facility_name']);
     $row[] = ($aRow['facility_state']);
     $row[] = ($aRow['facility_district']);
     $row[] = ($aRow['sample_name']);
     $row[] = $aRow['result'];
     $row[] = ($aRow['status_name']);
     $output['aaData'][] = $row;
}

echo json_encode($output);
