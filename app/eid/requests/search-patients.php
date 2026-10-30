<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$artNo = $_POST['childIdNo'] ?? $_POST['artPatientNo'] ?? '';
$motherNo = $_POST['motherNo'] ?? '';

// Same scope as the View Requests grid (get-request-list.php)
$scopeWhere = '';
if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
    $scopeWhere .= " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}
if ($labScope = $general->labScopeWhere('vl')) {
    $scopeWhere .= " AND $labScope";
}

if (!empty($artNo) && $artNo != '') {

    $count = 0;
    $searchTerm = '%' . $artNo . '%';
    $pQuery = "SELECT count(*) as 'count'
            FROM form_eid as vl
            WHERE (vl.child_id like ?
            OR vl.child_name like ?
            OR vl.child_surname like ?
            OR vl.caretaker_phone_number like ?) $scopeWhere";
    $pResult = $db->rawQueryOne($pQuery, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    echo $pResult['count'];

} else if(!empty($motherNo) && $motherNo != '') {
    $count = 0;
    $searchTerm = '%' . $motherNo . '%';
    $pQuery = "SELECT count(*) as 'count'
            FROM form_eid as vl
            WHERE (vl.mother_id like ?
            OR vl.mother_name like ?
            OR vl.mother_surname like ?
            OR vl.caretaker_address like ?
            OR vl.caretaker_phone_number like ?) $scopeWhere";
    $pResult = $db->rawQueryOne($pQuery, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    echo $pResult['count'];
} else {
    echo '0';
}