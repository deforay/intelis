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

$count = 0;
$pQuery = "SELECT COUNT(*) AS count
            FROM form_covid19
            WHERE patient_id like '%$artNo%'
            OR patient_name like '%$artNo%'
            OR patient_surname like '%$artNo%'
            OR patient_phone_number like '%$artNo%'";

$pResult = $db->rawQueryOne($pQuery);
echo $pResult['count'];
