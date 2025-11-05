<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$key = (string) $general->getGlobalConfig('key');

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


$tableName = "form_cd4";
$primaryKey = "cd4_id";

$vlsmFormId = (int) $general->getGlobalConfig('vl_form');



$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_code', 's.sample_name', 'vl.cd4_result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_code', 's.sample_name', 'vl.cd4_result', 'vl.last_modified_datetime', 'ts.status_name');
if ($general->isSTSInstance()) {
     $sampleCode = 'remote_sample_code';
} elseif ($general->isStandaloneInstance()) {
     $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
     $orderColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
}

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


$sQuery = "SELECT vl.*,
               f.facility_name,
               s.sample_name,
               b.batch_code, ts.status_id
               FROM form_cd4 as vl
               LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
               LEFT JOIN r_cd4_sample_types as s ON s.sample_id=vl.specimen_type
               LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
               LEFT JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id
               LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
               LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner";


[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');


if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
     $sWhere[] =  '  b.batch_code LIKE "%' . $_POST['batchCode'] . '%"';
}
if (!empty($_POST['sampleCollectionDate'])) {
     if (trim((string) $start_date) == trim((string) $end_date)) {
          $sWhere[] = '  DATE(vl.sample_collection_date) = "' . $start_date . '"';
     } else {
          $sWhere[] =  '  DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}
if (isset($_POST['sampleType']) && $_POST['sampleType'] != '') {
     $sWhere[] =  ' s.sample_id = "' . $_POST['sampleType'] . '"';
}
if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
     $sWhere[] =  ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['statusFilter']) && $_POST['statusFilter'] != '') {
     if ($_POST['statusFilter'] == 'approvedOrRejected') {
          $sWhere[] = ' vl.result_status IN (4,7)';
     } elseif ($_POST['statusFilter'] == 'notApprovedOrRejected') {
          $sWhere[] = ' vl.result_status IN (6,8)';
     }
}

if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] =  " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
}

$sWhere[] =  ' vl.cd4_result not like "" AND vl.cd4_result is not null ';

if (!empty($sWhere)) {
     $sWhere = ' WHERE ' . implode(" AND ", $sWhere);
}

$sQuery = $sQuery . $sWhere;
if (!empty($sOrder) && $sOrder !== '') {
     $sOrder = preg_replace('/\s+/', ' ', $sOrder);
     $sQuery = "$sQuery ORDER BY $sOrder";
}

if (isset($sLimit) && isset($sOffset)) {
     $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
}

[$rResult, $resultCount] = $db->getDataAndCount($sQuery);

$output = [
     "sEcho" => (int) $_POST['sEcho'],
     "iTotalRecords" => $resultCount,
     "iTotalDisplayRecords" => $resultCount,
     "aaData" => []
];

foreach ($rResult as $aRow) {

     $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');

     $status = '<select class="form-control"  name="status[]" id="' . $aRow['cd4_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,' . $aRow['status_id'] . ')">
               <option value="">' . _translate("-- Select --") . '</option>
               <option value="' . SAMPLE_STATUS\ACCEPTED . '" ' . ($aRow['status_id'] == SAMPLE_STATUS\ACCEPTED ? "selected=selected" : "") . '>' . _translate("Accepted") . '</option>
               <option value="' . SAMPLE_STATUS\REJECTED . '" ' . ($aRow['status_id'] == SAMPLE_STATUS\REJECTED  ? "selected=selected" : "") . '>' . _translate("Rejected") . '</option>
               <option value="' . SAMPLE_STATUS\LOST_OR_MISSING . '" ' . ($aRow['status_id'] == SAMPLE_STATUS\LOST_OR_MISSING  ? "selected=selected" : "") . '>' . _translate("Lost") . '</option>
               </select><br><br>';

     $row = [];
     $row[] = '<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['cd4_id'] . '"  value="' . $aRow['cd4_id'] . '" onclick="toggleTest(this);"  />';
     $row[] = $aRow['sample_code'];
     if (!$general->isStandaloneInstance()) {
          $row[] = $aRow['remote_sample_code'];
     }
     if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
          $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
          $patientFname = $general->crypto('decrypt', $patientFname, $key);
          $patientMname = $general->crypto('decrypt', $patientMname, $key);
          $patientLname = $general->crypto('decrypt', $patientLname, $key);
     } else {
          $patientFname = $aRow['patient_first_name'];
          $patientMname = $aRow['patient_middle_name'];
          $patientLname = $aRow['patient_last_name'];
     }

     $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
     $row[] = $aRow['batch_code'];
     $row[] = $aRow['patient_art_no'];
     $row[] = $patientFname . " " . $patientMname . " " . $patientLname;
     $row[] = $aRow['facility_name'];
     $row[] = $aRow['sample_name'];
     $row[] = $aRow['cd4_result'];
     $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true);
     //$row[] = $status;

     $output['aaData'][] = $row;
}

echo json_encode($output);
