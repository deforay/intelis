<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$barCodePrinting = $general->getGlobalConfig('bar_code_printing');


$tableName = "form_vl";
$primaryKey = "vl_sample_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
 * you want to insert a non-database field (for example a counter or static image)
 */
$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'testingLab.facility_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');

$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'testingLab.facility_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($_SESSION['instanceType'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
} elseif ($_SESSION['instanceType'] == 'standalone') {
     $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
     $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
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
//  echo intval($_POST['iSortingCols']);
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
               $sWhereSub .= " (";
          } else {
               $sWhereSub .= " AND (";
          }
          $colSize = count($aColumns);

          for ($i = 0; $i < $colSize; $i++) {
               if ($i < $colSize - 1) {
                    if (!empty($aColumns[$i])) {
                         $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    }
               } else {
                    if (!empty($aColumns[$i])) {
                         $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
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


$sQuery = "SELECT
               vl.vl_sample_id,
               vl.sample_code,
               vl.remote_sample_code,
               vl.patient_art_no,
               vl.patient_first_name,
               vl.patient_middle_name,
               vl.patient_last_name,
               vl.patient_dob,
               vl.patient_gender,
               vl.patient_age_in_years,
               vl.sample_collection_date,
               vl.treatment_initiated_date,
               vl.date_of_initiation_of_current_regimen,
               vl.test_requested_on,
               vl.sample_tested_datetime,
               vl.arv_adherance_percentage,
               vl.is_sample_rejected,
               vl.reason_for_sample_rejection,
               vl.result_value_log,
               vl.result_value_absolute,
               vl.result,
               vl.result_value_hiv_detection,
               vl.current_regimen,
               vl.is_patient_pregnant,
               vl.is_patient_breastfeeding,
               vl.request_clinician_name,
               vl.lab_tech_comments,
               vl.sample_received_at_hub_datetime,
               vl.sample_received_at_lab_datetime,
               vl.result_dispatched_datetime,
               vl.request_created_datetime,
               vl.result_printed_datetime,
               vl.last_modified_datetime,
               vl.result_status,
               vl.locked,
               vl.is_encrypted,
               s.sample_name as sample_name,
               b.batch_code,
               ts.status_name,
               f.facility_name,
               testingLab.facility_name as lab_name,
               f.facility_code,
               f.facility_state,
               f.facility_district,
               u_d.user_name as reviewedBy,
               a_u_d.user_name as approvedBy,
               rs.rejection_reason_name,
               tr.test_reason_name,
               r_f_s.funding_source_name,
               r_i_p.i_partner_name,
               rs.rejection_reason_name as rejection_reason

               FROM form_vl as vl

               LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
               LEFT JOIN facility_details as testingLab ON vl.lab_id=testingLab.facility_id
               LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type
               LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
               LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
               LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
               LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
               LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
               LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing
               LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source
               LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";


[$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
[$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDateAtLab'] ?? '');
[$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate'] ?? '');
[$sPrintDate, $ePrintDate] = DateUtility::convertDateRange($_POST['printDate'] ?? '');


if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
     $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     if (trim($startDate) == trim($endDate)) {
          $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $startDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $startDate . '" AND DATE(vl.sample_collection_date) <= "' . $endDate . '"';
     }
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim($_POST['sampleReceivedDateAtLab']) != '') {
     if (trim($labStartDate) == trim($labEndDate)) {
          $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) like "' . $labStartDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) >= "' . $labStartDate . '" AND DATE(vl.sample_received_at_lab_datetime) <= "' . $labEnddate . '"';
     }
}
if (isset($_POST['sampleTestedDate']) && trim($_POST['sampleTestedDate']) != '') {
     if (trim($testedStartDate) == trim($testedEndDate)) {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) like "' . $testedStartDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $testedStartDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $testedEndDate . '"';
     }
}
/* Viral load filter */
if (isset($_POST['vLoad']) && trim($_POST['vLoad']) != '') {
     if ($_POST['vLoad'] === 'suppressed') {
          $sWhere[] = " vl.vl_result_category like 'suppressed' AND vl.vl_result_category is NOT NULL ";
     } else {
          $sWhere[] = "  vl.vl_result_category like 'not suppressed' AND vl.vl_result_category is NOT NULL ";
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
if (isset($_POST['gender']) && trim($_POST['gender']) != '') {
     if (trim($_POST['gender']) == "not_recorded") {
          $sWhere[] = ' (vl.patient_gender="not_recorded" OR vl.patient_gender="" OR vl.patient_gender IS NULL)';
     } else {
          $sWhere[] = ' vl.patient_gender IN ("' . $_POST['gender'] . '")';
     }
}

/* Sample status filter */
if (isset($_POST['status']) && trim($_POST['status']) != '') {
     $sWhere[] = '  (vl.result_status IS NOT NULL AND vl.result_status =' . $_POST['status'] . ')';
}
if (isset($_POST['showReordSample']) && trim($_POST['showReordSample']) != '') {
     $sWhere[] = ' vl.sample_reordered IN ("' . $_POST['showReordSample'] . '")';
}
if (isset($_POST['communitySample']) && trim($_POST['communitySample']) != '') {
     $sWhere[] = ' (vl.community_sample IS NOT NULL AND vl.community_sample ="' . $_POST['communitySample'] . '") ';
}
if (isset($_POST['patientPregnant']) && trim($_POST['patientPregnant']) != '') {
     $sWhere[] = ' vl.is_patient_pregnant IN ("' . $_POST['patientPregnant'] . '")';
}

if (isset($_POST['breastFeeding']) && trim($_POST['breastFeeding']) != '') {
     $sWhere[] = ' vl.is_patient_breastfeeding IN ("' . $_POST['breastFeeding'] . '")';
}
if (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') {
     $sWhere[] = ' vl.funding_source IN ("' . base64_decode($_POST['fundingSource']) . '")';
}
if (isset($_POST['implementingPartner']) && trim($_POST['implementingPartner']) != '') {
     $sWhere[] = ' vl.implementing_partner IN ("' . base64_decode($_POST['implementingPartner']) . '")';
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
     $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
     $sWhere[] = ' f.facility_state_id = "' . $_POST['state'] . '"';
}

if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'result') {
     $sWhere[] = ' vl.result != "" ';
} elseif (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'noresult') {
     $sWhere[] = ' (vl.result IS NULL OR vl.result = "") ';
}
if (isset($_POST['srcOfReq']) && trim($_POST['srcOfReq']) != '') {
     $sWhere[] = ' vl.source_of_request like "' . $_POST['srcOfReq'] . '" ';
}
/* Source of request show model conditions */
if (isset($_POST['dateRangeModel']) && trim($_POST['dateRangeModel']) != '') {
     $sWhere[] = ' DATE(vl.sample_collection_date) like "' . DateUtility::isoDateFormat($_POST['dateRangeModel']) . '"';
}
if (isset($_POST['srcOfReqModel']) && trim($_POST['srcOfReqModel']) != '') {
     $sWhere[] = ' vl.source_of_request like "' . $_POST['srcOfReqModel'] . '" ';
}
if (isset($_POST['labIdModel']) && trim($_POST['labIdModel']) != '') {
     $sWhere[] = ' vl.lab_id like "' . $_POST['labIdModel'] . '" ';
}
if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == 4) {
     $sWhere[] = ' vl.is_sample_rejected is not null AND vl.is_sample_rejected like "yes"';
}
if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == 6) {
     $sWhere[] = ' vl.sample_received_at_lab_datetime is not null AND vl.sample_received_at_lab_datetime not like ""';
}
if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == 7) {
     $sWhere[] = ' vl.result is not null AND vl.result not like "" AND result_status = ' . SAMPLE_STATUS\ACCEPTED;
}
if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == "sent") {
     $sWhere[] = ' vl.result_sent_to_source is not null and vl.result_sent_to_source = "sent"';
}
if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
     $sWhere[] = ' vl.patient_art_no like "' . $_POST['patientId'] . '"';
}
if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
     $sWhere[] = " CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,'')) like '%" . $_POST['patientName'] . "%'";
}
if (empty($_POST['recencySamples']) || $_POST['recencySamples'] === 'no') {
     $sWhere[] = " IFNULL(reason_for_vl_testing, 0)  != 9999 ";
}
if (!empty($_POST['rejectedSamples']) && $_POST['rejectedSamples'] == 'no') {
     $sWhere[] = " IFNULL(vl.is_sample_rejected, 'no') not like 'yes' ";
}
if (isset($_POST['requestCreatedDatetime']) && trim($_POST['requestCreatedDatetime']) != '') {
     $sRequestCreatedDatetime = '';
     $eRequestCreatedDatetime = '';

     $date = explode("to", $_POST['requestCreatedDatetime']);
     if (isset($date[0]) && trim($date[0]) != "") {
          $sRequestCreatedDatetime = DateUtility::isoDateFormat(trim($date[0]));
     }
     if (isset($date[1]) && trim($date[1]) != "") {
          $eRequestCreatedDatetime = DateUtility::isoDateFormat(trim($date[1]));
     }

     if (trim($sRequestCreatedDatetime) == trim($eRequestCreatedDatetime)) {
          $sWhere[] = '  DATE(vl.request_created_datetime) like "' . $sRequestCreatedDatetime . '"';
     } else {
          $sWhere[] = '  DATE(vl.request_created_datetime) >= "' . $sRequestCreatedDatetime . '" AND DATE(vl.request_created_datetime) <= "' . $eRequestCreatedDatetime . '"';
     }
}

