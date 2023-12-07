<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
     session_start();
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$gconfig = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

$tableName = "form_vl";
$primaryKey = "vl_sample_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'ts.status_name');
if ($sarr['sc_user_type'] == 'standalone') {
     $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
     $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}

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

/*
* Ordering
*/

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

/*
          * SQL queries
          * Get data to display
          */
$aWhere = '';
$sQuery = "SELECT SQL_CALC_FOUND_ROWS  * FROM form_vl as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

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
          if ($search == 'sample_collection_date') {
               $sWhereSub .=  'vl.' . $search . " IS NULL";
          } else {
               $sWhereSub .= 'vl.' . $search . " ='' OR " . 'vl.' . $search . " IS NULL";
          }
          $sWhereSub .= ")";
     }
     $sWhereSub .= ")";
     $sWhere[] = $sWhereSub;
}


if ($_SESSION['instanceType'] == 'remoteuser' && !empty($_SESSION['facilityMap'])) {
     $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}

if (!empty($sWhere)) {
     $sWhere = ' where ' . implode(" AND ", $sWhere);
}

$sQuery = $sQuery . ' ' . $sWhere;

$_SESSION['vlIncompleteForm'] = $sQuery;
if (!empty($sOrder)) {
     $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . ' order by ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

$rResult = $db->rawQuery($sQuery);

$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
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


     $patientFname = $aRow['patient_first_name'];
     $patientMname = $aRow['patient_middle_name'];
     $patientLname = $aRow['patient_last_name'];
     $row = [];
     $row[] = $aRow['sample_code'];
     if ($_SESSION['instanceType'] != 'standalone') {
          $row[] = $aRow['remote_sample_code'];
     }
     if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
          $key = (string) $general->getGlobalConfig('key');
          $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
          $patientFname = $general->crypto('decrypt', $patientFname, $key);
          $patientMname = $general->crypto('decrypt', $patientMname, $key);
          $patientLname = $general->crypto('decrypt', $patientLname, $key);
     }
     $row[] = $aRow['sample_collection_date'];
     $row[] = $aRow['batch_code'];
     $row[] = $aRow['patient_art_no'];
     $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
     $row[] = ($aRow['facility_name']);
     $row[] = ($aRow['facility_state']);
     $row[] = ($aRow['facility_district']);
     $row[] = ($aRow['sample_name']);
     $row[] = $aRow['result'];
     $row[] = ($aRow['status_name']);
     $output['aaData'][] = $row;
}

echo json_encode($output);
