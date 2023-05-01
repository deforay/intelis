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
$tableName = "form_vl";
$primaryKey = "vl_sample_id";
$country = $general->getGlobalConfig('vl_form');


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$facilityMap = $facilitiesService->getUserFacilityMap($_SESSION['userId']);

$sarr = $general->getSystemConfig();
/* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */

$aColumns = array('f.facility_state', 'f.facility_district', 'f.facility_name', 'f.facility_code', "DATE_FORMAT(vl.sample_tested_datetime,'%d-%b-%Y')", 'vl.lab_tech_comments');
$orderColumns = array('f.facility_state', 'f.facility_district', 'f.facility_name', 'sample_tested_datetime', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'vl.lab_tech_comments');

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
$sWhere[] = " vl.lab_id is NOT NULL AND reason_for_vl_testing != 9999 ";
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
	
		vl.facility_id,f.facility_code,f.facility_state,f.facility_district,f.facility_name,
		
		SUM(CASE
          WHEN (reason_for_sample_rejection IS NOT NULL AND reason_for_sample_rejection!= '' AND reason_for_sample_rejection!= 0) THEN 1
          ELSE 0
          END) AS rejections,
		SUM(CASE 
          WHEN (patient_age_in_years >= 0 AND patient_age_in_years <= 15 AND vl.vl_result_category like 'suppressed') THEN 1
          ELSE 0
          END) AS lt15suppressed, 
		SUM(CASE 
          WHEN (patient_age_in_years >= 0 AND patient_age_in_years <= 15 AND vl.vl_result_category like 'not suppressed' ) THEN 1
          ELSE 0
          END) AS lt15NotSuppressed,
		SUM(CASE 
          WHEN (patient_age_in_years > 15 AND patient_gender IN ('m','male','M','MALE') AND vl.vl_result_category like 'suppressed') THEN 1
          ELSE 0
          END) AS gt15suppressedM,
		SUM(CASE 
          WHEN (patient_age_in_years > 15 AND patient_gender IN ('m','male','M','MALE') AND vl.vl_result_category like 'not suppressed' ) THEN 1
          ELSE 0
          END) AS gt15NotSuppressedM,
		SUM(CASE 
          WHEN (patient_age_in_years > 15 AND patient_gender IN ('f','female','F','FEMALE') AND vl.vl_result_category like 'suppressed') THEN 1
          ELSE 0
          END) AS gt15suppressedF,
		SUM(CASE 
          WHEN (patient_age_in_years > 15 AND patient_gender IN ('f','female','F','FEMALE') AND vl.vl_result_category like 'not suppressed' ) THEN 1
          ELSE 0
          END) AS gt15NotSuppressedF,	
		SUM(CASE 
          WHEN ((is_patient_pregnant like 'yes') AND vl.vl_result_category like 'suppressed') THEN 1
          ELSE 0
          END) AS pregSuppressed,	
		SUM(CASE 
          WHEN ((is_patient_pregnant like 'yes') AND vl.vl_result_category like 'not suppressed' ) THEN 1
          ELSE 0
          END) AS pregNotSuppressed,           	           	
		SUM(CASE 
          WHEN (patient_gender = '' OR patient_gender = 'unknown' OR patient_gender = 'unreported' OR patient_gender is NULL AND vl.vl_result_category like 'suppressed') THEN 1
          ELSE 0
          END) AS genderUnknownSuppressed, 
		SUM(CASE 
          WHEN (patient_gender = '' OR patient_gender = 'unknown' OR patient_gender = 'unreported' OR patient_gender is NULL AND vl.vl_result_category like 'not suppressed') THEN 1
          ELSE 0
          END) AS genderUnknownNotSuppressed,               
		SUM(CASE 
          WHEN (vl.vl_result_category like 'suppressed') THEN 1
          ELSE 0
          END) AS totalLessThan1000,     
		SUM(CASE 
          WHEN (vl.vl_result_category like 'not suppressed') THEN 1
          ELSE 0
          END) AS totalGreaterThan1000,
		COUNT(result) as total
        FROM form_vl as vl 
        INNER JOIN facility_details as f ON f.facility_id=vl.facility_id
        INNER JOIN facility_details as testingLab ON vl.lab_id=testingLab.facility_id ";

$start_date = '';
$end_date = '';
if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
  $s_t_date = explode("to", $_POST['sampleTestDate']);
  if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
    $start_date = DateUtility::isoDateFormat(trim($s_t_date[0]));
  }
  if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
    $end_date = DateUtility::isoDateFormat(trim($s_t_date[1]));
  }
}

if (isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate']) != '') {
  if (trim($start_date) == trim($end_date)) {
    $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
  } else {
    $sWhere[] =  ' DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
  }
}


if (isset($_POST['lab']) && trim($_POST['lab']) != '') {
  $sWhere[] =  " vl.lab_id IN (" . $_POST['lab'] . ")";
}

if ($_SESSION['instanceType'] == 'remoteuser') {
  if (!empty($facilityMap)) {
    $sWhere[] =  " vl.facility_id IN (" . $facilityMap . ")";
  }
}

if (isset($sWhere) && !empty($sWhere)) {
  $sWhere = implode(" AND ", $sWhere);
}


$sQuery = $sQuery . ' WHERE ' . $sWhere;
$sQuery = $sQuery . ' GROUP BY vl.lab_id, vl.facility_id';


if (isset($sOrder) && $sOrder != "") {
  $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
  $sQuery = $sQuery . ' order by ' . $sOrder;
}
//die($sQuery);die;
$_SESSION['vlStatisticsQuery'] = $sQuery;

if (isset($sLimit) && isset($sOffset)) {
  $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//echo $sQuery;die;
$sResult = $db->rawQuery($sQuery);

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

foreach ($sResult as $aRow) {
  $row = [];
  $row[] = ($aRow['facility_state']);
  $row[] = ($aRow['facility_district']);
  $row[] = ($aRow['facility_name']);
  // $row[] = $aRow['facility_code'];
  $row[] = $aRow['rejections'];
  $row[] = $aRow['lt15suppressed'];
  $row[] = $aRow['lt15NotSuppressed'];
  $row[] = $aRow['gt15suppressedM'];
  $row[] = $aRow['gt15NotSuppressedM'];
  $row[] = $aRow['gt15suppressedF'];
  $row[] = $aRow['gt15NotSuppressedF'];
  $row[] = $aRow['pregSuppressed'];
  $row[] = $aRow['pregNotSuppressed'];
  $row[] = $aRow['genderUnknownSuppressed'];
  $row[] = $aRow['genderUnknownNotSuppressed'];
  $row[] = $aRow['totalLessThan1000'];
  $row[] = $aRow['totalGreaterThan1000'];
  $row[] = $aRow['total'];
  $output['aaData'][] = $row;
}

echo json_encode($output);
