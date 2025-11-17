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

$formId = (int) $general->getGlobalConfig('vl_form');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "form_tb";
$primaryKey = "tb_id";

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
$sQuery = "SELECT DATE_FORMAT(DATE(vl.sample_tested_datetime), '%b-%Y') as monthrange, f.*, vl.*, hf.monthly_target FROM testing_labs as hf INNER JOIN form_tb as vl ON vl.lab_id=hf.facility_id LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id  ";

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

if ($sWhere !== '' && $sWhere !== '0') {
     $sWhere = ' WHERE ' . $sWhere;

     if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
          if (trim((string) $sTestDate) === trim((string) $eTestDate)) {
               $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
          } else {
               $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
          }
     }

     if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
          $fac = explode(',', (string) $_POST['facilityName']);
          $out = '';
          //          print_r($fac);
          //          die;
          $counter = count($fac);
          //          print_r($fac);
          //          die;
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
     $sWhere = $sWhere . '  AND vl.result_status != ' . RECEIVED_AT_CLINIC;
} else {
     $sWhere = $sWhere . ' WHERE vl.result_status != ' . RECEIVED_AT_CLINIC;
}

if (!empty($_SESSION['facilityMap'])) {
     $sWhere .= " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}
$sWhere .= " AND hf.test_type = 'tb'";
$sQuery = $sQuery . ' ' . $sWhere;
$_SESSION['hepatitisMonitoringThresholdReportQuery'] = $sQuery;
$rResult = $db->rawQuery($sQuery);

$aResultFilterTotal = $db->rawQuery($sQuery);
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal = $db->rawQuery($sQuery);
// $aResultTotal = $countResult->fetch_row();
$iTotal = count($aResultTotal);


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
