<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$covid19Results = $covid19Service->getCovid19Results();

$tableName = "form_covid19";
$primaryKey = "covid19_id";

$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'CONCAT(COALESCE(vl.patient_name,""), COALESCE(vl.patient_surname,""))', 'f.facility_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($general->isSTSInstance()) {
     $sampleCode = 'remote_sample_code';
} else if ($general->isStandaloneInstance()) {
     $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
     $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}
if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
     array_unshift($orderColumns, "vl.covid19_id");
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




$sQuery = "SELECT vl.*,b.*,ts.*,f.facility_name,
          l_f.facility_name as labName,
          l_f.facility_logo as facilityLogo,
          l_f.header_text as headerText,
          f.facility_code,
          f.facility_state,
          f.facility_district,
          u_d.user_name as reviewedBy,
          a_u_d.user_name as approvedBy
          FROM form_covid19 as vl
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
          INNER JOIN covid19_tests as ct ON ct.covid19_id=vl.covid19_id
          INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
          LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
          LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by";
$start_date = '';
$end_date = '';
$t_start_date = '';
$t_end_date = '';
if (!empty($_POST['sampleCollectionDate'])) {
     $s_c_date = explode("to", (string) $_POST['sampleCollectionDate']);
     if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
          $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
     }
     if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
          $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
     }
}

if (!empty($sWhere)) {
     $sWhere = ' WHERE ' . $sWhere;
     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
          $sWhere = $sWhere . ' AND b.batch_code = "' . $_POST['batchCode'] . '"';
     }
     if (!empty($_POST['sampleCollectionDate'])) {
          if (trim((string) $start_date) == trim((string) $end_date)) {
               $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
          } else {
               $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
          }
     }

     if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) != '') {
          $sWhere = $sWhere . ' AND f.facility_id IN (' . $_POST['facilityName'] . ')';
     }
     if (isset($_POST['status']) && trim((string) $_POST['status']) != '') {
          if ($_POST['status'] == 'no_result') {
               $statusCondition = ' AND (vl.result is NULL OR vl.result ="") AND vl.result_status != ' . SAMPLE_STATUS\REJECTED;
          } else if ($_POST['status'] == 'result') {
               $statusCondition = ' AND (vl.result is NOT NULL AND vl.result !="" AND vl. != ' . SAMPLE_STATUS\REJECTED . ')';
          } else {
               $statusCondition = ' AND vl.result_status = ' . SAMPLE_STATUS\REJECTED;
          }
          $sWhere = $sWhere . $statusCondition;
     }

     if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) != '') {
          $sWhere = $sWhere . ' AND vl.funding_source ="' . base64_decode((string) $_POST['fundingSource']) . '"';
     }
     if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) != '') {
          $sWhere = $sWhere . ' AND vl.implementing_partner ="' . base64_decode((string) $_POST['implementingPartner']) . '"';
     }
} else {
     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
          $setWhr = 'where';
          $sWhere = ' WHERE ' . $sWhere;
          $sWhere = $sWhere . ' b.batch_code = "' . $_POST['batchCode'] . '"';
     }

     if (!empty($_POST['sampleCollectionDate'])) {
          if (isset($setWhr)) {
               if (trim((string) $start_date) == trim((string) $end_date)) {
                    $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
               } else {
                    $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
               }
          } else {
               $setWhr = 'where';
               $sWhere = ' WHERE ' . $sWhere;
               $sWhere = $sWhere . ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
          }
     }


     if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND f.facility_id IN (' . $_POST['facilityName'] . ')';
          } else {
               $setWhr = 'where';
               $sWhere = ' WHERE ' . $sWhere;
               $sWhere = $sWhere . ' f.facility_id IN (' . $_POST['facilityName'] . ')';
          }
     }

     if (isset($_POST['status']) && trim((string) $_POST['status']) != '') {
          if (isset($setWhr)) {
               if ($_POST['status'] == 'no_result') {
                    $statusCondition = ' AND  (vl.result is NULL OR vl.result ="")  AND vl.result_status = ' . SAMPLE_STATUS\REJECTED;
               } else if ($_POST['status'] == 'result') {
                    $statusCondition = ' AND (vl.result is NOT NULL AND vl.result !=""  AND vl.result_status != ' . SAMPLE_STATUS\REJECTED . ')';
               } else {
                    $statusCondition = ' AND vl.result_status = ' . SAMPLE_STATUS\REJECTED;
               }
               $sWhere = $sWhere . $statusCondition;
          } else {
               $setWhr = 'where';
               $sWhere = ' WHERE ' . $sWhere;
               if ($_POST['status'] == 'no_result') {
                    $statusCondition = '  (vl.result is NULL OR vl.result ="")  AND vl.result_status = ' . SAMPLE_STATUS\REJECTED;
               } else if ($_POST['status'] == 'result') {
                    $statusCondition = ' (vl.result is NOT NULL AND vl.result !=""  AND vl.result_status != ' . SAMPLE_STATUS\REJECTED . ')';
               } else {
                    $statusCondition = ' vl.result_status = ' . SAMPLE_STATUS\REJECTED;
               }
               $sWhere = $sWhere . $statusCondition;
          }
     }

     if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND vl.funding_source ="' . base64_decode((string) $_POST['fundingSource']) . '"';
          } else {
               $setWhr = 'where';
               $sWhere = ' WHERE ' . $sWhere;
               $sWhere = $sWhere . ' vl.funding_source ="' . base64_decode((string) $_POST['fundingSource']) . '"';
          }
     }
     if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) != '') {
          if (isset($setWhr)) {
               $sWhere = $sWhere . ' AND vl.implementing_partner ="' . base64_decode((string) $_POST['implementingPartner']) . '"';
          } else {
               $setWhr = 'where';
               $sWhere = ' WHERE ' . $sWhere;
               $sWhere = $sWhere . ' vl.implementing_partner ="' . base64_decode((string) $_POST['implementingPartner']) . '"';
          }
     }
}
$dWhere = '';
// Only approved results can be printed
if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
     if (!isset($_POST['status']) || trim((string) $_POST['status']) == '') {
          if (trim($sWhere) != '') {
               $sWhere = $sWhere . " AND ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND (result_printed_datetime is NULL OR DATE(result_printed_datetime) = '0000-00-00')";
          } else {
               $sWhere = "WHERE ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND (result_printed_datetime is NULL OR DATE(result_printed_datetime) = '0000-00-00')";
          }
     }

     $dWhere = "WHERE ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND (result_printed_datetime is NULL OR DATE(result_printed_datetime) = '0000-00-00')";
} else {
     if (trim($sWhere) != '') {
          $sWhere = $sWhere . "  AND vl.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
     } else {
          $sWhere = "WHERE vl.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
     }
     $dWhere = "WHERE vl.result_statu != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
}


