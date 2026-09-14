<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Services\TestsService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

if (isset($_GET['code'])) {
    // testType carries a table name into the query, so only a test type's own
    // table may be named; any other value finds no sample codes.
    $table = $_GET['testType'] ?? null;
    $knownTables = array_column(TestsService::getTestTypes(), 'tableName');
    if (!is_string($table) || !in_array($table, $knownTables, true)) {
        echo json_encode([]);
        return;
    }
    $sampleCode = $db->escape((string) $_GET['code']);
    $sql = "SELECT DISTINCT sample_code FROM $table WHERE sample_code like '$sampleCode%' OR remote_sample_code like '$sampleCode%'";
    $result = $db->rawQuery($sql);
    echo json_encode($result);
}
