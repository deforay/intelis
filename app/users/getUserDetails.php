<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$aColumns = $orderColumns = ['ud.user_name', 'ud.login_id', 'ud.email', 'r.role_name', 'ud.status'];

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

$sQuery = "SELECT ud.user_id,
            ud.user_name,
            ud.login_id,
            ud.interface_user_name,
            ud.email,
            ud.status,
            r.role_name
            FROM user_details as ud
            LEFT JOIN roles as r ON ud.role_id=r.role_id ";

$sWhere = empty($sWhere) ? "" : ' WHERE ' . implode(' AND ', $sWhere);
$sQuery = "$sQuery $sWhere";
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

    if (!empty($aRow['interface_user_name'])) {
        $interfaceUsers = implode(", ", json_decode((string) $aRow['interface_user_name'], true));
        $aRow['user_name'] = $aRow['user_name'] . "<br><small>[" . $interfaceUsers . "]</small>";
    }
    $row = [];
    $row[] = $aRow['user_name'];
    $row[] = $aRow['login_id'];
    $row[] = $aRow['email'];
    $row[] = $aRow['role_name'];
    $row[] = $aRow['status'];
    if (_isAllowed("/users/editUser.php")) {
        $row[] = '<a href="editUser.php?id=' . base64_encode((string) $aRow['user_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
    }
    $output['aaData'][] = $row;
}
header('Content-Type: application/json');
echo JsonUtility::encodeUtf8Json($output);
