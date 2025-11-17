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

$count = 0;
$pQuery = "SELECT count(*) as 'count'
            FROM form_eid
            WHERE child_id like '%$artNo%'
            OR child_name like '%$artNo%'
            OR child_surname like '%$artNo%'
            OR caretaker_phone_number like '%$artNo%'";
$pResult = $db->rawQueryOne($pQuery);
echo $pResult['count'];
