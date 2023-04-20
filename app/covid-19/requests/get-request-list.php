<?php

use App\Models\General;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
     session_start();
}

$formConfigQuery = "SELECT * FROM global_config";
$configResult = $db->query($formConfigQuery);
$gconfig = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
     $gconfig[$configResult[$i]['name']] = $configResult[$i]['value'];
}
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
     $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}

$general = new General();
$tableName = "form_covid19";
$primaryKey = "covid19_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$sampleCode = 'sample_code';

$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')",  'l_f.facility_name', 'f.facility_name', 'b.batch_code', 'vl.patient_id', 'CONCAT(COALESCE(vl.patient_name,""), COALESCE(vl.patient_surname,""))', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'lab_name', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');

if ($_SESSION['instanceType'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
} else if ($sarr['sc_user_type'] == 'standalone') {
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

$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*, f.*,  ts.status_name, b.batch_code, r.result as resultTxt,
          rtr.test_reason_name,
          rst.sample_name,
          f.facility_name,
          l_f.facility_name as lab_name,
          f.facility_code,
          f.facility_state,
          f.facility_district,
          u_d.user_name as reviewedBy,
          a_u_d.user_name as approvedBy,
          lt_u_d.user_name as labTechnician,
          rs.rejection_reason_name,
          r_f_s.funding_source_name,
          c.iso_name as nationality,
          r_i_p.i_partner_name FROM form_covid19 as vl 
          LEFT JOIN r_countries as c ON vl.patient_nationality=c.id 
          LEFT JOIN r_covid19_results as r ON vl.result=r.result_id 
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
          LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
          LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
          LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
          LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
          LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.lab_technician 
          LEFT JOIN r_covid19_test_reasons as rtr ON rtr.test_reason_id=vl.reason_for_covid19_test 
          LEFT JOIN r_covid19_sample_type as rst ON rst.sample_id=vl.specimen_type 
          LEFT JOIN r_covid19_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
          LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source 
          LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";

//echo $sQuery;die;
$start_date = '';
$end_date = '';
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     $s_c_date = explode("to", $_POST['sampleCollectionDate']);
     if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
          $start_date = DateUtils::isoDateFormat(trim($s_c_date[0]));
     }
     if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
          $end_date = DateUtils::isoDateFormat(trim($s_c_date[1]));
     }
}
$labStartDate = '';
$labEndDate = '';
if (isset($_POST['sampleReceivedDateAtLab']) && trim($_POST['sampleReceivedDateAtLab']) != '') {
     $s_c_date = explode("to", $_POST['sampleReceivedDateAtLab']);
     if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
          $labStartDate = DateUtils::isoDateFormat(trim($s_c_date[0]));
     }
     if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
          $labEnddate = DateUtils::isoDateFormat(trim($s_c_date[1]));
     }
}

$testedStartDate = '';
$testedEndDate = '';
if (isset($_POST['sampleTestedDate']) && trim($_POST['sampleTestedDate']) != '') {
     $s_c_date = explode("to", $_POST['sampleTestedDate']);
     if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
          $testedStartDate = DateUtils::isoDateFormat(trim($s_c_date[0]));
     }
     if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
          $testedEndDate = DateUtils::isoDateFormat(trim($s_c_date[1]));
     }
}

if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
     $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     if (trim($start_date) == trim($end_date)) {
          $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}

if (isset($_POST['sampleReceivedDateAtLab']) && trim($_POST['sampleReceivedDateAtLab']) != '') {
     if (trim($labStartDate) == trim($labEnddate)) {
          $sWhere[] = ' DATE(vl.sample_received_at_vl_lab_datetime) = "' . $labStartDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_received_at_vl_lab_datetime) >= "' . $labStartDate . '" AND DATE(vl.sample_received_at_vl_lab_datetime) <= "' . $labEnddate . '"';
     }
}

