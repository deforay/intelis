<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

if(isset($_GET['code']))
{
    $table = $_GET['testType'];
    $sampleCode = $_GET['code'];
    $sql = "SELECT DISTINCT sample_code FROM $table WHERE sample_code like '$sampleCode%' OR remote_sample_code like '$sampleCode%'";
    $result = $db->rawQuery($sql);
    echo json_encode($result);
}
