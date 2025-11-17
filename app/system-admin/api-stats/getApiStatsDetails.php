<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "track_api_requests";
$primaryKey = "api_track_id";


$aColumns = ['requested_on', 'number_of_records', 'request_type', 'test_type', 'api_url', 'data_format'];

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

$sQuery = "SELECT api.api_track_id,
                api.requested_by,
                api.requested_on,
                api.number_of_records,
                api.request_type,
                api.test_type,
                api.api_url,
                api.api_params,
                api.facility_id,
                api.data_format 
                FROM track_api_requests as api";


if (!empty($sWhere)) {
    $sWhere = implode(" AND ", $sWhere);
    $sQuery = "$sQuery $sWhere";
}

if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
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
    $row = [];
    $row[] = $aRow['requested_on'];
    $row[] = $aRow['number_of_records'];
    $row[] = $aRow['request_type'];
    $row[] = $aRow['test_type'];
    $row[] = $aRow['api_url'];
    $row[] = $aRow['data_format'];
    $output['aaData'][] = $row;
}

echo json_encode($output);
