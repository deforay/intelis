<?php

use Laminas\Diactoros\ServerRequest;
use App\Services\EidService;
use App\Utilities\DateUtility;
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
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (empty($_POST['manifestCode'])) {
     throw new SystemException('Manifest code is required');
}


// Clear the query count cache for this manifest
if (CommonService::isSessionActive() && !empty($_POST['manifestCode'])) {
     unset($_SESSION['queryCounters']);
}


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

$tableName = "form_eid";
$primaryKey = "eid_id";

$sampleCode = 'sample_code';
$aColumns = ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.child_id', 'vl.child_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'];
$orderColumns = ['vl.sample_code', 'vl.last_modified_datetime', 'vl.sample_collection_date', 'b.batch_code', 'vl.child_id', 'vl.child_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'];
if ($general->isSTSInstance()) {
     $sampleCode = 'remote_sample_code';
} elseif ($general->isStandaloneInstance()) {
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

$sQuery = "SELECT vl.sample_collection_date,
                    vl.eid_id,
                    vl.last_modified_datetime,
                    vl.sample_code,
                    vl.remote_sample_code,
                    vl.result,
                    b.batch_code,
                    vl.child_id,
                    vl.child_name,
                    vl.mother_id,
                    vl.mother_name,
                    vl.facility_id,
                    vl.result_status,
                    f.facility_name,
                    f.facility_state,
                    f.facility_district,
                    ts.status_name
                    FROM form_eid as vl
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
     $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
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
     $row = [];
     $row[] = $aRow['sample_code'];
     if (!$general->isStandaloneInstance()) {
          $row[] = $aRow['remote_sample_code'];
     }
     $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
     $row[] = $aRow['batch_code'];
     $row[] = $aRow['facility_name'];
     $row[] = $aRow['child_id'];
     $row[] = $aRow['child_name'];
     $row[] = $aRow['mother_id'];
     $row[] = $aRow['mother_name'];
     $row[] = $aRow['facility_state'];
     $row[] = $aRow['facility_district'];
     $row[] = $eidResults[$aRow['result']] ?? $aRow['result'] ?? '';
     $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');
     $row[] = $aRow['status_name'];

     $output['aaData'][] = $row;
}
echo json_encode($output);
