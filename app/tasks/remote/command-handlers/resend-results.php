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
//   - statuses (optional) status names from SampleStatusUtility::SENDABLE_STATUS_NAMES,
//                         as a list or a comma-separated string — resend only those
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

$statuses = $params['statuses'] ?? null;
if (is_array($statuses)) {
    $statuses = implode(',', array_filter($statuses, 'is_string'));
}
if ($statuses !== null && !is_string($statuses)) {
    return [
        'status' => 'failed',
        'error' => 'Statuses must be a list of status names',
    ];
}
if ($statuses !== null && trim($statuses) !== '') {
    // Refused here rather than in the sender, so the STS sees why.
    try {
        \App\Utilities\SampleStatusUtility::parseSendableStatuses($statuses);
    } catch (InvalidArgumentException $e) {
        return [
            'status' => 'failed',
            'error' => $e->getMessage(),
        ];
    }
} else {
    $statuses = null;
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
if ($statuses !== null) {
    $argv[] = '--status=' . $statuses;
}
// Match manual cron convention so timestamp rewrites stay consistent.
$argv[] = 'silent';
// Wait out a cron run instead of exiting 0, which would mark this command
// completed without sending anything.
$argv[] = '--wait-for-lock';

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
    'statuses' => $statuses,
    'outputTail' => implode("\n", array_slice(array_filter($combinedLines, 'strlen'), -30)),
];
