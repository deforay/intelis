<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$artNo = $_POST['artPatientNo'];

$searchTerm = '%' . $artNo . '%';
$pQuery = "SELECT count(*) as 'count' FROM form_vl
                WHERE patient_art_no like ?
                OR patient_first_name like ?
                OR patient_middle_name like ?
                OR patient_last_name like ?";
$pResult = $db->rawQueryOne($pQuery, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
echo $pResult['count'];
