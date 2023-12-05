<?php

use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$artNo = $_POST['artPatientNo'];

$count = 0;
$pQuery = "SELECT COUNT(*) AS count
            FROM form_generic
            WHERE patient_id like '%$artNo%'
            OR patient_first_name like '%$artNo%'
            OR patient_middle_name like '%$artNo%'
            OR patient_last_name like '%$artNo%'";
$pResult = $db->rawQueryOne($pQuery);
echo $pResult['count'];
