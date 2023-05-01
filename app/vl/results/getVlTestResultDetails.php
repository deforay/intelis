<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
     session_start();
}

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sarr = $general->getSystemConfig();


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


$tableName = "form_vl";
$primaryKey = "vl_sample_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.sample_code', 'vl.remote_sample_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.sample_code', 'vl.remote_sample_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "vl.last_modified_datetime", 'ts.status_name');
if (!empty($_POST['from']) && $_POST['from'] == "enterresult") {
     $aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
     $orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 's.sample_name', 'vl.result', "vl.last_modified_datetime", 'ts.status_name');
}
if ($_SESSION['instanceType'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
} else if ($_SESSION['instanceType'] == 'standalone') {
     if (($key = array_search("remote_sample_code", $aColumns)) !== false) {
          unset($aColumns[$key]);
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
               if (!empty($orderColumns[intval($_POST['iSortCol_' . $i])]))
                    $sOrder .= $orderColumns[intval($_POST['iSortCol_' . $i])] . "
				 	" . ($_POST['sSortDir_' . $i]) . ", ";
          }
     }
     $sOrder = substr_replace($sOrder, "", -2);
}
//echo $sOrder;
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
               $sWhereSub .= " (";
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
          $sWhereSub .= ") ";
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
$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.vl_sample_id,
vl.sample_code,
vl.remote_sample,
vl.remote_sample_code,
b.batch_code,
vl.sample_collection_date,
vl.sample_tested_datetime,
vl.patient_art_no,
vl.patient_first_name,
vl.patient_middle_name,
vl.patient_last_name,
f.facility_name, 
f.facility_district,
f.facility_state,
testingLab.facility_name as lab_name, 
UPPER(s.sample_name) as sample_name, 
vl.result,
vl.reason_for_vl_testing,
vl.last_modified_datetime,
vl.vl_test_platform,
vl.result_status,
vl.requesting_vl_service_sector,
vl.request_clinician_name,
vl.requesting_phone,
vl.patient_responsible_person,
vl.patient_mobile_number,
vl.consent_to_receive_sms,
vl.result_value_log,
vl.last_vl_date_routine,
vl.last_vl_date_ecd,
vl.last_vl_date_failure,
vl.last_vl_date_failure_ac,
vl.last_vl_date_cf,
vl.last_vl_date_if,
vl.lab_technician,
vl.patient_gender,
vl.locked,
ts.status_name,
vl.result_approved_datetime,
vl.result_reviewed_datetime,
vl.sample_received_at_hub_datetime, 
vl.sample_received_at_vl_lab_datetime, 
vl.result_dispatched_datetime, 
vl.result_printed_datetime,
vl.result_approved_by

FROM form_vl as vl 
LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
LEFT JOIN facility_details as testingLab ON vl.lab_id=testingLab.facility_id 
LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type 
INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status ";


$start_date = '';
$end_date = '';
$t_start_date = '';
$t_end_date = '';
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

if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
     $s_t_date = explode("to", $_POST['sampleTestDate']);
     //print_r($s_t_date);die;
     if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
          $t_start_date = DateUtility::isoDateFormat(trim($s_t_date[0]));
     }
     if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
          $t_end_date = DateUtility::isoDateFormat(trim($s_t_date[1]));
     }
}

if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
     $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}

if (isset($_POST['district']) && trim($_POST['district']) != '') {
     $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
     $sWhere[] = ' f.facility_state_id = "' . $_POST['state'] . '"';
}

if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
     $sWhere[] = ' vl.patient_art_no like "%' . $_POST['patientId'] . '%"';
}
if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
     $sWhere[] = " CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,'')) like '%" . $_POST['patientName'] . "%'";
}


if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {

     if (trim($start_date) == trim($end_date)) {
          $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}

if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
     if (trim($t_start_date) == trim($t_end_date)) {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $t_start_date . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $t_start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $t_end_date . '"';
     }
}
if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
     $sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
}
if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
     $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['vlLab']) && trim($_POST['vlLab']) != '') {
     $sWhere[] = ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
}
if (isset($_POST['artNo']) && trim($_POST['artNo']) != '') {
     $sWhere[] = " vl.patient_art_no LIKE '%" . $_POST['artNo'] . "%' ";
}
if (isset($_POST['status']) && trim($_POST['status']) != '') {
     if ($_POST['status'] == 'no_result') {
          $statusCondition = '  (vl.result is NULL OR vl.result ="")  AND vl.result_status !=4 ';
     } else if ($_POST['status'] == 'result') {
          $statusCondition = ' (vl.result is NOT NULL AND vl.result !=""  AND vl.result_status !=4) ';
     } else {
          $statusCondition = ' vl.result_status=4 ';
     }
     $sWhere[] = $statusCondition;
}
if (isset($_POST['gender']) && trim($_POST['gender']) != '') {
     if (trim($_POST['gender']) == "not_recorded") {
          $sWhere[] = ' (vl.patient_gender = "not_recorded" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
     } else {
          $sWhere[] = ' vl.patient_gender ="' . $_POST['gender'] . '"';
     }
}
if (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') {
     $sWhere[] = ' vl.funding_source ="' . base64_decode($_POST['fundingSource']) . '"';
}
if (isset($_POST['implementingPartner']) && trim($_POST['implementingPartner']) != '') {
     $sWhere[] = ' vl.implementing_partner ="' . base64_decode($_POST['implementingPartner']) . '"';
}

