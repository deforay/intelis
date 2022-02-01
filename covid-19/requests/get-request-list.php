<?php
if (session_status() == PHP_SESSION_NONE) {
     session_start();
}
  


$formConfigQuery = "SELECT * FROM global_config";
$configResult = $db->query($formConfigQuery);
$gconfig = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
     $gconfig[$configResult[$i]['name']] = $configResult[$i]['value'];
}
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
     $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}

$general = new \Vlsm\Models\General();
$tableName = "form_covid19";
$primaryKey = "covid19_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$sampleCode = 'sample_code';

$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'CONCAT(vl.patient_name, vl.patient_surname)', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');

if ($_SESSION['instanceType'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
} else if ($sarr['sc_user_type'] == 'standalone') {
     $aColumns = array('vl.sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'CONCAT(vl.patient_name, vl.patient_surname)', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');
     $orderColumns = array('vl.sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
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

$sWhere = array();
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
     $searchArray = explode(" ", $_POST['sSearch']);
     $sWhereSub = "";
     foreach ($searchArray as $search) {
          $sWhereSub .= " (";
          $colSize = count($aColumns);

          for ($i = 0; $i < $colSize; $i++) {
               if ($i < $colSize - 1) {
                    if ($aColumns[$i] == "CONCAT(vl.patient_name, vl.patient_surname)")
                         $sWhereSub .= "vl.patient_name LIKE '%" . ($search) . "%' OR vl.patient_surname LIKE '%" . ($search) . "%' OR ";
                    else
                         $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
               } else {
                    $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
               }
          }
          $sWhereSub .= ")";
     }
     $sWhere[] .= $sWhereSub;
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
$aWhere = '';
$sQuery = '';

$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*, f.*,  ts.status_name, b.batch_code, r.result as resultTxt FROM form_covid19 as vl 
          LEFT JOIN r_covid19_results as r ON vl.result=r.result_id 
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
          LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

//echo $sQuery;die;
$start_date = '';
$end_date = '';
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     $s_c_date = explode("to", $_POST['sampleCollectionDate']);
     if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
          $start_date = $general->dateFormat(trim($s_c_date[0]));
     }
     if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
          $end_date = $general->dateFormat(trim($s_c_date[1]));
     }
}

if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
     $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
}

if (isset($_POST['facilityName']) && trim($_POST['facilityName']) != '') {
     $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
     $sWhere[] = " f.facility_district LIKE '%" . $_POST['district'] . "%' ";
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
     $sWhere[] = " f.facility_state LIKE '%" . $_POST['state'] . "%' ";
}
if (isset($_POST['vlLab']) && trim($_POST['vlLab']) != '') {
     $sWhere[] = ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
}

