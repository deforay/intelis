<?php

use App\Services\EidService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
     session_start();
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

$sarr = $general->getSystemConfig();

$tableName = "form_eid";
$primaryKey = "eid_id";
/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.child_id', 'vl.child_name', 'f.facility_name', 'l_f.facility_name', 'vl.mother_id', 'vl.result', 'ts.status_name', 'funding_source_name', 'i_partner_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.child_id', 'vl.child_name', 'f.facility_name', 'l_f.facility_name', 'vl.mother_id', 'vl.result', 'ts.status_name', 'funding_source_name', 'i_partner_name');
$sampleCode = 'sample_code';
if ($_SESSION['instanceType'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
} elseif ($sarr['sc_user_type'] == 'standalone') {
     if (($key = array_search('vl.remote_sample_code', $aColumns)) !== false) {
          unset($aColumns[$key]);
     }
     if (($key = array_search('vl.remote_sample_code', $orderColumns)) !== false) {
          unset($orderColumns[$key]);
     }
}
/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;
$sTable = $tableName;
/*
* Paging
*/
$sLimit = "";
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
     $searchArray = explode(" ", $_POST['sSearch']);
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
$sQuery = "SELECT SQL_CALC_FOUND_ROWS
                    vl.*,
                    b.batch_code,
                    ts.status_name,
                    f.facility_name,
                    l_f.facility_name as lab_name,
                    f.facility_code,
                    f.facility_state,
                    f.facility_district,
                    UPPER(s.sample_name) as sample_name,
                    u_d.user_name as reviewedBy,
                    a_u_d.user_name as approvedBy,
                    rs.rejection_reason_name,
                    tr.test_reason_name,
                    r_f_s.funding_source_name,
                    r_i_p.i_partner_name,
                    rs.rejection_reason_name as rejection_reason
                    
                    FROM form_eid as vl
                    
                    INNER JOIN facility_details as f ON vl.facility_id=f.facility_id
                    INNER JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
                    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
                    LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
                    LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
                    LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
                    LEFT JOIN r_eid_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
                    LEFT JOIN r_eid_test_reasons as tr ON tr.test_reason_id=vl.reason_for_eid_test
                    LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source
                    LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";
/* Sample collection date filter */
$start_date = '';
$end_date = '';
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     $s_c_date = explode("to", $_POST['sampleCollectionDate']);
     //print_r($s_c_date);die;
     if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
          $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
     }
     if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
          $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
     }
}
$sReceivedDate = '';
$eReceivedDate = '';
if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != '') {
     $s_t_date = explode("to", $_POST['sampleReceivedDate']);
     if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
          $sReceivedDate = DateUtility::isoDateFormat(trim($s_t_date[0]));
     }
     if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
          $eReceivedDate = DateUtility::isoDateFormat(trim($s_t_date[1]));
     }
}
$sResultDispatchedDate = '';
$eResultDispatchedDate = '';
if (isset($_POST['resultDispatchedOn']) && trim($_POST['resultDispatchedOn']) != '') {
     $s_t_date = explode("to", $_POST['resultDispatchedOn']);
     if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
          $sResultDispatchedDate = DateUtility::isoDateFormat(trim($s_t_date[0]));
     }
     if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
          $eResultDispatchedDate = DateUtility::isoDateFormat(trim($s_t_date[1]));
     }
}
/* Sample test date filter */
$sTestDate = '';
$eTestDate = '';
if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
     $s_t_date = explode("to", $_POST['sampleTestDate']);
     if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
          $sTestDate = DateUtility::isoDateFormat(trim($s_t_date[0]));
     }
     if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
          $eTestDate = DateUtility::isoDateFormat(trim($s_t_date[1]));
     }
}
/* Print date filter */
$sPrintDate = '';
$ePrintDate = '';
if (isset($_POST['printDate']) && trim($_POST['printDate']) != '') {
     $s_p_date = explode("to", $_POST['printDate']);
     if (isset($s_p_date[0]) && trim($s_p_date[0]) != "") {
          $sPrintDate = DateUtility::isoDateFormat(trim($s_p_date[0]));
     }
     if (isset($s_p_date[1]) && trim($s_p_date[1]) != "") {
          $ePrintDate = DateUtility::isoDateFormat(trim($s_p_date[1]));
     }
}
/* Sample type filter */
if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
     $sWhere[] =  ' vl.specimen_type IN (' . $_POST['sampleType'] . ')';
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
     $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
     $sWhere[] = " f.facility_district_id = '" . $_POST['district'] . "' ";
}
/* Facility id filter */
if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
     $sWhere[] = ' vl.facility_id = "' . $_POST['facilityName'] . '"';
}
/* Testing lab filter */
if (isset($_POST['testingLab']) && trim($_POST['testingLab']) != '') {
     $sWhere[] = ' vl.lab_id = "' . $_POST['testingLab'] . '"';
}
/* Result filter */
if (isset($_POST['result']) && trim($_POST['result']) != '') {
     $sWhere[] = ' vl.result like "' . $_POST['result'] . '"';
}
/* Status filter */
if (isset($_POST['status']) && trim($_POST['status']) != '') {
     $sWhere[] = ' vl.result_status =' . $_POST['status'];
}
/* Funding src filter */
if (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') {
     $sWhere[] = ' vl.funding_source ="' . base64_decode($_POST['fundingSource']) . '"';
}
/* Implementing partner filter */
if (isset($_POST['implementingPartner']) && trim($_POST['implementingPartner']) != '') {
     $sWhere[] = ' vl.implementing_partner ="' . base64_decode($_POST['implementingPartner']) . '"';
}

