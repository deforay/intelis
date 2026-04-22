<?php

// Command handler: metadata-resync
//
// Force-refreshes reference metadata with STS. Equivalent to
// `composer run metadata-sync -- -f` but invoked directly so we don't
// depend on composer being on PATH. Runs receiver then sender; each with
// the -f flag so last-sync-timestamps are ignored and everything is
// re-pulled / re-pushed.
//
// Expected params: none.
//
// Returns the status payload for the STS-side row.

/** @var array $params */

$base = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'tasks' . DIRECTORY_SEPARATOR . 'remote';
$scripts = [
    'sts-metadata-receiver' => $base . DIRECTORY_SEPARATOR . 'sts-metadata-receiver.php',
    'lab-metadata-sender' => $base . DIRECTORY_SEPARATOR . 'lab-metadata-sender.php',
];

$results = [];
$overallOk = true;
$start = microtime(true);

foreach ($scripts as $label => $scriptPath) {
    if (!is_file($scriptPath)) {
        $results[$label] = ['exitCode' => -1, 'error' => 'script missing'];
        $overallOk = false;
        continue;
    }

    $argv = [PHP_BINARY, $scriptPath, '-f'];
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $stepStart = microtime(true);
    $process = proc_open($argv, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        $results[$label] = ['exitCode' => -1, 'error' => 'spawn failed'];
        $overallOk = false;
        continue;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $stepDuration = round(microtime(true) - $stepStart, 2);

    $combined = array_merge(
        explode("\n", rtrim((string) $stdout, "\n")),
        explode("\n", rtrim((string) $stderr, "\n"))
    );

    $results[$label] = [
        'exitCode' => $exitCode,
        'durationSeconds' => $stepDuration,
        'outputTail' => implode("\n", array_slice(array_filter($combined, 'strlen'), -15)),
    ];

    if ($exitCode !== 0) {
        $overallOk = false;
    }
}

return [
    'status' => $overallOk ? 'completed' : 'failed',
    'durationSeconds' => round(microtime(true) - $start, 2),
    'steps' => $results,
];
