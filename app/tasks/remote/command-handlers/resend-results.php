<?php

// Command handler: resend-results
//
// Invoked by the pending-commands courier in its own PHP process. Spawns
// results-sender.php with the requested module + days filter via proc_open
// (no shell; args passed as explicit argv) so the existing per-module loops
// + chunking + acknowledgement flow are reused unchanged.
//
// Expected params:
//   - module  (optional)  one of: vl, eid, covid19, hepatitis, tb, cd4, generic-tests
//   - days    (optional)  integer 1..3650 — resend records modified within last N days
//
// Returns an array that becomes the `result` field on the status row back at STS.

/** @var array $params */
/** @var array $command */

$module = $params['module'] ?? null;
$days = isset($params['days']) ? (int) $params['days'] : null;

$validModules = ['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'cd4', 'generic-tests'];
if (!empty($module) && !in_array($module, $validModules, true)) {
    return [
        'status' => 'failed',
        'error' => 'Invalid module: ' . $module,
    ];
}
if ($days !== null && ($days < 1 || $days > 3650)) {
    return [
        'status' => 'failed',
        'error' => 'Days out of range (1..3650)',
    ];
}

$scriptPath = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'tasks'
    . DIRECTORY_SEPARATOR . 'remote' . DIRECTORY_SEPARATOR . 'results-sender.php';

// Build explicit argv — no shell interpretation; proc_open with an array
// bypasses the shell entirely on *nix.
$argv = [PHP_BINARY, $scriptPath];
if (!empty($module)) {
    $argv[] = '-t';
    $argv[] = $module;
}
if ($days !== null && $days > 0) {
    $argv[] = (string) $days;
}
// Match manual cron convention so timestamp rewrites stay consistent.
$argv[] = 'silent';

$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$start = microtime(true);
$process = proc_open($argv, $descriptorSpec, $pipes);

if (!is_resource($process)) {
    return [
        'status' => 'failed',
        'error' => 'Failed to spawn results-sender subprocess',
    ];
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);
$duration = round(microtime(true) - $start, 2);

$combinedLines = array_merge(
    explode("\n", rtrim((string) $stdout, "\n")),
    explode("\n", rtrim((string) $stderr, "\n"))
);

return [
    'status' => $exitCode === 0 ? 'completed' : 'failed',
    'exitCode' => $exitCode,
    'durationSeconds' => $duration,
    'module' => $module,
    'days' => $days,
    'outputTail' => implode("\n", array_slice(array_filter($combinedLines, 'strlen'), -30)),
];