if (isset($_POST['printDate']) && trim($_POST['printDate']) != '') {
     if (trim($sPrintDate) == trim($eTestDate)) {
          $sWhere[] = '  DATE(vl.result_printed_datetime) = "' . $sPrintDate . '"';
     } else {
          $sWhere[] = '  DATE(vl.result_printed_datetime) >= "' . $sPrintDate . '" AND DATE(vl.result_printed_datetime) <= "' . $ePrintDate . '"';
     }
}

if ($_SESSION['instanceType'] == 'remoteuser') {
     if (!empty($_SESSION['facilityMap'])) {
          $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
     }
} elseif (!$_POST['hidesrcofreq']) {
     $sWhere[] = ' vl.result_status != ' . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
}

if (!empty($sWhere)) {
     $_SESSION['vlRequestData']['sWhere'] = $sWhere = implode(" AND ", $sWhere);
     $sQuery = $sQuery . ' WHERE ' . $sWhere;
}

if (!empty($sOrder)) {
     $_SESSION['vlRequestData']['sOrder'] = $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . " ORDER BY " . $sOrder;
}
$_SESSION['vlRequestQuery'] = $sQuery;
[$rResult, $resultCount] = $general->getQueryResultAndCount($sQuery, null, $sLimit, $sOffset);
$_SESSION['vlRequestQueryCount'] = $resultCount;

