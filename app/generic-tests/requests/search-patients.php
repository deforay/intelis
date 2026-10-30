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

$artNo = $_POST['artPatientNo'];

$count = 0;
$searchTerm = '%' . $artNo . '%';
$sWhere = [];
$sWhere[] = " (vl.patient_id like ?
            OR vl.patient_first_name like ?
            OR vl.patient_middle_name like ?
            OR vl.patient_last_name like ?) ";

// Same scope as the View Requests grid (get-request-list.php)
if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
    $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}
if ($labScope = $general->labScopeWhere('vl')) {
    $sWhere[] = $labScope;
}

$pQuery = "SELECT COUNT(*) AS count
            FROM form_generic as vl
            WHERE " . implode(" AND ", $sWhere);
$pResult = $db->rawQueryOne($pQuery, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
echo $pResult['count'];
