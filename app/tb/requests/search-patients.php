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
$pQuery = "SELECT count(*) as 'count'
            FROM form_tb
            WHERE patient_id like '%$artNo%'
            OR patient_name like '%$artNo%'
            OR patient_surname like '%$artNo%'
            ORDER BY sample_tested_datetime DESC, sample_collection_date DESC
            LIMIT 25";
$pResult = $db->rawQueryOne($pQuery);
echo $pResult['count'];
