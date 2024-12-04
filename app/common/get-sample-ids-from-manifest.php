<?php

use GuzzleHttp\Client;
use App\Services\TestsService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var \App\Services\DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var \Laminas\Diactoros\ServerRequest $request */
$request = \App\Registries\AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$manifestCode = $_POST['manifestCode'];

$testType = $_POST['testType'];

if (empty($testType) || empty($manifestCode)) {
    echo "";
    return;
}

$formTable = TestsService::getTestTableName($testType);
$primaryKey = TestsService::getTestPrimaryKeyColumn($testType);

function fetchSamples(DatabaseService $db, $formTable, $primaryKey, string $manifestCode): array
{

    $query = "SELECT vl.$primaryKey,
                COALESCE(JSON_EXTRACT(vl.form_attributes, '$.manifest.number_of_samples'), 0) AS number_of_samples
                FROM $formTable AS vl
                WHERE vl.sample_package_code IN
                    (?, (SELECT DISTINCT sample_package_code FROM $formTable WHERE remote_sample_code LIKE ?))
                ORDER BY request_created_datetime DESC";

    return $db->rawQuery($query, [$manifestCode, $manifestCode]);
}

// Fetch initial sample data
$sampleResult = fetchSamples($db, $formTable, $primaryKey, $manifestCode);

$sampleData = array_column($sampleResult, $primaryKey);
$noOfSamples = (int)($sampleResult[0]['number_of_samples'] ?? 0);
$count = count($sampleData);

if ($noOfSamples > 0 && $count === $noOfSamples) {
    echo implode(',', $sampleData);
    return;
}

$sampleData = [];
$baseUrl = sprintf(
    "%s://%s",
    ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http',
    $_SERVER['HTTP_HOST']
);

try {
    $client = new Client();
    $response = $client->get("{$baseUrl}/scheduled-jobs/remote/requests-receiver.php", [
        'query' => [
            'manifestCode' => $manifestCode,
            'forceSyncModule' => $testType,
        ],
        'headers' => [
            'X-CSRF-Token' => $_SESSION['csrf_token'],
            'X-Requested-With' => 'XMLHttpRequest',
        ],
        'verify' => false,
    ]);

    if ($response->getStatusCode() === 200) {
        $sampleResult = fetchSamples($db, $formTable, $primaryKey, $manifestCode);
        $sampleData = array_column($sampleResult, $primaryKey);
    }
} catch (Exception $e) {
    LoggerUtility::log('error', 'Request to archive audit data failed: ' . $e->getMessage());
}

echo implode(',', $sampleData);
