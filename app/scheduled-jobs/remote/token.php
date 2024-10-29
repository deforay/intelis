<?php

use App\Services\ApiService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;

require_once __DIR__ . "/../../../bootstrap.php";

ini_set('memory_limit', -1);
set_time_limit(0);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);

$cliMode = php_sapi_name() === 'cli';
$isLIS = $general->isLISInstance();

if (!$isLIS || !$cliMode) {
    LoggerUtility::log('error', "Token not generated. This script is only for LIS instances.");
    exit(0);
}

// Set the URL for the token generation endpoint
$remoteURL = trim($general->getRemoteURL(), '/');

// Check connectivity
if (empty($remoteURL) || $remoteURL == '') {
    LoggerUtility::log('error', "Please check if STS URL is set");
    exit(0);
}

// Parse CLI arguments to get the API key using either `-key` or `--key`
$options = getopt('', ['key:']);
$apiKey = $options['key'] ?? null;


if (empty($apiKey)) {
    $apiKey = MiscUtility::generateUUIDv5($remoteURL);
}
if (!$cliMode) {
    echo "Usage: php token.php --key <API_KEY>\n";
    exit(1);
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
        'X-API-KEY' => $apiKey, // Add your API key header
        'Content-Type' => 'application/json',
    ];

    $apiService->setHeaders($headers);

    $jsonResponse = $apiService->post($tokenURL, json_encode($payload), gzip: true);
    if (!empty($jsonResponse) && $jsonResponse != "[]") {


        $response = JsonUtility::decodeJson($jsonResponse);

        // Handle the response
        if (!empty($response['status']) && $response['status'] === 'success') {
            echo $response['token'];
            $data['sts_token'] = $response['token'];
            $db->update('s_vlsm_instance', $data);
        } else {
            echo "Failed to generate token. Error: " . (implode(" | ", $response['error']) ?? 'Unknown error') . "\n";
        }
    }
} catch (Throwable $e) {
    LoggerUtility::log('error', "Error in token generation: " . $e->getMessage());
    echo "An error occurred. Please check logs for details.\n";
    exit(1);
}
