<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "r_vl_results";
$primaryKey = "result_id";


$aColumns = ['result', 'status'];
$orderColumns = ['', 'result', 'status', ''];

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

$sQuery = "SELECT r.*, GROUP_CONCAT(i.machine_name SEPARATOR ', ') AS machine_names
            FROM r_vl_results r
            LEFT JOIN instruments i ON JSON_CONTAINS(r.available_for_instruments, JSON_QUOTE(i.instrument_id))";


if (!empty($sWhere)) {
    $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
}

$sQuery = $sQuery . ' GROUP BY r.result_id';

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
    $machines = "";
    $status = '<select class="form-control" name="status[]" id="' . $aRow['result_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,\'' . $aRow['status'] . '\')">
                <option value="active" ' . ($aRow['status'] == "active" ? "selected=selected" : "") . '>' . _translate("Active") . '</option>
                <option value="inactive" ' . ($aRow['status'] == "inactive" ? "selected=selected" : "") . '>' . _translate("Inactive") . '</option>
                </select><br><br>';
    $row = [];
    $row[] = "";
    $row[] = $aRow['result'];
    $row[] = $aRow['machine_names'];

    if (_isAllowed("/vl/reference/vl-results.php")) {
        $row[] = $status;
        $row[] = '<a href="edit-vl-results.php?id=' . base64_encode((string) $aRow['result_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
    } else {
        $row[] = $aRow['status'];
    }

    $output['aaData'][] = $row;
}

echo json_encode($output);
