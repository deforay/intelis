<?php

use App\Models\Facilities;
use App\Models\General;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
     session_start();
}
  

$general = new General();
$facilitiesDb = new Facilities();
$facilityMap = $facilitiesDb->getUserFacilityMap($_SESSION['userId']);

$formId = $general->getGlobalConfig('vl_form');

//system config
$systemConfigQuery = "SELECT * FROM system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
     $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
$general = new General();
$tableName = "form_vl";
$primaryKey = "vl_sample_id";
/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/

$aColumns = array('vl.sample_code', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'ts.status_name');
$orderColumns = array('vl.sample_code', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 's.sample_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'ts.status_name');

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
$sWhere= array();
$sWhere[] = " WHERE reason_for_vl_testing != 9999 ";
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
     $sWhere[] =  $sWhereSub;
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
$sQuery = "SELECT SQL_CALC_FOUND_ROWS 
                    vl.sample_code,
                    vl.patient_art_no,
                    vl.sample_collection_date,
                    vl.sample_tested_datetime,
                    s.sample_name,
                    b.batch_code,
                    ts.status_name,
                    f.facility_name,
                    l_f.facility_name as labName,
                    f.facility_code,
                    f.facility_state,
                    f.facility_district,
                    acd.art_code,
                    rs.rejection_reason_name,
                    tr.test_reason_name 
                    FROM form_vl as vl 
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                    LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
                    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type 
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
                    LEFT JOIN r_vl_art_regimen as acd ON acd.art_id=vl.current_regimen 
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
                    LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
                    LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing";

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
$sTestDate = '';
$eTestDate = '';
// if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
//      $s_t_date = explode("to", $_POST['sampleTestDate']);
//      if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
//           $sTestDate = \App\Utilities\DateUtils::isoDateFormat(trim($s_t_date[0]));
//      }
//      if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
//           $eTestDate = \App\Utilities\DateUtils::isoDateFormat(trim($s_t_date[1]));
//      }
// }


if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
     if (trim($start_date) == trim($end_date)) {
          $sWhere[] =  '  DATE(vl.sample_collection_date) = "' . $start_date . '"';
     } else {
          $sWhere[] = '  DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}
// if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
//      if (trim($sTestDate) == trim($eTestDate)) {
//           $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
//      } else {
//           $sWhere = $sWhere . ' AND DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
//      }
// }

if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
     $sWhere[] = ' vl.lab_id = "' . $_POST['facilityName'] . '"';
}

if (isset($_POST['district']) && trim($_POST['district']) != '') {
     $sWhere[] = " f.facility_district_id LIKE " . $_POST['district'];
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
     $sWhere[] =  " f.facility_state_id LIKE " . $_POST['state'];
}

if (count($sWhere) > 0) {
     $sWhere[] =  ' vl.result!="" AND vl.result_status!=9';
} else {
     $sWhere[] = ' WHERE vl.result!="" AND vl.result_status!=9';
}

if (!empty($facilityMap)) {
     $sWhere[] = " vl.facility_id IN ($facilityMap) ";
}

if(isset($sWhere) && count($sWhere)>0)
{
     $sWhere = implode(' AND ',$sWhere);
}
$sQuery = $sQuery . ' ' . $sWhere;

$_SESSION['vlMonitoringResultQuery'] = $sQuery;

if (isset($sOrder) && $sOrder != "") {
     $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
     $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// die($sQuery);
$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */

// $aResultFilterTotal = $db->rawQuery($sQuery);
// $iFilteredTotal = count($aResultFilterTotal);

// /* Total data set length */
// $aResultTotal =  $db->rawQuery($sQuery);
// // $aResultTotal = $countResult->fetch_row();
// $iTotal = count($aResultTotal);


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

foreach ($rResult as $aRow) {

     $patientFname = $general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_art_no']);
     $patientMname = $general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_art_no']);
     $patientLname = $general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_art_no']);

     $row = array();
     $row[] = $aRow['sample_code'];
     $row[] = $aRow['batch_code'];
     $row[] = $aRow['patient_art_no'];
     $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
     $row[] = ($aRow['facility_name']);
     $row[] = ($aRow['facility_state']);
     $row[] = ($aRow['facility_district']);
     $row[] = ($aRow['sample_name']);
     $row[] = $aRow['result'];
     $row[] = ($aRow['status_name']);
     $output['aaData'][] = $row;
}
echo json_encode($output);
