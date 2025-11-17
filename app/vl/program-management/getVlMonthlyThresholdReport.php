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

$tableName = "form_vl";
$primaryKey = "vl_sample_id";

$aColumns = ['vl.sample_tested_datetime', 'f.facility_name'];
$orderColumns = ['vl.sample_tested_datetime', 'f.facility_name'];

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




$aWhere = '';
$sQuery = "SELECT DATE_FORMAT(DATE(vl.sample_tested_datetime), '%b-%Y') as monthrange, f.facility_id, f.facility_name, vl.is_sample_rejected,vl.sample_tested_datetime,vl.sample_collection_date, tl.monthly_target,
          SUM(CASE WHEN (is_sample_rejected IS NOT NULL AND is_sample_rejected LIKE 'yes%') THEN 1 ELSE 0 END) as totalRejected,
          SUM(CASE WHEN (sample_tested_datetime IS NULL AND sample_collection_date IS NOT NULL) THEN 1 ELSE 0 END) as totalReceived,
          SUM(CASE WHEN (sample_collection_date IS NOT NULL) THEN 1 ELSE 0 END) as totalCollected
          FROM testing_labs as tl INNER JOIN form_vl as vl ON vl.lab_id=tl.facility_id LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          RIGHT JOIN testing_lab_health_facilities_map as fm ON vl.lab_id=fm.vl_lab_id";

// form_vl
// health_facilities
$start_date = '';
$end_date = '';
if (!empty($_POST['sampleCollectionDate'])) {
     $s_c_date = explode(" to ", (string) $_POST['sampleCollectionDate']);

     if (isset($s_c_date[0]) && trim($s_c_date[0]) !== "") {
          $start_date = trim($s_c_date[0]) . "-01";
     }
     if (isset($s_c_date[1]) && trim($s_c_date[1]) !== "") {
          $end_date = trim($s_c_date[1]) . "-31";
     }
}
$sTestDate = '';
$eTestDate = '';
if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
     $s_t_date = explode("to", (string) $_POST['sampleTestDate']);
     if (isset($s_t_date[0]) && trim($s_t_date[0]) !== "") {
          $sTestDate = DateUtility::isoDateFormat(trim($s_t_date[0]));
     }
     if (isset($s_t_date[1]) && trim($s_t_date[1]) !== "") {
          $eTestDate = DateUtility::isoDateFormat(trim($s_t_date[1]));
     }
}

if ($sWhere !== '' && $sWhere !== '0') {
     $sWhere = ' WHERE ' . $sWhere;
     if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
          if (trim((string) $sTestDate) === trim((string) $eTestDate)) {
               $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
          } else {
               $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
          }
     }
} else {
     if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
          $fac = explode(',', (string) $_POST['facilityName']);
          $out = '';
          $counter = count($fac);
          for ($s = 0; $s < $counter; $s++) {
               $out = $out !== '' && $out !== '0' ? $out . ',"' . $fac[$s] . '"' : '("' . $fac[$s] . '"';
          }
          $out .= ')';
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND vl.lab_id IN ' . $out;
          } else {
               $setWhr = 'where';
               $sWhere = ' WHERE ' . $sWhere;
               $sWhere = $sWhere . ' vl.lab_id IN ' . $out;
          }
     }

     if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
          } else {
               $setWhr = 'where';
               $sWhere = ' WHERE ' . $sWhere;
               $sWhere = $sWhere . ' DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
          }
     }
}
if ($sWhere !== '') {
     $sWhere = $sWhere . ' AND vl.result!="" AND vl.result_status != ' . RECEIVED_AT_CLINIC;
} else {
     $sWhere = $sWhere . ' where vl.result!="" AND vl.result_status != ' . RECEIVED_AT_CLINIC;
}

// HAVING COUNT(c2.sid) >= 2)
$sWhere .= " AND tl.test_type = 'vl'";


$sQuery = $sQuery . ' ' . $sWhere . ' GROUP BY f.facility_id, YEAR(vl.sample_tested_datetime), MONTH(vl.sample_tested_datetime)';
if ($_POST['targetType'] == 1) {
    $sQuery .= ' HAVING tl.monthly_target > SUM(CASE WHEN (sample_collection_date IS NOT NULL) THEN 1 ELSE 0 END) ';
} elseif ($_POST['targetType'] == 2) {
    $sQuery .= ' HAVING tl.monthly_target < SUM(CASE WHEN (sample_collection_date IS NOT NULL) THEN 1 ELSE 0 END) ';
}

$_SESSION['vlMonitoringThresholdReportQuery'] = $sQuery;
$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */

$aResultFilterTotal = $db->rawQuery($sQuery);
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal = $db->rawQuery($sQuery);
$iTotal = count($aResultTotal);


$output = [
    "sEcho" => (int) $_POST['sEcho'],
    // "iTotalRecords" => $cnt,
    // "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => [],
];
//  print_r($sQuery);die;
$cnt = 0;
foreach ($rResult as $rowData) {
     $cnt++;
     $data = [];
     $data[] = ($rowData['facility_name']);
     $data[] = $rowData['monthrange'];
     $data[] = $rowData['totalReceived'];
     $data[] = $rowData['totalRejected'];
     $data[] = $rowData['totalCollected'];
     $data[] = $rowData['monthly_target'];
     $output['aaData'][] = $data;
}
$output['iTotalDisplayRecords'] = $cnt;
$output['iTotalRecords'] = $cnt;
echo json_encode($output);
