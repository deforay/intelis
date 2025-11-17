<?php

use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$key = (string) $general->getGlobalConfig('key');

$tableName = "form_vl";
$primaryKey = "vl_sample_id";

$aColumns = ['vl.sample_code', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'ts.status_name'];
$orderColumns = ['vl.sample_code', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 's.sample_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'ts.status_name'];

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

$sWhere = [];
$sWhere[] = " WHERE IFNULL(reason_for_vl_testing, 0)  != 9999 ";
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




$aWhere = '';
$sQuery = "SELECT vl.sample_code,
                    vl.patient_art_no,
                    vl.sample_collection_date,
                    vl.sample_tested_datetime,
                    vl.is_encrypted,
                    s.sample_name,
                    b.batch_code,
                    ts.status_name,
                    f.facility_name,
                    l_f.facility_name as labName,
                    f.facility_code,
                    f.facility_state,
                    f.facility_district,
                    acd.art_code,
                    rs.rejection_reason_name,
                    tr.test_reason_name
                    FROM form_vl as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
                    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    LEFT JOIN r_vl_art_regimen as acd ON acd.art_id=vl.current_regimen
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
                    LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
                    LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing";

[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

if (!empty($_POST['sampleCollectionDate'])) {
     if (trim((string) $start_date) === trim((string) $end_date)) {
          $sWhere[] = '  DATE(vl.sample_collection_date) = "' . $start_date . '"';
     } else {
          $sWhere[] = '  DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}
if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
     $sWhere[] = ' vl.lab_id = "' . $_POST['facilityName'] . '"';
}

if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
     $sWhere[] = " f.facility_district_id LIKE " . $_POST['district'];
}
if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
     $sWhere[] = " f.facility_state_id LIKE " . $_POST['state'];
}

if ($sWhere !== []) {
     $sWhere[] = ' vl.result!="" AND vl.result_status != ' . RECEIVED_AT_CLINIC;
} else {
     $sWhere[] = ' WHERE vl.result!="" AND vl.result_status != ' . RECEIVED_AT_CLINIC;
}

if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}

if ($sWhere !== []) {
     $sWhere = implode(' AND ', $sWhere);
}
$sQuery = $sQuery . ' ' . $sWhere;

$_SESSION['vlMonitoringResultQuery'] = $sQuery;

if (!empty($sOrder) && $sOrder !== '') {
     $sOrder = preg_replace('/\s+/', ' ', $sOrder);
     $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
     $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
}

[$rResult, $resultCount] = $db->getDataAndCount($sQuery);

$output = [
     "sEcho" => (int) $_POST['sEcho'],
     "iTotalRecords" => $resultCount,
     "iTotalDisplayRecords" => $resultCount,
     "aaData" => []
];

foreach ($rResult as $aRow) {

     $patientFname = $aRow['patient_first_name'] ?? '';
     $patientMname = $aRow['patient_middle_name'] ?? '';
     $patientLname = $aRow['patient_last_name'] ?? '';

     $row = [];
     $row[] = $aRow['sample_code'];
     $row[] = $aRow['batch_code'];
     if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
          $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
          $patientFname = $general->crypto('decrypt', $patientFname, $key);
          $patientMname = $general->crypto('decrypt', $patientMname, $key);
          $patientLname = $general->crypto('decrypt', $patientLname, $key);
     }
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
