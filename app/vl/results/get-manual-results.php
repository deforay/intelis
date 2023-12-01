<?php

use App\Services\FacilitiesService;
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

$sarr = $general->getSystemConfig();


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


$tableName = "form_vl";
$primaryKey = "vl_sample_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
 * you want to insert a non-database field (for example a counter or static image)
 */
$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 's.sample_name', 'vl.result', "vl.last_modified_datetime", 'ts.status_name');
if ($_SESSION['instanceType'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
} else if ($_SESSION['instanceType'] == 'standalone') {
     if (($key = array_search("remote_sample_code", $aColumns)) !== false) {
          unset($aColumns[$key]);
          $aColumns = array_values($aColumns);
          unset($orderColumns[$key]);
          $orderColumns = array_values($orderColumns);
     }
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
     for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
          if ($_POST['bSortable_' . (int) $_POST['iSortCol_' . $i]] == "true") {
               if (!empty($orderColumns[(int) $_POST['iSortCol_' . $i]]))
                    $sOrder .= $orderColumns[(int) $_POST['iSortCol_' . $i]] . "
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
     $searchArray = explode(" ", (string) $_POST['sSearch']);
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
$columnCounter = count($aColumns);
for ($i = 0; $i < $columnCounter; $i++) {
     if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
          $sWhere[] = $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
     }
}

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
               s.sample_name as sample_name,
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
               vl.sample_received_at_lab_datetime,
               vl.result_dispatched_datetime,
               vl.result_printed_datetime,
               vl.result_approved_by,
               vl.is_encrypted
               FROM form_vl as vl
               LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
               LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
               LEFT JOIN facility_details as testingLab ON vl.lab_id=testingLab.facility_id
               LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type
               INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status ";


[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
[$t_start_date, $t_end_date] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '');

if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
     $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}

if (isset($_POST['district']) && trim((string) $_POST['district']) != '') {
     $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}
if (isset($_POST['state']) && trim((string) $_POST['state']) != '') {
     $sWhere[] = ' f.facility_state_id = "' . $_POST['state'] . '"';
}

if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
     $sWhere[] = ' vl.patient_art_no like "%' . $_POST['patientId'] . '%"';
}
if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
     $sWhere[] = " CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,'')) like '%" . $_POST['patientName'] . "%'";
}


if (!empty($_POST['sampleCollectionDate'])) {

     if (trim((string) $start_date) == trim((string) $end_date)) {
          $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}

if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) != '') {
     if (trim((string) $t_start_date) == trim((string) $t_end_date)) {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $t_start_date . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $t_start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $t_end_date . '"';
     }
}
if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) != '') {
     $sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
}
if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) != '') {
     $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['vlLab']) && trim((string) $_POST['vlLab']) != '') {
     $sWhere[] = ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
}
if (isset($_POST['artNo']) && trim((string) $_POST['artNo']) != '') {
     $sWhere[] = " vl.patient_art_no LIKE '%" . $_POST['artNo'] . "%' ";
}
if (isset($_POST['status']) && trim((string) $_POST['status']) != '') {
     if ($_POST['status'] == 'no_result') {
          $statusCondition = '  (vl.result is NULL OR vl.result ="")  AND vl.result_status != ' . SAMPLE_STATUS\REJECTED;
     } else if ($_POST['status'] == 'result') {
          $statusCondition = ' (vl.result is NOT NULL AND vl.result !="")  OR vl.result_status = ' . SAMPLE_STATUS\REJECTED;
     } else {
          $statusCondition = ' vl.result_status= ' . SAMPLE_STATUS\REJECTED;
     }
     $sWhere[] = $statusCondition;
}
if (isset($_POST['gender']) && trim((string) $_POST['gender']) != '') {
     if (trim((string) $_POST['gender']) == "not_recorded") {
          $sWhere[] = ' (vl.patient_gender = "not_recorded" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
     } else {
          $sWhere[] = ' vl.patient_gender ="' . $_POST['gender'] . '"';
     }
}
if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) != '') {
     $sWhere[] = ' vl.funding_source ="' . base64_decode((string) $_POST['fundingSource']) . '"';
}
if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) != '') {
     $sWhere[] = ' vl.implementing_partner ="' . base64_decode((string) $_POST['implementingPartner']) . '"';
}

// Only approved results can be printed
if (!isset($_POST['status']) || trim((string) $_POST['status']) == '') {
     $sWhere[] = " ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND (result_printed_datetime is NULL OR result_printed_datetime like '')";
} else {
     $sWhere[] = " vl.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
}

if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")";
}

if (!empty($sWhere)) {
     $sQuery = $sQuery . ' WHERE' . implode(" AND ", $sWhere);
}
$_SESSION['vlResultQuery'] = $sQuery;

if (!empty($sOrder)) {
     $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . ' order by ' . $sOrder;
}
if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}


$rResult = $db->rawQuery($sQuery);


/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $aResultFilterTotal['totalCount'];

$_SESSION['vlResultQueryCount'] = $iTotal;

/*
 * Output
 */
$output = array(
     "sEcho" => (int) $_POST['sEcho'],
     "iTotalRecords" => $iTotal,
     "iTotalDisplayRecords" => $iTotal,
     "aaData" => []
);

foreach ($rResult as $aRow) {
     $row = [];
     if (isset($_POST['vlPrint'])) {
          if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'not-print') {
               $row[] = '<input type="checkbox" name="chk[]" class="checkRows" id="chk' . $aRow['vl_sample_id'] . '"  value="' . $aRow['vl_sample_id'] . '" onclick="checkedRow(this);"  />';
          } else {
               $row[] = '<input type="checkbox" name="chkPrinted[]" class="checkPrintedRows" id="chkPrinted' . $aRow['vl_sample_id'] . '"  value="' . $aRow['vl_sample_id'] . '" onclick="checkedPrintedRow(this);"  />';
          }
          $print = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Print") . '" onclick="convertResultToPdf(' . $aRow['vl_sample_id'] . ')"><em class="fa-solid fa-print"></em> ' . _translate("Print") . '</a>';
     } else {
          $print = '<a href="updateVlTestResult.php?id=' . base64_encode((string) $aRow['vl_sample_id']) . '" class="btn btn-success btn-xs" style="margin-right: 2px;" title="' . _translate("Result") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Enter Result") . '</a>';
          if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes') {
               if (!_isAllowed("/vl/requests/edit-locked-vl-samples")) {
                    $print = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Locked") . '" disabled><em class="fa-solid fa-lock"></em>' . _translate("Locked") . '</a>';
               }
          }
     }

     $patientFname = $aRow['patient_first_name'] ?? '';
     $patientMname = $aRow['patient_middle_name'] ?? '';
     $patientLname = $aRow['patient_last_name'] ?? '';
     if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
          $key = base64_decode((string) $general->getGlobalConfig('key'));
          $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
          $patientFname = $general->crypto('decrypt', $patientFname, $key);
          $patientMname = $general->crypto('decrypt', $patientMname, $key);
          $patientLname = $general->crypto('decrypt', $patientLname, $key);
     }

     $row[] = $aRow['sample_code'];
     if ($_SESSION['instanceType'] != 'standalone') {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = $aRow['batch_code'];
     $row[] = $aRow['patient_art_no'];
     $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
     $row[] = ($aRow['facility_name']);
     $row[] = ($aRow['lab_name']);
     $row[] = ($aRow['sample_name']);
     $row[] = $aRow['result'];
     $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');

     $row[] = $aRow['last_modified_datetime'];
     $row[] = ($aRow['status_name']);
     $row[] = $print;
     $output['aaData'][] = $row;
}

echo json_encode($output);
