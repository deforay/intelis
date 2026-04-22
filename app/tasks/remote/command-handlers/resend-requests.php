<?php

// Command handler: resend-requests
//
// Pulls test requests (and/or a specific manifest) from STS by spawning
// requests-receiver.php with the requested params.
//
// Expected params:
//   - module   (optional)  one of: vl, eid, covid19, hepatitis, tb, cd4, generic-tests
//   - manifest (optional)  specific manifest code to fetch
//
// Returns the status payload for the STS-side row.

/** @var array $params */

$module = $params['module'] ?? null;
$manifest = $params['manifest'] ?? null;

$validModules = ['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'cd4', 'generic-tests'];
if (!empty($module) && !in_array($module, $validModules, true)) {
    return [
        'status' => 'failed',
        'error' => 'Invalid module: ' . $module,
    ];
}
if (!empty($manifest) && !preg_match('/^[A-Za-z0-9_\-\.]{1,64}$/', (string) $manifest)) {
    return [
        'status' => 'failed',
        'error' => 'Invalid manifest code',
    ];
}

$scriptPath = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'tasks'
    . DIRECTORY_SEPARATOR . 'remote' . DIRECTORY_SEPARATOR . 'requests-receiver.php';

$argv = [PHP_BINARY, $scriptPath];
if (!empty($module)) {
    $argv[] = '-t';
    $argv[] = $module;
}
if (!empty($manifest)) {
    $argv[] = '-m';
    $argv[] = $manifest;
}

$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$start = microtime(true);
$process = proc_open($argv, $descriptorSpec, $pipes);
if (!is_resource($process)) {
    return ['status' => 'failed', 'error' => 'Failed to spawn requests-receiver'];
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);
$duration = round(microtime(true) - $start, 2);

$combined = array_merge(
    explode("\n", rtrim((string) $stdout, "\n")),
    explode("\n", rtrim((string) $stderr, "\n"))
);

return [
    'status' => $exitCode === 0 ? 'completed' : 'failed',
    'exitCode' => $exitCode,
    'durationSeconds' => $duration,
    'module' => $module,
    'manifest' => $manifest,
    'outputTail' => implode("\n", array_slice(array_filter($combined, 'strlen'), -30)),
];
