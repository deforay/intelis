<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sarr = $general->getSystemConfig();
$gconfig = $general->getGlobalConfig();

$tableName = "form_vl";
$primaryKey = "vl_sample_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
 * you want to insert a non-database field (for example a counter or static image)
 */
$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.last_modified_datetime', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($_SESSION['instanceType'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
} elseif ($sarr['sc_user_type'] == 'standalone') {
     $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
     $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}


/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;
/*
 * Paging
 */
$sLimit = null;
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
$aWhere = '';
$sQuery = "SELECT vl.sample_collection_date,
                    vl.vl_sample_id,
                    vl.last_modified_datetime,
                    vl.sample_code,
                    vl.remote_sample_code,
                    vl.result,
                    b.batch_code,
                    vl.patient_first_name,
                    vl.patient_middle_name,
                    vl.patient_last_name,
                    vl.patient_art_no,
                    vl.facility_id,
                    vl.sample_type,
                    vl.result_status,
                    f.facility_name,
                    f.facility_state,
                    f.facility_district,
                    s.sample_name,
                    ts.status_name FROM form_vl as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    -- LEFT JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id
                    -- LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
                    -- LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

if (!empty($sWhere)) {
     $sWhere = ' WHERE ' . $sWhere;
     if (isset($_POST['samplePackageCode']) && $_POST['samplePackageCode'] != '') {
          $sWhere = $sWhere . ' AND vl.sample_package_code LIKE "%' . $_POST['samplePackageCode'] . '%" OR remote_sample_code LIKE "' . $_POST['samplePackageCode'] . '"';
     }
} else {
     if (isset($_POST['samplePackageCode']) && trim((string) $_POST['samplePackageCode']) != '') {
          $sWhere = ' WHERE ' . $sWhere;
          $sWhere = $sWhere . ' vl.sample_package_code LIKE "%' . $_POST['samplePackageCode'] . '%" OR remote_sample_code LIKE "' . $_POST['samplePackageCode'] . '"';
     }
}

$sFilter = '';
$sQuery = $sQuery . ' ' . $sWhere;
if (!empty($sOrder)) {
     $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . " ORDER BY " . $sOrder;
}

$rResult = $db->rawQuery($sQuery);

[$rResult, $resultCount] = $general->getQueryResultAndCount($sQuery, null, $sLimit, $sOffset);

/*
 * Output
 */
$output = array(
     "sEcho" => intval($_POST['sEcho']),
     "iTotalRecords" => $resultCount,
     "iTotalDisplayRecords" => $resultCount,
     "aaData" => []
);

foreach ($rResult as $aRow) {

     $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '', true);
     $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true);

     $patientFname = $aRow['patient_first_name'];
     $patientMname = $aRow['patient_middle_name'];
     $patientLname = $aRow['patient_last_name'];

     $row = [];
     $row[] = $aRow['sample_code'];
     if ($_SESSION['instanceType'] != 'standalone') {
          $row[] = $aRow['remote_sample_code'];
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
     $row[] = $aRow['last_modified_datetime'];
     $row[] = ($aRow['status_name']);
     $output['aaData'][] = $row;
}
echo json_encode($output);
