<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "form_vl";
$primaryKey = "vl_sample_id";

$sarr = $general->getSystemConfig();


$aColumns = ['f.facility_state', 'f.facility_district', 'f.facility_name'];
$orderColumns = ['f.facility_state', 'f.facility_district', 'f.facility_name', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

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



$sWhere = [];
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
  $searchArray = explode(" ", (string) $_POST['sSearch']);
  $sWhereSub = "";
  foreach ($searchArray as $search) {
    if ($sWhereSub === "") {
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

$sQuery = "SELECT

		vl.facility_id,f.facility_code,f.facility_state,f.facility_district,f.facility_name,

      SUM(CASE
        WHEN (patient_gender IN ('f','female','F','FEMALE')) THEN 1
                  ELSE 0
                END) AS totalFemale,
      SUM(CASE
              WHEN ((is_patient_pregnant ='Yes' OR is_patient_pregnant ='YES' OR is_patient_pregnant ='yes') AND ((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null  )) THEN 1
              ELSE 0
            END) AS pregSuppressed,
      SUM(CASE
              WHEN ((is_patient_pregnant ='Yes' OR is_patient_pregnant ='YES' OR is_patient_pregnant ='yes')  AND vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null   ) THEN 1
              ELSE 0
            END) AS pregNotSuppressed,
      SUM(CASE
              WHEN ((is_patient_breastfeeding ='Yes' OR is_patient_breastfeeding ='YES' OR is_patient_breastfeeding ='yes') AND ((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null   )) THEN 1
              ELSE 0
            END) AS bfsuppressed,
      SUM(CASE
              WHEN ((is_patient_breastfeeding ='Yes' OR is_patient_breastfeeding ='YES' OR is_patient_breastfeeding ='yes') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null   ) THEN 1
              ELSE 0
            END) AS bfNotSuppressed,
      SUM(CASE
              WHEN (patient_age_in_years > 15 AND patient_gender IN ('f','female','F','FEMALE') AND ((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null  )) THEN 1
              ELSE 0
            END) AS gt15suppressedF,
        SUM(CASE
              WHEN (patient_age_in_years > 15 AND patient_gender IN ('f','female','F','FEMALE') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null  ) THEN 1
              ELSE 0
            END) AS gt15NotSuppressedF,
      SUM(CASE
        WHEN ((patient_age_in_years >= 0 AND patient_age_in_years <= 15) AND ((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null   )) THEN 1
                  ELSE 0
                END) AS lt15suppressed,
      SUM(CASE
              WHEN ((patient_age_in_years >= 0 AND patient_age_in_years <= 15) AND vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null   ) THEN 1
              ELSE 0
            END) AS lt15NotSuppressed,
      SUM(CASE
        WHEN ((patient_age_in_years ='' OR patient_age_in_years IS NULL) AND ((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null   )) THEN 1
                  ELSE 0
                END) AS ltUnKnownAgesuppressed,
      SUM(CASE
              WHEN ((patient_age_in_years ='' OR patient_age_in_years IS NULL)  AND vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null   ) THEN 1
              ELSE 0
            END) AS ltUnKnownAgeNotSuppressed
      FROM form_vl as vl RIGHT JOIN facility_details as f ON f.facility_id=vl.facility_id where vl.patient_gender IN ('f','female','F','FEMALE')";



if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
  [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '', includeTime: true);
  $sWhere[] = "vl.sample_tested_datetime BETWEEN '$startDate' AND '$endDate'";
}

if (isset($_POST['sampleCollectionDate']) && trim((string) $_POST['sampleCollectionDate']) !== '') {
  [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '', includeTime: true);
  $sWhere[] = "vl.sample_collection_date BETWEEN '$startDate' AND '$endDate'";
}

if (isset($_POST['lab']) && trim((string) $_POST['lab']) !== '') {
  $sWhere[] =  "  vl.lab_id IN (" . $_POST['lab'] . ")";
}
if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
  $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}

if ($sWhere !== []) {
  $sWhere = implode(' AND ', $sWhere);
}

$sQuery = $sQuery . ' AND ' . $sWhere;
$sQuery .= ' GROUP BY vl.facility_id';


if (!empty($sOrder) && $sOrder !== '') {
  $sOrder = preg_replace('/\s+/', ' ', $sOrder);
  $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}
//die($sQuery);
$_SESSION['vlStatisticsFemaleQuery'] = $sQuery;

if (isset($sLimit) && isset($sOffset)) {
  $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

[$rResult, $resultCount] = $db->getDataAndCount($sQuery);

$output = [
  "sEcho" => (int) $_POST['sEcho'],
  "iTotalRecords" => $resultCount,
  "iTotalDisplayRecords" => $resultCount,
  "aaData" => []
];

foreach ($rResult as $aRow) {
  $row = [];
  $row[] = $aRow['facility_state'];
  $row[] = $aRow['facility_district'];
  $row[] = $aRow['facility_name'];
  $row[] = $aRow['totalFemale'];
  $row[] = $aRow['pregSuppressed'];
  $row[] = $aRow['pregNotSuppressed'];
  $row[] = $aRow['bfsuppressed'];
  $row[] = $aRow['bfNotSuppressed'];
  $row[] = $aRow['gt15suppressedF'];
  $row[] = $aRow['gt15NotSuppressedF'];
  $row[] = $aRow['ltUnKnownAgesuppressed'];
  $row[] = $aRow['ltUnKnownAgeNotSuppressed'];
  $row[] = $aRow['lt15suppressed'];
  $row[] = $aRow['lt15NotSuppressed'];
  $output['aaData'][] = $row;
}

echo json_encode($output);