/*
 * Output
 */
$output = array(
     "sEcho" => intval($_POST['sEcho']),
     "iTotalRecords" => $resultCount,
     "iTotalDisplayRecords" => $resultCount,
     "aaData" => []
);
$editRequest = false;
$syncRequest = false;
if ($usersService->isAllowed("/vl/requests/editVlRequest.php")) {
     $editRequest = true;
     $syncRequest = true;
}

foreach ($rResult as $aRow) {

     $vlResult = '';
     $edit = '';
     $sync = '';
     $barcode = '';

     $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
     $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);

     $patientFname = $aRow['patient_first_name'];
     $patientMname = $aRow['patient_middle_name'];
     $patientLname = $aRow['patient_last_name'];


     $row = [];
     $row[] = $aRow['sample_code'];
     if ($_SESSION['instanceType'] != 'standalone') {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = $aRow['sample_collection_date'];
     $row[] = $aRow['batch_code'];
     if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes' && !empty($general->getGlobalConfig('key'))) {
          $key = base64_decode($general->getGlobalConfig('key'));
          $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
          $patientFname = $general->crypto('decrypt', $patientFname, $key);
          $patientMname = $general->crypto('decrypt', $patientMname, $key);
          $patientLname = $general->crypto('decrypt', $patientLname, $key);
     }
     $row[] = $aRow['patient_art_no'];
     $row[] = trim(implode(" ", array($patientFname, $patientMname, $patientLname)));
     $row[] = $aRow['lab_name'];
     $row[] = $aRow['facility_name'];
     $row[] = $aRow['facility_state'];
     $row[] = $aRow['facility_district'];
     $row[] = $aRow['sample_name'];
     $row[] = $aRow['result'];
     $row[] = $aRow['last_modified_datetime'];
     $row[] = $aRow['status_name'];

     if ($editRequest) {
          if ($_SESSION['instanceType'] == 'vluser' && $aRow['result_status'] == 9) {
               $edit = '';
          } else {
               $edit = '<a href="editVlRequest.php?id=' . base64_encode($aRow['vl_sample_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
          }
          if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes' && !$usersService->isAllowed("/vl/requests/edit-locked-vl-samples")) {
               $edit = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Locked") . '" disabled><em class="fa-solid fa-lock"></em>' . _translate("Locked") . '</a>';
          }
     }

     if (isset($barCodePrinting) && $barCodePrinting != "off") {
          $fac = ($aRow['facility_name']) . " | " . $aRow['sample_collection_date'];
          $barcode = '<br><a href="javascript:void(0)" onclick="printBarcodeLabel(\'' . $aRow[$sampleCode] . '\',\'' . $fac . '\')" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Barcode") . '"><em class="fa-solid fa-barcode"></em> ' . _translate("Barcode") . ' </a>';
     }

     if ($syncRequest && $_SESSION['instanceType'] == 'vluser' && ($aRow['result_status'] == 7 || $aRow['result_status'] == 4)) {
          if ($aRow['data_sync'] == 0) {
               $sync = '<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="' . _translate("Sync this sample") . '" onclick="forceResultSync(\'' . ($aRow['sample_code']) . '\')"> ' . _translate("Sync") . '</a>';
          }
     } else {
          $sync = "";
     }

     $actions = "";
     if ($editRequest) {
          $actions .= $edit;
     }
     if ($syncRequest) {
          $actions .= $sync;
     }
     if (!$_POST['hidesrcofreq']) {
          $row[] = $actions . $barcode;
     }

     $output['aaData'][] = $row;
}
echo json_encode($output);
