<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sarr = $general->getSystemConfig();
$gconfig = $general->getGlobalConfig();


// Clear the query count cache for this manifest
if (session_status() === PHP_SESSION_ACTIVE && !empty($_POST['manifestCode'])) {
     unset($_SESSION['queryCounters']);
}


$tableName = "form_generic";
$primaryKey = "sample_id";

$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.last_modified_datetime', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($general->isSTSInstance()) {
     $sampleCode = 'remote_sample_code';
} elseif ($general->isStandaloneInstance()) {
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




$aWhere = '';
$sQuery = "SELECT SQL_CALC_FOUND_ROWS *, ts.status_name FROM form_generic as vl
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN r_generic_sample_types as s ON s.sample_type_id=vl.specimen_type
          INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
          LEFT JOIN r_generic_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
          LEFT JOIN r_generic_test_reasons as tr ON tr.test_reason_id=vl.reason_for_testing
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

if (!empty($sWhere)) {
     $sWhere = ' WHERE ' . $sWhere;
     if (isset($_POST['manifestCode']) && $_POST['manifestCode'] != '') {
          $manifestCode = $_POST['manifestCode'];

          $sWhere = $sWhere . " AND vl.sample_package_code IN
                    (
                        '$manifestCode',
                        (SELECT DISTINCT sample_package_code FROM form_generic WHERE remote_sample_code LIKE '$manifestCode')
                    )";
     }
} else {
     $manifestCode = $_POST['manifestCode'];
     if (isset($_POST['manifestCode']) && trim((string) $_POST['manifestCode']) != '') {
          $manifestCode = $_POST['manifestCode'];

          $sWhere = ' WHERE ' . $sWhere;
          $sWhere = $sWhere . " vl.sample_package_code IN
                    (
                        '$manifestCode',
                        (SELECT DISTINCT sample_package_code FROM form_generic WHERE remote_sample_code LIKE '$manifestCode')
                    )";
     }
}
$sFilter = '';
$sQuery = $sQuery . ' ' . $sWhere;
if (!empty($sOrder) && $sOrder !== '') {
     $sOrder = preg_replace('/\s+/', ' ', $sOrder);
     $sQuery = $sQuery . " ORDER BY " . $sOrder;
}
if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// die($sQuery);
$rResult = $db->rawQuery($sQuery);

/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];


$output = array(
     "sEcho" => (int) $_POST['sEcho'],
     "iTotalRecords" => $iTotal,
     "iTotalDisplayRecords" => $iFilteredTotal,
     "aaData" => []
);

foreach ($rResult as $aRow) {

     $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
     $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');

     $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_id']));
     $patientMname = ($general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_id']));
     $patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_id']));

     $row = [];
     $row[] = $aRow['sample_code'];
     if (!$general->isStandaloneInstance()) {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = $aRow['sample_collection_date'];
     $row[] = $aRow['batch_code'];
     $row[] = $aRow['patient_id'];
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
