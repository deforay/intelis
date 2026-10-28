<?php

// Command handler: resend-requests
//
// Pulls test requests (and/or a specific manifest) from STS by spawning
// requests-receiver.php with the requested params.
//
// Expected params (validated by ResendRequestsArgs):
//   - module   (optional)  one of: vl, eid, covid19, hepatitis, tb, cd4, generic-tests
//   - manifest (optional)  specific manifest code to fetch
//   - days     (optional)  1..3650: pull everything changed in the last N days,
//                          including requests the STS already sent
//
// Returns the status payload for the STS-side row.

/** @var array $params */

$module = $params['module'] ?? null;
$manifest = $params['manifest'] ?? null;
$days = $params['days'] ?? null;

try {
    $args = \App\Utilities\ResendRequestsArgs::from($params);
} catch (\InvalidArgumentException $e) {
    return ['status' => 'failed', 'error' => $e->getMessage()];
}

$scriptPath = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'tasks'
    . DIRECTORY_SEPARATOR . 'remote' . DIRECTORY_SEPARATOR . 'requests-receiver.php';

$argv = [PHP_BINARY, $scriptPath, ...$args];
// Wait out a cron run instead of exiting 0, which would mark this command
// completed without pulling the window it asked for.
$argv[] = '--wait-for-lock';

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
    'days' => $days,
    'outputTail' => implode("\n", array_slice(array_filter($combined, 'strlen'), -30)),
];
