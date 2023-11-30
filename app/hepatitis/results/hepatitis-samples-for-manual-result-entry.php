<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\HepatitisService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
     session_start();
}


$formConfigQuery = "SELECT * from global_config where name='vl_form'";
$configResult = $db->query($formConfigQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
     $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
     $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "form_hepatitis";
$primaryKey = "hepatitis_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
 * you want to insert a non-database field (for example a counter or static image)
 */
$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.external_sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'CONCAT(COALESCE(vl.patient_name,""), COALESCE(vl.patient_surname,""))', 'f.facility_name', 'vl.hcv_vl_count', 'vl.hbv_vl_count', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.external_sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'vl.hcv_vl_count', 'vl.hbv_vl_count', 'vl.last_modified_datetime', 'ts.status_name');
if ($_SESSION['instanceType'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
} else if ($sarr['sc_user_type'] == 'standalone') {
     $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
     $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}
if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
     array_unshift($orderColumns, "vl.hepatitis_id");
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

/* Individual column filtering */
for ($i = 0; $i < count($aColumns); $i++) {
     if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
          $sWhere[] = $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
     }
}

/*
 * SQL queries
 * Get data to display
 */
$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.hepatitis_id,vl.sample_code, vl.external_sample_code,
          vl.remote_sample_code, vl.patient_id, vl.patient_name,
          vl.patient_surname,vl.patient_phone_number,vl.patient_gender,vl.is_sample_collected,vl.reason_for_hepatitis_test,
          vl.specimen_type,vl.hepatitis_test_platform,vl.result_status,vl.locked,vl.is_sample_rejected,vl.reason_for_sample_rejection,
          vl.result,vl.result_reviewed_datetime,vl.result_reviewed_by,vl.result_approved_datetime,vl.result_approved_by,
          vl.hcv_vl_count,vl.hbv_vl_count,vl.is_encrypted,
          vl.result_dispatched_datetime,vl.last_modified_datetime as lastModifiedDate,vl.last_modified_by ,b.*,ts.*,f.facility_name,
          l_f.facility_name as labName,
          l_f.facility_logo as facilityLogo,
          l_f.header_text as headerText,
          f.facility_code,
          f.facility_state,
          f.facility_district,
          u_d.user_name as reviewedBy,
          a_u_d.user_name as approvedBy
          FROM form_hepatitis as vl
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
          INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
          LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
          LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by";
[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
     $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (!empty($_POST['sampleCollectionDate'])) {
     if (trim((string) $start_date) == trim((string) $end_date)) {
          $sWhere[] = ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}

if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) != '') {
     $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}

if (isset($_POST['vlLab']) && trim((string) $_POST['vlLab']) != '') {
     $sWhere[] = ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
}

if (isset($_POST['status']) && trim((string) $_POST['status']) != '') {
     if ($_POST['status'] == 'no_result') {
          $statusCondition = ' (vl.hcv_vl_count is NULL OR vl.hcv_vl_count  ="" OR vl.hbv_vl_count is NULL OR vl.hbv_vl_count  ="") AND vl.result_status != ' . SAMPLE_STATUS\REJECTED;
     } else if ($_POST['status'] == 'result') {
          $statusCondition = ' ((vl.hcv_vl_count is NOT NULL OR vl.hcv_vl_count  !="" OR vl.hbv_vl_count is NOT NULL OR vl.hbv_vl_count  !="") AND vl. != ' . SAMPLE_STATUS\REJECTED . ')';
     } else {
          $statusCondition = ' vl.result_status = ' . SAMPLE_STATUS\REJECTED;
     }
     $sWhere[] = $statusCondition;
}

if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) != '') {
     $sWhere[] = '  vl.funding_source ="' . base64_decode((string) $_POST['fundingSource']) . '"';
}
if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) != '') {
     $sWhere[] = '  vl.implementing_partner ="' . base64_decode((string) $_POST['implementingPartner']) . '"';
}

