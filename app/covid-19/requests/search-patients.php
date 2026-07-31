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
$searchTerm = '%' . $artNo . '%';
$pQuery = "SELECT COUNT(*) AS count
            FROM form_covid19
            WHERE patient_id like ?
            OR patient_name like ?
            OR patient_surname like ?
            OR patient_phone_number like ?";

$pResult = $db->rawQueryOne($pQuery, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
echo $pResult['count'];
