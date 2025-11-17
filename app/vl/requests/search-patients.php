<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$artNo = $_POST['artPatientNo'];

$pQuery = "SELECT count(*) as 'count' FROM form_vl
                WHERE patient_art_no like '%$artNo%'
                OR patient_first_name like '%$artNo%'
                OR patient_middle_name like '%$artNo%'
                OR patient_last_name like '%$artNo%'";
$pResult = $db->rawQueryOne($pQuery);
echo $pResult['count'];