if (isset($_POST['sampleTestedDate']) && trim($_POST['sampleTestedDate']) != '') {
     if (trim($testedStartDate) == trim($testedEndDate)) {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $testedStartDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $testedStartDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $testedEndDate . '"';
     }
}
if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
     $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
     $sWhere[] = " f.facility_district_id = '" . $_POST['district'] . "' ";
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
     $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
}
if (isset($_POST['vlLab']) && trim($_POST['vlLab']) != '') {
     $sWhere[] = ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
}


if (isset($_POST['gender']) && trim($_POST['gender']) != '') {
     if (trim($_POST['gender']) == "not_recorded") {
          $sWhere[] = ' vl.patient_gender="not_recorded" OR vl.patient_gender="" OR vl.patient_gender IS NULL';
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
if (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') {
     $sWhere[] = ' vl.funding_source IN ("' . base64_decode($_POST['fundingSource']) . '")';
}
if (isset($_POST['implementingPartner']) && trim($_POST['implementingPartner']) != '') {
     $sWhere[] = ' vl.implementing_partner IN ("' . base64_decode($_POST['implementingPartner']) . '")';
}

if (isset($_POST['srcOfReq']) && trim($_POST['srcOfReq']) != '') {
     $sWhere[] = ' vl.source_of_request like "' . $_POST['srcOfReq'] . '"';
}

if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'result') {
     $sWhere[] = ' vl.result != ""  ';
} else if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'noresult') {
     $sWhere[] = ' (vl.result IS NULL OR vl.result = "") ';
}
if (isset($_POST['patientId']) && trim($_POST['patientId']) != '') {
     $sWhere[] = " vl.patient_id LIKE '%" . $_POST['patientId'] . "%' ";
}
if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
     $sWhere[] = " CONCAT(COALESCE(vl.patient_name,''), COALESCE(vl.patient_surname,'')) like '%" . $_POST['patientName'] . "%'";
}
/* Source of request show model conditions */
if (isset($_POST['dateRangeModel']) && trim($_POST['dateRangeModel']) != '') {
     $sWhere[] = ' DATE(vl.sample_collection_date) like "' . DateUtils::isoDateFormat($_POST['dateRangeModel']) . '"';
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
     $sWhere[] = ' vl.sample_received_at_vl_lab_datetime is not null AND vl.sample_received_at_vl_lab_datetime not like ""';
}
if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == 7) {
     $sWhere[] = ' vl.result is not null AND vl.result not like "" AND result_status = 7';
}
if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == "sent") {
     $sWhere[] = ' vl.result_sent_to_source is not null and vl.result_sent_to_source = "sent"';
}
if ($_SESSION['instanceType'] == 'remoteuser') {
     $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT facility_id ORDER BY facility_id SEPARATOR ',') as facility_id FROM user_facility_map where user_id='" . $_SESSION['userId'] . "'";
     $userfacilityMapresult = $db->rawQuery($userfacilityMapQuery);
     if ($userfacilityMapresult[0]['facility_id'] != null && $userfacilityMapresult[0]['facility_id'] != '') {
          $sWhere[] = " vl.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ")  ";
     }
} else if (!$_POST['hidesrcofreq']) {
     $sWhere[] = 'vl.result_status!=9 ';
}


