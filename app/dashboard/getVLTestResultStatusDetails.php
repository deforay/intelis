<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;


if (session_status() == PHP_SESSION_NONE) {
     session_start();
}



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {

     $db->beginReadOnlyTransaction();

     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);
     $configQuery = "SELECT `value` FROM global_config where name ='vl_form'";
     $configResult = $db->query($configQuery);
     $tableName = "form_vl";
     $primaryKey = "vl_sample_id";


     $aColumns = array('vl.sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result');
     $orderColumns = array('vl.sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result');

     /* Indexed column (used for fast and accurate table cardinality) */
     $sIndexColumn = $primaryKey;

     $sTable = $tableName;
     /*
 * Paging
 */
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



     /*
 * SQL queries
 * Get data to display
 */
     $sQuery = "SELECT * FROM form_vl as vl INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type LEFT JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";
     [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
          $sWhere[] = ' b.batch_code LIKE "%' . $_POST['batchCode'] . '%"';
     }
     if (!empty($_POST['sampleCollectionDate'])) {
          if (trim((string) $start_date) == trim((string) $end_date)) {
               $sWhere[] = ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
          }
     }
     if (isset($_POST['sampleType']) && $_POST['sampleType'] != '') {
          $sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
     }
     if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
          $sWhere[] = ' f.facility_id = "' . $_POST['facilityName'] . '"';
     }

     if (!empty($sWhere)) {
          $sWhere[] = ' vl.result_status = "' . $_POST['status'] . '"';
     } else {
          $sWhere = ' WHERE vl.result_status = "' . $_POST['status'] . '"';
     }

     if (!empty($sWhere)) {
          $sWhere = implode(' AND ', $sWhere);
          $sQuery = $sQuery . ' WHERE ' . $sWhere;
     }
     $sQuery = $sQuery . " ORDER BY vl.last_modified_datetime DESC";
     if (!empty($sOrder)) {
          $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
          $sQuery = $sQuery . "," . $sOrder;
     }

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
     }

     $rResult = $db->rawQuery($sQuery);
     /* Data set length after filtering */
     $aResultFilterTotal = $db->rawQuery("SELECT vl.vl_sample_id,vl.facility_id,vl.patient_first_name,vl.result,f.facility_name,f.facility_code,vl.patient_art_no,s.sample_name,b.batch_code,vl.sample_batch_id,ts.status_name FROM form_vl as vl INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id  LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id $sWhere  ORDER BY vl.last_modified_datetime DESC, $sOrder");
     $iFilteredTotal = count($aResultFilterTotal);

     /* Total data set length */
     $aResultTotal = $db->rawQuery("select COUNT(vl_sample_id) as total FROM form_vl where result_status = '" . $_POST['status'] . "' AND vlsm_country_id = '" . $configResult[0]['value'] . "'");
     // $aResultTotal = $countResult->fetch_row();
     //print_r($aResultTotal);
     $iTotal = $aResultTotal[0]['total'];

     /*
 * Output
 */
     $output = array(
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $iTotal,
          "iTotalDisplayRecords" => $iFilteredTotal,
          "aaData" => []
     );


     foreach ($rResult as $aRow) {
          $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
          $patientFname = $aRow['patient_first_name'];
          $patientMname = $aRow['patient_middle_name'];
          $patientLname = $aRow['patient_last_name'];

          $row = [];
          $row[] = $aRow['sample_code'];
          $row[] = $aRow['sample_collection_date'];
          $row[] = $aRow['batch_code'];
          $row[] = $aRow['patient_art_no'];
          $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['facility_state']);
          $row[] = ($aRow['facility_district']);
          $row[] = ($aRow['sample_name']);
          $row[] = $aRow['result'];
          $output['aaData'][] = $row;
     }

     echo MiscUtility::convertToUtf8AndEncode($output);

     $db->commitTransaction();
} catch (Exception $exc) {
     LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
