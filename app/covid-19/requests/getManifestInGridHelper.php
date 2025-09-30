<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$covid19Results = $covid19Service->getCovid19Results();

$tableName = "form_covid19";
$primaryKey = "covid19_id";


// Clear the query count cache for this manifest
if (session_status() === PHP_SESSION_ACTIVE && !empty($_POST['manifestCode'])) {
     unset($_SESSION['queryCounters']);
}


$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'CONCAT(COALESCE(vl.patient_name,""), COALESCE(vl.patient_surname,""))', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($general->isSTSInstance()) {
     $sampleCode = 'remote_sample_code';
} else if ($general->isStandaloneInstance()) {
     $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
     $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}

$sOffset = $sLimit = null;
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
     $sOffset = $_POST['iDisplayStart'];
     $sLimit = $_POST['iDisplayLength'];
}

$sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

$columnSearch = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);

$sWhere = [];
if (!empty($columnSearch) && $columnSearch != '') {
     $sWhere[] = $columnSearch;
}

$sQuery = "SELECT * FROM form_covid19 as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

if (!empty($_POST['manifestCode'])) {
     $sWhere[] = " vl.sample_package_code = '{$_POST['manifestCode']}'";
}

if (!empty($sWhere)) {
     $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
}

if (!empty($sOrder) && $sOrder !== '') {
     $sOrder = preg_replace('/\s+/', ' ', $sOrder);
     $sQuery = $sQuery . " ORDER BY " . $sOrder;
}

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

     $patientFname = $aRow['patient_name'] ?? '';
     $patientLname = $aRow['patient_surname'] ?? '';

     $row = [];
     $row[] = $aRow['sample_code'];
     if (!$general->isStandaloneInstance()) {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
     $row[] = $aRow['batch_code'];
     $row[] = ($aRow['facility_name']);
     $row[] = $aRow['patient_id'];
     $row[] = $patientFname . " " . $patientLname;
     $row[] = $aRow['facility_state'];
     $row[] = $aRow['facility_district'];
     $row[] = $covid19Results[$aRow['result']] ?? $aRow['result'];
     $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');
     $row[] = $aRow['status_name'];

     $output['aaData'][] = $row;
}
echo json_encode($output);
