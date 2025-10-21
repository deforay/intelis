<?php

use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


try {

    $tableName = "geographical_divisions";
    $primaryKey = "geo_id";

    $aColumns     = ['g.geo_name', 'g.geo_code', 'g.geo_status', 'p.geo_name'];
    $orderColumns = ['g.geo_name', 'g.geo_code', 'p.geo_name', 'g.geo_status'];



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
    $whereSql = !empty($sWhere) ? (' WHERE ' . implode(' AND ', $sWhere)) : '';


    $sQuery = "SELECT
                    g.geo_id,
                    g.geo_name,
                    g.geo_code,
                    g.geo_status,
                    p.geo_name AS parent_name
                FROM geographical_divisions AS g
                LEFT JOIN geographical_divisions AS p
                    ON p.geo_id = CAST(NULLIF(g.geo_parent, '') AS UNSIGNED)
                $whereSql";


    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
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
        $row   = [];
        $row[] = $aRow['geo_name'];
        $row[] = $aRow['geo_code'];
        $row[] = $aRow['parent_name'] ?? '';
        $row[] = $aRow['geo_status'];
        if (_isAllowed("/common/reference/edit-geographical-divisions.php") && $general->isLISInstance() === false) {
            $row[] = '<a href="/common/reference/edit-geographical-divisions.php?id=' .
                base64_encode((string)$aRow['geo_id']) .
                '" class="btn btn-primary btn-xs" style="margin-right:2px;" title="' . _translate("Edit") .
                '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</a>';
        }
        $output['aaData'][] = $row;
    }

    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
    ]);
}
