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

// The tests read the saved test MySQL login from the environment, so load it
// here, where every check inherits it. On the first run on a terminal, offer
// to set it up.
require_once $root . '/tests/Support/TestDbSettings.php';
if (!\Tests\Support\TestDbSettings::saved() && stream_isatty(STDIN) && getenv('INTELIS_TEST_DB_HOST') === false) {
    echo "First run: set up the test environment. Rerun this any time with composer setup-tests.\n\n";
    passthru(escapeshellarg(PHP_BINARY) . ' bin/build/setup-tests.php');
    echo "\n";
}
\Tests\Support\TestDbSettings::load();

$formatSeconds = static fn (float $seconds): string => sprintf('%.1fs', $seconds);

$runStarted = microtime(true);
$results = [];
foreach ($checks as $check) {
    [$name, $command, $rerun] = $check;
    echo "{$name} ... ";
    $started = microtime(true);

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
    $took = $formatSeconds(microtime(true) - $started);
    echo $passed ? "ok ({$took})\n" : "FAILED ({$took})\n";
    if (!$passed && $output !== '') {
        echo $output, "\n\n";
    }
    $results[] = [$name, $passed, $rerun, $took];

    // Every later check runs code from vendor/, so their failures would only
    // repeat this one.
    if (!$passed && ($check['required'] ?? false)) {
        break;
    }
}

$notes = [];
if (!\Tests\Support\TestDbSettings::configured()) {
    $notes[] = 'Integration tests were SKIPPED: no test MySQL login. Run composer setup-tests to set one up.';
}
// The tests run in a new PHP process, so ask that one, not this one: an install
// made during setup is not loaded here.
$sqliteCheck = escapeshellarg(PHP_BINARY) . ' -r "echo extension_loaded(\'pdo_sqlite\') ? 1 : 0;"';
if (trim((string) shell_exec($sqliteCheck)) !== '1') {
    $notes[] = 'Error index tests were SKIPPED: PHP has no pdo_sqlite. Run composer setup-tests to install it.';
}

echo "\nSummary\n";
$failed = 0;
foreach ($results as [$name, $passed, $rerun, $took]) {
    $took = str_pad($took, 7, ' ', STR_PAD_LEFT);
    if ($passed) {
        echo "  ok      {$took}  {$name}\n";
    } else {
        $failed++;
        echo "  FAILED  {$took}  {$name}  (rerun: {$rerun})\n";
    }
}
foreach (array_slice($checks, count($results)) as [$name]) {
    echo "  not run {$name}\n";
}
foreach ($notes as $note) {
    echo "\n  {$note}\n";
}

$total = $formatSeconds(microtime(true) - $runStarted);
echo $failed === 0 && count($results) === count($checks)
    ? "\nqc: all checks passed in {$total}.\n"
    : "\nqc: {$failed} check(s) failed. Total {$total}.\n";
exit($failed === 0 ? 0 : 1);