if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
     $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}

if (isset($_POST['childId']) && $_POST['childId'] != "") {
     $sWhere[] = ' vl.child_id like "%' . $_POST['childId'] . '%"';
}
if (isset($_POST['childName']) && $_POST['childName'] != "") {
     $sWhere[] = ' vl.child_name like "%' . $_POST['childName'] . '%"';
}
if (isset($_POST['motherId']) && $_POST['motherId'] != "") {
     $sWhere[] = ' vl.mother_id like "%' . $_POST['motherId'] . '%"';
}
if (isset($_POST['motherName']) && $_POST['motherName'] != "") {
     $sWhere[] = ' vl.mother_name like "%' . $_POST['motherName'] . '%"';
}

/* Date time filters */
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     if (trim($start_date) == trim($end_date)) {
          $sWhere[] = ' DATE(vl.sample_collection_date) like "' . $start_date . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}
if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != '') {
     if (trim($sReceivedDate) == trim($eReceivedDate)) {
          $sWhere[] = ' DATE(vl.sample_received_at_vl_lab_datetime) like "' . $sReceivedDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_received_at_vl_lab_datetime) >= "' . $sReceivedDate . '" AND DATE(vl.sample_received_at_vl_lab_datetime) <= "' . $eReceivedDate . '"';
     }
}
if (isset($_POST['resultDispatchedOn']) && trim($_POST['resultDispatchedOn']) != '') {
     if (trim($sResultDispatchedDate) == trim($eResultDispatchedDate)) {
          $sWhere[] = ' DATE(vl.result_dispatched_datetime) like "' . $sResultDispatchedDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.result_dispatched_datetime) >= "' . $sResultDispatchedDate . '" AND DATE(vl.result_dispatched_datetime) <= "' . $eResultDispatchedDate . '"';
     }
}
if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
     if (!empty($sTestDate) && trim($sTestDate) == trim($eTestDate)) {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) like "' . $sTestDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
     }
}
if (isset($_POST['printDate']) && trim($_POST['printDate']) != '') {
     if (trim($sPrintDate) == trim($eTestDate)) {
          $sWhere[] = ' DATE(vl.result_printed_datetime) like "' . $sPrintDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.result_printed_datetime) >= "' . $sPrintDate . '" AND DATE(vl.result_printed_datetime) <= "' . $ePrintDate . '"';
     }
}

if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}

if (isset($sWhere) && !empty($sWhere)) {
     $sQuery = $sQuery . ' WHERE result_status is NOT NULL AND' . implode(" AND ", $sWhere);
} else {
     $sQuery = $sQuery . ' WHERE result_status is NOT NULL ';
}

if (isset($sOrder) && !empty($sOrder)) {
     $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . ' order by ' . $sOrder;
}
$_SESSION['eidExportResultQuery'] = $sQuery;

if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//$general->elog($sQuery);
$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */

$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

$_SESSION['eidExportResultQueryCount'] = $iTotal;

/*
          * Output
          */
$output = array(
     "sEcho" => intval($_POST['sEcho']),
     "iTotalRecords" => $iTotal,
     "iTotalDisplayRecords" => $iFilteredTotal,
     "aaData" => array()
);

foreach ($rResult as $aRow) {

     $patientFname = ($general->crypto('doNothing', $aRow['child_name'], $aRow['child_id']));
     $patientMname = ($general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['child_id']));
     $patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['child_id']));

     $row = [];
     $row[] = $aRow['sample_code'];
     if ($_SESSION['instanceType'] != 'standalone') {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = $aRow['batch_code'];
     $row[] = $aRow['child_id'];
     //$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
     $row[] = $aRow['child_name'];
     $row[] = ($aRow['facility_name']);
     $row[] = ($aRow['lab_name']);
     $row[] = $aRow['mother_id'];
     $row[] = $eidResults[$aRow['result']] ?? $aRow['result'];
     $row[] = ($aRow['status_name']);
     $row[] = $aRow['funding_source_name'] ?? null;
     $row[] = $aRow['i_partner_name'] ?? null;
     $row[] = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _("View") . '" onclick="convertSearchResultToPdf(' . $aRow['eid_id'] . ')"><em class="fa-solid fa-file-lines"></em> ' . _("Result PDF") . '</a>';

     $output['aaData'][] = $row;
}

echo json_encode($output);
