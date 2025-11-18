<?php

// bin/token.php

require_once __DIR__ . "/../bootstrap.php";

use App\Services\ApiService;
use App\Utilities\JsonUtility;
use App\Services\CommonService;
use App\Services\ConfigService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

ini_set('memory_limit', -1);
set_time_limit(0);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$cliMode = PHP_SAPI === 'cli';
$isLIS = $general->isLISInstance();

if (!$isLIS) {
    //LoggerUtility::logError("Token not generated. This script is only for LIS instances.");
    exit(CLI\ERROR);
}

if (!$cliMode) {
    // LoggerUtility::logError("Token generation script can only be run in CLI mode.");
    exit(CLI\OK);
}


$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

// Set the URL for the token generation endpoint
$remoteURL = rtrim((string) $general->getRemoteURL(), '/');

// Check connectivity
if ($remoteURL === '' || $remoteURL === '0' || $remoteURL === '') {
    LoggerUtility::logError("Please check if STS URL is set");
    exit(CLI\ERROR);
}

// Parse CLI arguments to get the API key using either `-key` or `--key`
$options = getopt('', ['key:']);
$apiKey = $options['key'] ?? null;


if ($apiKey === '' || $apiKey === '0' || $apiKey === [] || $apiKey === false || $apiKey === null) {
    $apiKey = ConfigService::generateAPIKeyForSTS($remoteURL);
}
if (!$cliMode) {
    $io->info("Usage only via CLI: php bin/token.php --key <API_KEY>");
    exit(CLI\ERROR);
}

$tokenURL = "$remoteURL/remote/v2/get-token.php";

// Prepare payload with API key and lab ID
$labId = $general->getSystemConfig('sc_testing_lab_id');
$payload = [
    'labId' => $labId,
];

// Send the request to generate a token
try {
    $headers = [
        'X-API-KEY' => $apiKey,
        'Content-Type' => 'application/json',
    ];

    $apiService->setHeaders($headers);

    $jsonResponse = $apiService->post($tokenURL, json_encode($payload), gzip: true);
    if (!empty($jsonResponse) && $jsonResponse != "[]") {


        $response = JsonUtility::decodeJson($jsonResponse);

        // Handle the response
        if (!empty($response['status']) && $response['status'] === 'success') {
            $io->success("Token generated: {$response['token']}");
            $data['sts_token'] = $response['token'];
            $db->update('s_vlsm_instance', $data);
        } else {
            $io->error("Failed to generate token. Error: " . (implode(" | ", $response['error']) ?? 'Unknown error'));
        }
    }
} catch (Throwable $e) {
    LoggerUtility::logError(
        "Error in token generation: " . $e->getMessage(),
        [
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ]
    );
    $io->error("Error in token generation. Please check logs for details");
}
