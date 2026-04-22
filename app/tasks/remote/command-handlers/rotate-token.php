<?php

// Command handler: rotate-token
//
// Drops the current STS bearer token from s_vlsm_instance and re-fetches a
// fresh one by spawning bin/token.php (which auto-derives the API key from
// the configured remote URL via ConfigService::generateAPIKeyForSTS).
//
// Used when STS has reason to suspect the LIS's token is stale or
// compromised. Safe to run at any time — failure just leaves the instance
// without a token and the next sync tick's @token step will re-fetch.
//
// Expected params: none.

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$start = microtime(true);
$details = [];

try {
    // Clear the stored token so any in-flight use fails closed.
    $db->update('s_vlsm_instance', ['sts_token' => null]);
    $details['clearedExistingToken'] = true;
} catch (Throwable $e) {
    return [
        'status' => 'failed',
        'error' => 'Failed to clear existing token: ' . $e->getMessage(),
        'durationSeconds' => round(microtime(true) - $start, 2),
    ];
}

// Re-fetch via bin/token.php. It self-derives the API key.
$scriptPath = ROOT_PATH . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'token.php';
if (!is_file($scriptPath)) {
    return [
        'status' => 'failed',
        'error' => 'bin/token.php missing',
        'durationSeconds' => round(microtime(true) - $start, 2),
    ];
}

$argv = [PHP_BINARY, $scriptPath];
$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($argv, $descriptorSpec, $pipes);
if (!is_resource($process)) {
    return [
        'status' => 'failed',
        'error' => 'Failed to spawn token script',
        'durationSeconds' => round(microtime(true) - $start, 2),
        'details' => $details,
    ];
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

$details['tokenScriptExitCode'] = $exitCode;

// Verify a new token is now present.
try {
    $newToken = $db->getValue('s_vlsm_instance', 'sts_token');
    $details['tokenRefreshed'] = !empty($newToken);
} catch (Throwable $e) {
    $details['tokenRefreshed'] = null;
    $details['tokenCheckError'] = $e->getMessage();
}

$combined = array_merge(
    explode("\n", rtrim((string) $stdout, "\n")),
    explode("\n", rtrim((string) $stderr, "\n"))
);

return [
    'status' => ($exitCode === 0 && !empty($details['tokenRefreshed'])) ? 'completed' : 'failed',
    'durationSeconds' => round(microtime(true) - $start, 2),
    'details' => $details,
    'outputTail' => implode("\n", array_slice(array_filter($combined, 'strlen'), -15)),
];