if (isset($sWhere) && !empty($sWhere)) {
     $_SESSION['covid19RequestData']['sWhere'] = $sWhere = implode(" AND ", $sWhere);
     $sQuery = $sQuery . ' WHERE ' . $sWhere;
}
if (isset($sOrder) && $sOrder != "") {
     $_SESSION['covid19RequestData']['sOrder'] = $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . " ORDER BY " . $sOrder;
}
$_SESSION['covid19RequestSearchResultQuery'] = $sQuery;
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
     "aaData" => array()
);
$editRequest = false;
$syncRequest = false;
if (isset($_SESSION['privileges']) && (in_array("covid-19-edit-request.php", $_SESSION['privileges']))) {
     $editRequest = true;
     $syncRequest = true;
}
// echo "<pre>";print_r($rResult);die;
foreach ($rResult as $aRow) {
     $vlResult = '';
     $edit = '';
     $view = '';
     $sync = '';
     $barcode = '';
     if (isset($aRow['sample_collection_date']) && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
          $aRow['sample_collection_date'] = DateUtils::humanReadableDateFormat($aRow['sample_collection_date']);
     } else {
          $aRow['sample_collection_date'] = '';
     }
     if (isset($aRow['last_modified_datetime']) && trim($aRow['last_modified_datetime']) != '' && $aRow['last_modified_datetime'] != '0000-00-00 00:00:00') {
          $aRow['last_modified_datetime'] = DateUtils::humanReadableDateFormat($aRow['last_modified_datetime'], true);
     } else {
          $aRow['last_modified_datetime'] = '';
     }

     //  $patientFname = ($general->crypto('doNothing',$aRow['patient_first_name'],$aRow['patient_art_no']));
     //  $patientMname = ($general->crypto('doNothing',$aRow['patient_middle_name'],$aRow['patient_art_no']));
     //  $patientLname = ($general->crypto('doNothing',$aRow['patient_surname'],$aRow['patient_art_no']));


     $row = [];

     //$row[]='<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['covid19_id'] . '"  value="' . $aRow['covid19_id'] . '" onclick="toggleTest(this);"  />';
     $row[] = $aRow['sample_code'];
     if ($_SESSION['instanceType'] != 'standalone') {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = $aRow['sample_collection_date'];
     $row[] = $aRow['batch_code'];
     $row[] = ($aRow['lab_name']);
     $row[] = ($aRow['facility_name']);
     $row[] = $aRow['patient_id'];
     $row[] = $aRow['patient_name'] . " " . $aRow['patient_surname'];

     $row[] = ($aRow['facility_state']);
     $row[] = ($aRow['facility_district']);
     $row[] = (is_numeric($aRow['result'])) ? ($aRow['resultTxt']) : ($aRow['result']);
     $row[] = $aRow['last_modified_datetime'];
     $row[] = ($aRow['status_name']);
     //$printBarcode='<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="View" onclick="printBarcode(\''.base64_encode($aRow['covid19_id']).'\');"><em class="fa-solid fa-barcode"></em> Print Barcode</a>';
     //$enterResult='<a href="javascript:void(0);" class="btn btn-success btn-xs" style="margin-right: 2px;" title="Result" onclick="showModal(\'updateVlResult.php?id=' . base64_encode($aRow['covid19_id']) . '\',900,520);"> Result</a>';

     if ($editRequest) {
          $edit = '<a href="covid-19-edit-request.php?id=' . base64_encode($aRow['covid19_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _("Edit") . '</em></a>';
          if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes') {
               if (isset($_SESSION['privileges']) && !in_array("edit-locked-covid19-samples", $_SESSION['privileges'])) {
                    $edit = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _("Locked") . '" disabled><em class="fa-solid fa-lock"></em>' . _("Locked") . '</a>';
               }
          }
     }


     if ($syncRequest && $_SESSION['instanceType'] == 'vluser' && ($aRow['result_status'] == 7 || $aRow['result_status'] == 4)) {
          if ($aRow['data_sync'] == 0) {
               $sync = '<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="' . _("Sync this sample") . '" onclick="forceResultSync(\'' . ($aRow['sample_code']) . '\')"><em class="fa-solid fa-arrows-rotate"></em> ' . _("Sync") . '</a>';
          }
     } else {
          $sync = "";
     }

     if (isset($gconfig['bar_code_printing']) && $gconfig['bar_code_printing'] != "off") {
          $fac = $aRow['patient_id'];
          $barcode = '<br><a href="javascript:void(0)" onclick="printBarcodeLabel(\'' . $aRow[$sampleCode] . '\',\'' . $fac . '\')" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _("Barcode") . '"><em class="fa-solid fa-barcode"></em> ' . _("Barcode") . ' </a>';
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
     // echo '<pre>';print_r($row);die;
     $output['aaData'][] = $row;
}
echo json_encode($output);
