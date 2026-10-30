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
            OR vl.patient_name like ?
            OR vl.patient_surname like ?) ";

// Same scope as the View Requests grid (get-request-list.php)
if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
    $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}
if ($labScope = $general->labScopeWhere('vl')) {
    $sWhere[] = $labScope;
}

$pQuery = "SELECT count(*) as 'count'
            FROM form_tb as vl
            WHERE " . implode(" AND ", $sWhere) . "
            ORDER BY sample_tested_datetime DESC, sample_collection_date DESC
            LIMIT 25";
$pResult = $db->rawQueryOne($pQuery, [$searchTerm, $searchTerm, $searchTerm]);
echo $pResult['count'];