if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
     $sWhere = $sWhere . " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
     $dWhere = $dWhere . " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
}
$sQuery = $sQuery . ' ' . $sWhere . ' AND ct.result LIKE "positive" GROUP BY vl.covid19_id';
$_SESSION['vlResultQuery'] = $sQuery;
//echo $_SESSION['vlResultQuery'];die;

if (!empty($sOrder) && $sOrder !== '') {
     $sOrder = preg_replace('/\s+/', ' ', $sOrder);
     $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */

$aResultFilterTotal = $db->rawQuery("SELECT * FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id  INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id $sWhere ORDER BY $sOrder");
$iFilteredTotal = count($aResultFilterTotal);
/* Total data set length */
$aResultTotal = $db->rawQuery("SELECT * FROM form_covid19 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id $sWhere ORDER BY $sOrder");
$iTotal = count($aResultTotal);


$output = array(
     "sEcho" => (int) $_POST['sEcho'],
     "iTotalRecords" => $iTotal,
     "iTotalDisplayRecords" => $iFilteredTotal,
     "aaData" => []
);

foreach ($rResult as $aRow) {
     $row = [];
     $print = '';
     if ($aRow['result'] == '') {

          $print = '<a href="update-record-confirmatory-tests.php?id=' . base64_encode((string) $aRow['covid19_id']) . '" class="btn btn-success btn-xs" style="margin-right: 2px;" title="Result"><em class="fa-solid fa-pen-to-square"></em> Enter Result</a>';
     } else {
          $print = '<a href="update-record-confirmatory-tests.php?id=' . base64_encode((string) $aRow['covid19_id']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;" title="Result"><em class="fa-solid fa-eye"></em> View</a>';
     }



     $row[] = $aRow['sample_code'];
     if (!$general->isStandaloneInstance()) {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = $aRow['batch_code'];
     $row[] = ($aRow['facility_name']);
     $row[] = $aRow['patient_id'];
     $row[] = $aRow['patient_name'] . " " . $aRow['patient_surname'];
     $row[] = $covid19Results[$aRow['result']] ?? $aRow['result'];

     $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');

     $row[] = $aRow['last_modified_datetime'];
     $row[] = ($aRow['status_name']);
     $row[] = $print;
     $output['aaData'][] = $row;
}

echo json_encode($output);