///$dWhere = '';
// Only approved results can be printed
if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
     if (!isset($_POST['status']) || trim((string) $_POST['status']) == '') {
          if (!empty($sWhere)) {
               $sWhere[] = " ((vl.result_status = 7 AND (vl.hcv_vl_count is NULL AND vl.hcv_vl_count  ='' AND vl.hbv_vl_count is NULL AND vl.hbv_vl_count  ='')) OR (vl.result_status = 4 AND (vl.hcv_vl_count is NULL AND vl.hcv_vl_count  ='' AND vl.hbv_vl_count is NULL AND vl.hbv_vl_count  =''))) AND (result_printed_datetime is NULL OR result_printed_datetime like '')";
          } else {
               $sWhere[] = " ((vl.result_status = 7 AND (vl.hcv_vl_count is NULL AND vl.hcv_vl_count  ='' AND vl.hbv_vl_count is NULL AND vl.hbv_vl_count  ='')) OR (vl.hcv_vl_count is NULL AND vl.hcv_vl_count  ='' AND vl.hbv_vl_count is NULL AND vl.hbv_vl_count  =''))) AND (result_printed_datetime is NULL OR result_printed_datetime like '')";
          }
     }
}
if ($_SESSION['instanceType'] == 'remoteuser' && !empty($_SESSION['facilityMap'])) {
     $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
     // $dWhere = $dWhere . " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}

if (!empty($sWhere))
     $sWhere = implode(' AND ', $sWhere);


$sQuery = $sQuery . ' WHERE ' . $sWhere;
//echo $sQuery; die();
$_SESSION['vlResultQuery'] = $sQuery;
//echo $_SESSION['vlResultQuery'];die;

if (!empty($sOrder)) {
     $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . ' order by ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// die($sQuery);
$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

/*
 * Output
 */
$output = array(
     "sEcho" => intval($_POST['sEcho']),
     "iTotalRecords" => $iTotal,
     "iTotalDisplayRecords" => $iFilteredTotal,
     "aaData" => []
);

/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);
$hepatitisResults = $hepatitisService->getHepatitisResults();
foreach ($rResult as $aRow) {
     $row = [];
     $print = '<a href="hepatitis-update-result.php?id=' . base64_encode((string) $aRow['hepatitis_id']) . '" class="btn btn-success btn-xs" style="margin-right: 2px;" title="' . _translate("Result") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Enter Result") . '</a>';
     if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes') {
          if (isset($_SESSION['privileges']) && !in_array("/hepatitis/requests/edit-locked-hepatitis-samples", $_SESSION['privileges'])) {
               $print = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Locked") . '" disabled><em class="fa-solid fa-lock"></em> ' . _translate("Locked") . '</a>';
          }
     }



     $row[] = $aRow['sample_code'] . (!empty($aRow['external_sample_code']) ? "<br>/" . $aRow['external_sample_code'] : '');
     if ($_SESSION['instanceType'] != 'standalone') {
          $row[] = $aRow['remote_sample_code'];
     }
     if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
          $key = base64_decode((string) $general->getGlobalConfig('key'));
          $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
          $aRow['patient_name'] = $general->crypto('decrypt', $aRow['patient_name'], $key);
          $aRow['patient_surname'] = $general->crypto('decrypt', $aRow['patient_surname'], $key);
     }
     $row[] = $aRow['batch_code'];
     $row[] = ($aRow['facility_name']);
     $row[] = $aRow['patient_id'];
     $row[] = $aRow['patient_name'] . " " . $aRow['patient_surname'];
     $row[] = $aRow['hcv_vl_count'];
     $row[] = $aRow['hbv_vl_count'];

     if (isset($aRow['lastModifiedDate']) && trim((string) $aRow['lastModifiedDate']) != '' && $aRow['lastModifiedDate'] != '0000-00-00 00:00:00') {
          $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['lastModifiedDate'], true);
     } else {
          $aRow['last_modified_datetime'] = '';
     }

     $row[] = $aRow['last_modified_datetime'];
     $row[] = ($aRow['status_name']);
     $row[] = $print;
     $output['aaData'][] = $row;
}

echo json_encode($output);
