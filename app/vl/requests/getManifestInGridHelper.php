<?php

// Force no caching
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");


// /vl/requests/getManifestInGridHelper.php
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

// Clear the query count cache for this manifest
if (session_status() === PHP_SESSION_ACTIVE && !empty($_POST['manifestCode'])) {
     unset($_SESSION['queryCounters']);
}

if (empty($_POST['manifestCode'])) {
     throw new SystemException(_translate('Manifest code is required'));
}

$_POST['manifestCode'] = trim($_POST['manifestCode']);

$tableName = "form_vl";
$primaryKey = "vl_sample_id";

$sampleCode = 'sample_code';
$aColumns = ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'];
$orderColumns = ['vl.sample_code', 'vl.last_modified_datetime', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'];
if ($general->isSTSInstance()) {
     $sampleCode = 'remote_sample_code';
} elseif ($general->isStandaloneInstance()) {
     $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
     $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}


/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;

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

$sQuery = "SELECT vl.sample_collection_date,
                    vl.vl_sample_id,
                    vl.last_modified_datetime,
                    vl.sample_code,
                    vl.remote_sample_code,
                    vl.result,
                    b.batch_code,
                    vl.patient_first_name,
                    vl.patient_middle_name,
                    vl.patient_last_name,
                    vl.patient_art_no,
                    vl.facility_id,
                    vl.specimen_type,
                    vl.result_status,
                    f.facility_name,
                    f.facility_state,
                    f.facility_district,
                    s.sample_name,
                    ts.status_name FROM form_vl as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
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

     $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '', true);
     $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true);

     $patientFname = $aRow['patient_first_name'];
     $patientMname = $aRow['patient_middle_name'];
     $patientLname = $aRow['patient_last_name'];

     $row = [];
     $row[] = $aRow['sample_code'];
     if (!$general->isStandaloneInstance()) {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = $aRow['sample_collection_date'];
     $row[] = $aRow['batch_code'];
     $row[] = $aRow['patient_art_no'];
     $row[] = "$patientFname $patientMname $patientLname";
     $row[] = $aRow['facility_name'];
     $row[] = $aRow['facility_state'];
     $row[] = $aRow['facility_district'];
     $row[] = $aRow['sample_name'];
     $row[] = $aRow['result'];
     $row[] = $aRow['last_modified_datetime'];
     $row[] = $aRow['status_name'];
     $output['aaData'][] = $row;
}


// Log the actual query being executed
error_log("Query: " . $sQuery);
error_log("Result count: " . $resultCount);

echo JsonUtility::encodeUtf8Json($output);
