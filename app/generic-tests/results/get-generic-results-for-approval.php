<?php

use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$tableName = "form_generic";
$primaryKey = "sample_id";

$vlsmFormId = (int) $general->getGlobalConfig('vl_form');


$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'f.facility_name', 'f.facility_code', 's.sample_type_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'f.facility_name', 'f.facility_code', 's.sample_type_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($general->isSTSInstance()) {
     $sampleCode = 'remote_sample_code';
} elseif ($general->isStandaloneInstance()) {
     $aColumns = array('vl.sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'f.facility_name', 'f.facility_code', 's.sample_type_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
     $orderColumns = array('vl.sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'f.facility_name', 'f.facility_code', 's.sample_type_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
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



$sQuery = "SELECT SQL_CALC_FOUND_ROWS *, vl.last_modified_datetime FROM form_generic as vl
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN r_generic_sample_types as s ON s.sample_type_id=vl.specimen_type
          INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
          LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner";



if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
     $sWhere[] =  '  b.batch_code LIKE "%' . $_POST['batchCode'] . '%"';
}
[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
if (!empty($_POST['sampleCollectionDate'])) {
     if (trim((string) $start_date) == trim((string) $end_date)) {
          $sWhere[] = '  DATE(vl.sample_collection_date) = "' . $start_date . '"';
     } else {
          $sWhere[] =  '  DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}
if (isset($_POST['sampleType']) && $_POST['sampleType'] != '') {
     $sWhere[] =  ' s.sample_type_id = "' . $_POST['sampleType'] . '"';
}
if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
     $sWhere[] =  ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['statusFilter']) && $_POST['statusFilter'] != '') {
     if ($_POST['statusFilter'] == 'approvedOrRejected') {
          $sWhere[] = ' vl.result_status IN (4,7)';
     } else if ($_POST['statusFilter'] == 'notApprovedOrRejected') {
          $sWhere[] = ' vl.result_status IN (6,8)';
     }
}

if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] =  " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
}

$sWhere[] =  ' vl.result not like "" AND vl.result is not null ';

if (!empty($sWhere)) {
     $sWhere = ' WHERE ' . implode(" AND ", $sWhere);
}

$sQuery = $sQuery . $sWhere;
if (!empty($sOrder) && $sOrder !== '') {
     $sOrder = preg_replace('/\s+/', ' ', $sOrder);
     $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

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
     "sEcho" => (int) $_POST['sEcho'],
     "iTotalRecords" => $iTotal,
     "iTotalDisplayRecords" => $iFilteredTotal,
     "aaData" => []
);
$vlRequest = false;
$vlView = false;
if ((_isAllowed("/generic-tests/requests/edit-request.php"))) {
     $vlRequest = true;
}
if ((_isAllowed("view-requests.php"))) {
     $vlView = true;
}

foreach ($rResult as $aRow) {
     $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');

     $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_id']));
     $patientMname = ($general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_id']));
     $patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_id']));


     $status = '<select class="form-control"  name="status[]" id="' . $aRow['sample_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,' . $aRow['status_id'] . ')">
               <option value="">' . _translate("-- Select --") . '</option>
               <option value="7" ' . ($aRow['status_id'] == "7" ? "selected=selected" : "") . '>' . _translate("Accepted") . '</option>
               <option value="4" ' . ($aRow['status_id'] == "4"  ? "selected=selected" : "") . '>' . _translate("Rejected") . '</option>
               <option value="2" ' . ($aRow['status_id'] == "2"  ? "selected=selected" : "") . '>' . _translate("Lost") . '</option>
               </select><br><br>';

     $row = [];
     $row[] = '<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['sample_id'] . '"  value="' . $aRow['sample_id'] . '" onclick="toggleTest(this);"  />';
     $row[] = $aRow['sample_code'];
     if (!$general->isStandaloneInstance()) {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = $aRow['sample_collection_date'];
     $row[] = $aRow['batch_code'];
     $row[] = $aRow['patient_id'];
     $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
     $row[] = ($aRow['facility_name']);
     $row[] = ($aRow['sample_type_name']);
     $row[] = $aRow['result'];
     // $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');
     $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);
     // print_r($aRow);die;
     $row[] = $status;

     $output['aaData'][] = $row;
}

echo json_encode($output);