if (isset($_POST['patientId']) && trim($_POST['patientId']) != '') {
     $sWhere[] = " vl.patient_id LIKE '%" . $_POST['patientId'] . "%' ";
}
if (isset($_POST['gender']) && trim($_POST['gender']) != '') {
     if (trim($_POST['gender']) == "not_recorded") {
          $sWhere[] = ' vl.patient_gender="not_recorded" OR vl.patient_gender="" OR vl.patient_gender IS NULL';
     } else {
          $sWhere[] = ' vl.patient_gender IN ("' . $_POST['gender'] . '")';
     }
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
$whereResult = '';
if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'result') {
     $whereResult = 'vl.result != "" AND ';
} else if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'noresult') {
     $whereResult = '(vl.result IS NULL OR vl.result = "") AND ';
}
if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) != '') {
     $sWhere[] = $whereResult . 'vl.vlsm_country_id="' . $gconfig['vl_form'] . '"';
}
if ($_SESSION['instanceType'] == 'remoteuser') {
     $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT facility_id ORDER BY facility_id SEPARATOR ',') as facility_id FROM vl_user_facility_map where user_id='" . $_SESSION['userId'] . "'";
     $userfacilityMapresult = $db->rawQuery($userfacilityMapQuery);
     if ($userfacilityMapresult[0]['facility_id'] != null && $userfacilityMapresult[0]['facility_id'] != '') {
          $sWhere[] = " vl.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ")  ";
     }
} else {
     $sWhere[] = 'vl.result_status!=9 ';
}
/* Implode all the where fields for filtering the data */
$sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
if (isset($sOrder) && $sOrder != "") {
     $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . " ORDER BY " . $sOrder;
}
$_SESSION['covid19RequestSearchResultQuery'] = $sQuery;
if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// echo $sQuery;die;
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
          $xplodDate = explode(" ", $aRow['sample_collection_date']);
          $aRow['sample_collection_date'] = $general->humanDateFormat($xplodDate[0]);
     } else {
          $aRow['sample_collection_date'] = '';
     }
     if (isset($aRow['last_modified_datetime']) && trim($aRow['last_modified_datetime']) != '' && $aRow['last_modified_datetime'] != '0000-00-00 00:00:00') {
          $xplodDate = explode(" ", $aRow['last_modified_datetime']);
          $aRow['last_modified_datetime'] = $general->humanDateFormat($xplodDate[0]) . " " . $xplodDate[1];
     } else {
          $aRow['last_modified_datetime'] = '';
     }

     //  $patientFname = ucwords($general->crypto('decrypt',$aRow['patient_first_name'],$aRow['patient_art_no']));
     //  $patientMname = ucwords($general->crypto('decrypt',$aRow['patient_middle_name'],$aRow['patient_art_no']));
     //  $patientLname = ucwords($general->crypto('decrypt',$aRow['patient_last_name'],$aRow['patient_art_no']));


     $row = array();

     //$row[]='<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['covid19_id'] . '"  value="' . $aRow['covid19_id'] . '" onclick="toggleTest(this);"  />';
     $row[] = $aRow['sample_code'];
     if ($sarr['sc_user_type'] != 'standalone') {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = $aRow['sample_collection_date'];
     $row[] = $aRow['batch_code'];
     $row[] = ucwords($aRow['facility_name']);
     $row[] = $aRow['patient_id'];
     $row[] = $aRow['patient_name'] . " " . $aRow['patient_surname'];

     $row[] = ucwords($aRow['facility_state']);
     $row[] = ucwords($aRow['facility_district']);
     $row[] = (is_numeric($aRow['result'])) ? ucwords($aRow['resultTxt']) : ucwords($aRow['result']);
     $row[] = $aRow['last_modified_datetime'];
     $row[] = ucwords($aRow['status_name']);
     //$printBarcode='<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="View" onclick="printBarcode(\''.base64_encode($aRow['covid19_id']).'\');"><i class="fa fa-barcode"> Print Barcode</i></a>';
     //$enterResult='<a href="javascript:void(0);" class="btn btn-success btn-xs" style="margin-right: 2px;" title="Result" onclick="showModal(\'updateVlResult.php?id=' . base64_encode($aRow['covid19_id']) . '\',900,520);"> Result</a>';

     if ($editRequest) {
          $edit = '<a href="covid-19-edit-request.php?id=' . base64_encode($aRow['covid19_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="'. _("Edit").'"><i class="fa fa-pencil"> '. _("Edit").'</i></a>';
          if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes') {
               if (isset($_SESSION['privileges']) && !in_array("edit-locked-covid19-samples", $_SESSION['privileges'])) {
                    $edit = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="'. _("Locked").'" disabled><i class="fa fa-lock"> '. _("Locked").'</i></a>';
               }
          }
     }


     if ($syncRequest && $_SESSION['instanceType'] == 'vluser' && ($aRow['result_status'] == 7 || $aRow['result_status'] == 4)) {
          if ($aRow['data_sync'] == 0) {
               $sync = '<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="'. _("Sync this sample").'" onclick="forceResultSync(\'' . ($aRow['sample_code']) . '\')"><i class="fa fa-refresh"> '. _("Sync").'</i></a>';
          }
     } else {
          $sync = "";
     }

     if (isset($gconfig['bar_code_printing']) && $gconfig['bar_code_printing'] != "off") {
          $fac = ucwords($aRow['facility_name']) . " | " . $aRow['sample_collection_date'];
          $barcode = '<br><a href="javascript:void(0)" onclick="printBarcodeLabel(\'' . $aRow[$sampleCode] . '\',\'' . $fac . '\')" class="btn btn-default btn-xs" style="margin-right: 2px;" title="'. _("Barcode").'"><i class="fa fa-barcode"> </i> '. _("Barcode").' </a>';
     }
     $actions = "";
     if ($editRequest) {
          $actions .= $edit;
     }
     if ($syncRequest) {
          $actions .= $sync;
     }
     $row[] = $actions . $barcode;
     // echo '<pre>';print_r($row);die;
     $output['aaData'][] = $row;
}
echo json_encode($output);
