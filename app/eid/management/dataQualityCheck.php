<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Utilities\SampleCountUtility;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$gconfig = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();
$key = (string) $general->getGlobalConfig('key');

$tableName = "form_eid";
$primaryKey = "eid_id";


$aColumns = ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.child_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'ts.status_name', 'r_i_p.i_partner_name'];
$orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.child_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'ts.status_name', 'r_i_p.i_partner_name'];
if ($general->isStandaloneInstance()) {
     $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
     $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;

$sOffset = $sLimit = null;
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
     $sOffset = (int) $_POST['iDisplayStart'];
     $sLimit = (int) $_POST['iDisplayLength'];
}



$sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

$sWhere = [];
$columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? null, $aColumns);
if (!empty($columnSearch)) {
     $sWhere[] = $columnSearch;
}




$sQuery = "SELECT SQL_CALC_FOUND_ROWS * FROM form_eid as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_eid_sample_type as s ON s.sample_id=vl.specimen_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";

[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

if (!empty($_POST['sampleCollectionDate'])) {
     if (trim((string) $start_date) === trim((string) $end_date)) {
          $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
     } else {
          $sWhere[] =  ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
     }
}
if (isset($_POST['formField']) && trim((string) $_POST['formField']) !== '') {
     // Whitelist keeps request values out of the SQL as identifiers, and maps
     // each option to the column that actually holds it: state/district live on
     // the joined facility row.
     $checkableFields = [
          'sample_code' => 'vl.sample_code',
          'sample_collection_date' => 'vl.sample_collection_date',
          'sample_batch_id' => 'vl.sample_batch_id',
          'child_id' => 'vl.child_id',
          'child_name' => 'vl.child_name',
          'facility_id' => 'vl.facility_id',
          'facility_state' => 'f.facility_state',
          'facility_district' => 'f.facility_district',
          'specimen_type' => 'vl.specimen_type',
          'result' => 'vl.result',
          'result_status' => 'vl.result_status',
     ];
     $sWhereSub = '';
     $searchArray = explode(",", (string) $_POST['formField']);
     foreach ($searchArray as $search) {
          if (!isset($checkableFields[$search])) {
               continue;
          }
          $column = $checkableFields[$search];
          if ($sWhereSub === "") {
               $sWhereSub .= "  ((";
          } else {
               $sWhereSub .= " AND (";
          }
          if ($search === 'sample_collection_date') {
               $sWhereSub .= $column . " IS NULL";
          } else {
               $sWhereSub .= $column . " ='' OR " . $column . " IS NULL";
          }
          $sWhereSub .= ")";
     }
     if ($sWhereSub !== '') {
          $sWhereSub .= ")";
          $sWhere[] = $sWhereSub;
     }
}
if (isset($_POST['dqImplementingPartner']) && trim((string) $_POST['dqImplementingPartner']) !== '') {
     $sWhere[] = ' vl.implementing_partner = "' . $db->escape(base64_decode((string) $_POST['dqImplementingPartner'])) . '"';
}
//echo $sWhereSub; die;
$dWhere = '';
if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
     $dWhere = $dWhere . " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}

if ($labScope = $general->labScopeWhere('vl')) {
    $sWhere[] = $labScope;
}
if ($labScope = $general->labScopeWhere('vl')) {
    $dWhere .= " AND $labScope";
}

// A cancelled sample was called off before testing, so its data is not a quality
// problem anyone needs to chase.
$sWhere[] = SampleCountUtility::countableWhere('vl');

$sWhere = $sWhere === [] ? "" : ' WHERE ' . implode(' AND ', $sWhere);

$sQuery = $sQuery . ' ' . $sWhere;

$_SESSION['vlIncompleteForm'] = $sQuery;
if (!empty($sOrder) && $sOrder !== '') {
     $sOrder = preg_replace('/\s+/', ' ', $sOrder);
     $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
     $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */
// An empty sort must not leave a dangling comma after the fixed ORDER BY column.
$filterTotalOrder = (!empty($sOrder) && $sOrder !== '') ? ", $sOrder" : '';
$aResultFilterTotal = $db->rawQuery("SELECT vl.eid_id,vl.facility_id,vl.child_name,vl.result,f.facility_name,f.facility_code,s.sample_name,b.batch_code,vl.sample_batch_id,ts.status_name FROM form_eid as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_eid_sample_type as s ON s.sample_id=vl.specimen_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner $sWhere ORDER BY vl.last_modified_datetime DESC $filterTotalOrder");
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal =  $db->rawQuery("select COUNT(eid_id) as total FROM form_eid as vl where vlsm_country_id='" . $gconfig['vl_form'] . "' $dWhere");
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = $aResultTotal[0]['total'];
$_SESSION['vlIncompleteFormCount'] = $iTotal;

/*
                                                       * Output
                                                       */
$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

foreach ($rResult as $aRow) {
     $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');

     $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';

     $childName = ($general->crypto('doNothing', $aRow['child_name'], $aRow[$decrypt]));

     $row = [];
     $row[] = $aRow['sample_code'];
     if (!$general->isStandaloneInstance()) {
          $row[] = $aRow['remote_sample_code'];
     }
     if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
          $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
          $childName = $general->crypto('decrypt', $childName, $key);
     }
     $row[] = $aRow['sample_collection_date'];
     $row[] = $aRow['batch_code'];
     $row[] = trim(($childName ?? '') . ' ' . ($aRow['child_surname'] ?? ''));
     $row[] = ($aRow['facility_name']);
     $row[] = ($aRow['facility_state']);
     $row[] = ($aRow['facility_district']);
     $row[] = ($aRow['sample_name']);
     $row[] = $aRow['result'];
     $row[] = ($aRow['status_name']);
     $row[] = $aRow['i_partner_name'];
     $output['aaData'][] = $row;
}

echo json_encode($output);
