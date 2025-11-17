<?php

use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$formId = (int) $general->getGlobalConfig('vl_form');


$tableName = "form_hepatitis";
$primaryKey = "hepatitis_id";

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

$sWhere = [];
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



$sQuery = "SELECT SQL_CALC_FOUND_ROWS DATE_FORMAT(DATE(vl.sample_tested_datetime), '%b-%Y') as monthrange, f.*, vl.*, hf.monthly_target FROM testing_labs as hf INNER JOIN form_hepatitis as vl ON vl.lab_id=hf.facility_id LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id  ";

[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
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


if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
     if (trim((string) $sTestDate) === trim((string) $eTestDate)) {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
     }
}

if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
     $fac = explode(',', (string) $_POST['facilityName']);
     $out = '';
     //  print_r($fac);
     /// die;
     $counter = count($fac);
     //  print_r($fac);
     /// die;
     for ($s = 0; $s < $counter; $s++) {
          $out = $out !== '' && $out !== '0' ? $out . ',"' . $fac[$s] . '"' : '("' . $fac[$s] . '"';
     }
     $out .= ')';
     $sWhere[] = ' vl.lab_id IN ' . $out;
}

$sWhere[] = '  vl.result_status != ' . RECEIVED_AT_CLINIC;


if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}
$sWhere[] = " hf.test_type = 'hepatitis'";
$sWhere = $sWhere === [] ? [] : ' where ' . implode(' AND ', $sWhere);
$sQuery = $sQuery . ' ' . $sWhere;
$_SESSION['hepatitisMonitoringThresholdReportQuery'] = $sQuery;
// die($sQuery);
$rResult = $db->rawQuery($sQuery);
/*
$aResultFilterTotal = $db->rawQuery($sQuery);
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length
$aResultTotal =  $db->rawQuery($sQuery);
// $aResultTotal = $countResult->fetch_row();
$iTotal = count($aResultTotal);*/


$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];


$output = ["sEcho" => (int) $_POST['sEcho'], "aaData" => []];

$cnt = 0;
foreach ($rResult as $rowData) {
     $targetType1 = false;
     $targetType2 = false;
     $targetType3 = false;
     if ($_POST['targetType'] == 1) {
         if ($rowData['monthly_target'] > $rowData['totalCollected']) {
              $targetType1 = true;
         }
     } elseif ($_POST['targetType'] == 2) {
         if ($rowData['monthly_target'] < $rowData['totalCollected']) {
              $targetType2 = true;
         }
     } elseif ($_POST['targetType'] == 3) {
         $targetType3 = true;
     }
     if ($targetType1 || $targetType2 || $targetType3) {
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
}
$output['iTotalDisplayRecords'] = $cnt;
$output['iTotalRecords'] = $cnt;
echo json_encode($output);
