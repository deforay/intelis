#!/usr/bin/env php
<?php

// Generate / refresh the STS API auth token. No-op on non-LIS instances.

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
    exit(CLI\OK);
}

if (!$cliMode) {
    // LoggerUtility::logError("Token generation script can only be run in CLI mode.");
    exit(CLI\ERROR);
}


$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

// Set the URL for the token generation endpoint
$remoteURL = rtrim((string) $general->getRemoteURL(), '/');

// No STS URL configured — there is nothing to generate a token against. This is
// not an error: a LIS may run standalone or have STS configured later, and this
// script runs from install/upgrade post hooks that must not fail because of it.
// (Mirrors the no-op-on-non-LIS behavior above.)
if ($remoteURL === '' || $remoteURL === '0') {
    $io->note('No STS URL configured — skipping STS token generation.');
    exit(CLI\OK);
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

// Prepare payload with lab ID
$labId = $general->getSystemConfig('sc_testing_lab_id');
$payload = [
    'labId' => $labId,
];

// Send the request to generate a token
try {
    // Always send the legacy X-API-KEY so an older STS still authorises us. A newer
    // STS prefers stronger proof: if we already hold an sts_token, present it in
    // X-STS-Token (possession proof) so we no longer depend on the derivable key.
    // Both are automatic -- nothing for the operator to enter.
    $headers = [
        'X-API-KEY' => $apiKey,
        'Content-Type' => 'application/json',
    ];
    $existingToken = trim((string) ($general->getSTSToken() ?? ''));
    if ($existingToken !== '') {
        $headers['X-STS-Token'] = $existingToken;
    }

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
