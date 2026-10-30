<?php

declare(strict_types=1);

// composer qc: run every quality check, keep going past a failure, and end with
// one line per check. A passing check prints one line. A failing check prints
// its own output, so the reason is next to the name.
//
// Composer's own script list stopped at the first failure and printed every
// check's output in full. Developers learned to skim it, and a run where half
// the tests skipped looked the same as a full run. This runner exists so the
// last screen of output answers "did everything pass, and what did not run".
//
// Run: composer qc         (or: php bin/build/qc.php)
//      composer qc -- -v   print every check's output as it runs

$verbose = array_intersect(array_slice($argv, 1), ['-v', '--verbose']) !== [];

$root = dirname(__DIR__, 2);
chdir($root);

// Composer exports its own path to the scripts it runs; fall back to PATH when
// this file is run on its own.
$composerBinary = getenv('COMPOSER_BINARY');
$composer = $composerBinary !== false && $composerBinary !== ''
    ? escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($composerBinary)
    : 'composer';

// name, command, the command to rerun this check alone. A check marked
// interactive runs on the terminal, because it can ask a question.
$checks = [
    [
        'vendor/ matches composer.lock',
        escapeshellarg(PHP_BINARY) . ' bin/build/check-vendor.php',
        'composer check-vendor',
        'interactive' => true,
        'required' => true,
    ],
    ['PHP extensions', "{$composer} check-platform-reqs --lock", 'composer check-platform-reqs --lock'],
    ['composer.json', "{$composer} validate --no-check-publish --no-check-version", 'composer validate'],
    ['PHP syntax', "{$composer} lint", 'composer lint'],
    ['coding style', "{$composer} cs-check", 'composer cs-check'],
    ['DI container', "{$composer} check-di", 'composer check-di'],
    ['invariants', "{$composer} check-invariants", 'composer check-invariants'],
    ['commit attribution', "{$composer} check-commit-attribution", 'composer check-commit-attribution'],
    ['tests', "{$composer} test", 'composer test'],
    ['security advisories', "{$composer} audit", 'composer audit'],
];

$results = [];
foreach ($checks as $check) {
    [$name, $command, $rerun] = $check;
    echo "{$name} ... ";

    if (($check['interactive'] ?? false) || $verbose) {
        echo "\n";
        passthru($command . ' 2>&1', $exitCode);
        $output = '';
    } else {
        exec($command . ' 2>&1', $lines, $exitCode);
        $output = implode("\n", $lines);
        $lines = [];
    }

    $passed = $exitCode === 0;
    echo $passed ? "ok\n" : "FAILED\n";
    if (!$passed && $output !== '') {
        echo $output, "\n\n";
    }
    $results[] = [$name, $passed, $rerun];

    // Every later check runs code from vendor/, so their failures would only
    // repeat this one.
    if (!$passed && ($check['required'] ?? false)) {
        break;
    }
}

$notes = [];
$dbHost = getenv('INTELIS_TEST_DB_HOST');
$dbUser = getenv('INTELIS_TEST_DB_USER');
if ($dbHost === false || $dbHost === '' || $dbUser === false || $dbUser === '') {
    $notes[] = 'Integration tests were SKIPPED: no test MySQL. Set INTELIS_TEST_DB_HOST and '
        . 'INTELIS_TEST_DB_USER (and _PASS, _PORT if needed) to run them.';
}
if (!extension_loaded('pdo_sqlite')) {
    $notes[] = 'Error index tests were SKIPPED: PHP has no pdo_sqlite. On Ubuntu: apt install php'
        . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-sqlite3';
}

echo "\nSummary\n";
$failed = 0;
foreach ($results as [$name, $passed, $rerun]) {
    if ($passed) {
        echo "  ok      {$name}\n";
    } else {
        $failed++;
        echo "  FAILED  {$name}  (rerun: {$rerun})\n";
    }
}
foreach (array_slice($checks, count($results)) as [$name]) {
    echo "  not run {$name}\n";
}
foreach ($notes as $note) {
    echo "\n  {$note}\n";
}

echo $failed === 0 && count($results) === count($checks)
    ? "\nqc: all checks passed.\n"
    : "\nqc: {$failed} check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
