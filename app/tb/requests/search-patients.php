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
$pQuery = "SELECT count(*) as 'count'
            FROM form_tb
            WHERE patient_id like ?
            OR patient_name like ?
            OR patient_surname like ?
            ORDER BY sample_tested_datetime DESC, sample_collection_date DESC
            LIMIT 25";
$pResult = $db->rawQueryOne($pQuery, [$searchTerm, $searchTerm, $searchTerm]);
echo $pResult['count'];