// Only approved results can be printed
if (!isset($_POST['status']) || trim($_POST['status']) == '') {
     if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
          $sWhere[] = " ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND result_printed_datetime is NOT NULL AND result_printed_datetime not like ''";
     } else {
          $sWhere[] = " ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND (result_printed_datetime is NULL OR result_printed_datetime like '')";
     }
} else {
     $sWhere[] = " vl.result_status!=9 ";
}
if ($_SESSION['instanceType'] == 'remoteuser') {
     $facilityMap = $facilitiesService->getUserFacilityMap($_SESSION['userId']);
     if (!empty($facilityMap)) {
          $sWhere[] = " vl.facility_id IN (" . $facilityMap . ")";
     }
}
if (isset($sWhere) && !empty($sWhere)) {
     $sQuery = $sQuery . ' WHERE' . implode(" AND ", $sWhere);
}
$_SESSION['vlResultQuery'] = $sQuery;

if (isset($sOrder) && $sOrder != "") {
     $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . ' order by ' . $sOrder;
}
if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
$rResult = $db->rawQuery($sQuery);


/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
//$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
$iTotal = $aResultFilterTotal['totalCount'];

/*
* Output
*/
$output = array(
     "sEcho" => intval($_POST['sEcho']),
     "iTotalRecords" => $iTotal,
     "iTotalDisplayRecords" => $iTotal,
     "aaData" => array()
);

foreach ($rResult as $aRow) {
     $row = [];
     if (isset($_POST['vlPrint'])) {
          if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'not-print') {
               $row[] = '<input type="checkbox" name="chk[]" class="checkRows" id="chk' . $aRow['vl_sample_id'] . '"  value="' . $aRow['vl_sample_id'] . '" onclick="checkedRow(this);"  />';
          } else {
               $row[] = '<input type="checkbox" name="chkPrinted[]" class="checkPrintedRows" id="chkPrinted' . $aRow['vl_sample_id'] . '"  value="' . $aRow['vl_sample_id'] . '" onclick="checkedPrintedRow(this);"  />';
          }
          $print = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _("Print") . '" onclick="convertResultToPdf(' . $aRow['vl_sample_id'] . ')"><em class="fa-solid fa-print"></em> ' . _("Print") . '</a>';
     } else {
          $print = '<a href="updateVlTestResult.php?id=' . base64_encode($aRow['vl_sample_id']) . '" class="btn btn-success btn-xs" style="margin-right: 2px;" title="' . _("Result") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _("Enter Result") . '</a>';
          if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes') {
               if (isset($_SESSION['privileges']) && !in_array("edit-locked-vl-samples", $_SESSION['privileges'])) {
                    $print = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _("Locked") . '" disabled><em class="fa-solid fa-lock"></em>' . _("Locked") . '</a>';
               }
          }
     }

     $patientFname = $general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_art_no']);
     $patientMname = $general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_art_no']);
     $patientLname = $general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_art_no']);

     $row[] = $aRow['sample_code'];
     if ($_SESSION['instanceType'] != 'standalone') {
          $row[] = $aRow['remote_sample_code'];
     }
     if (!empty($_POST['from']) && $_POST['from'] == "enterresult") {
          $row[] = $aRow['batch_code'];
     }
     $row[] = $aRow['patient_art_no'];
     $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
     $row[] = ($aRow['facility_name']);
     $row[] = ($aRow['lab_name']);
     if (empty($_POST['from']) || $_POST['from'] != "enterresult") {
          $row[] = ($aRow['facility_state']);
          $row[] = ($aRow['facility_district']);
     }
     $row[] = ($aRow['sample_name']);
     $row[] = $aRow['result'];
     if (isset($aRow['last_modified_datetime']) && trim($aRow['last_modified_datetime']) != '' && $aRow['last_modified_datetime'] != '0000-00-00 00:00:00') {
          $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);
     } else {
          $aRow['last_modified_datetime'] = '';
     }

     $row[] = $aRow['last_modified_datetime'];
     $row[] = ($aRow['status_name']);
     $row[] = $print;
     $output['aaData'][] = $row;
}

echo json_encode($output);
