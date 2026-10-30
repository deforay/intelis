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
// Run: composer qc              (or: php bin/build/qc.php)
//      composer qc --quick, -q   skip the integration tests, which take most
//                                of the time. CI runs them on every pull request.
//      composer qc -- -v         print every check's output as it runs

// A command-line script that defines a helper and runs: PSR1's side-effects
// rule does not fit it.
// phpcs:disable PSR1.Files.SideEffects

/**
 * The arguments of the composer command that started this script.
 *
 * Composer passes a script only what follows `--`. It drops an option it does
 * not know, such as --quick, and takes -q as its own --quiet. People type
 * `composer qc --quick` and `composer qc -q`, so read them from the composer
 * process: it is this script's parent, or the parent of the shell between them.
 *
 * @return list<string>
 */
function composerArguments(): array
{
    $pid = getmypid();
    for ($level = 0; $level < 3 && $pid > 1; $level++) {
        $line = trim((string) shell_exec('ps -o ppid=,args= -p ' . (int) $pid . ' 2>/dev/null'));
        if ($line === '' || preg_match('/^(\d+)\s+(.*)$/', $line, $match) !== 1) {
            return [];
        }
        [, $parent, $command] = $match;
        $words = preg_split('/\s+/', $command) ?: [];
        $at = array_search('qc', $words, true);
        if ($at !== false && preg_match('/composer/', $command) === 1) {
            return array_slice($words, $at + 1);
        }
        $pid = (int) $parent;
    }
    return [];
}

$arguments = array_merge(array_slice($argv, 1), getenv('COMPOSER_BINARY') !== false ? composerArguments() : []);
$verbose = array_intersect($arguments, ['-v', '--verbose']) !== [];
$quick = array_intersect($arguments, ['-q', '--quick']) !== [];

// composer -q mutes everything this script prints. The person asked for a quick
// run, not a silent one, so write to the terminal directly.
$toTerminal = '';
if (in_array('-q', $arguments, true) && !in_array('-q', $argv, true) && is_writable('/dev/tty')) {
    $terminal = fopen('/dev/tty', 'w');
    if ($terminal !== false) {
        ob_start(static function (string $buffer) use ($terminal): string {
            fwrite($terminal, $buffer);
            return '';
        }, 1);
        $toTerminal = ' > /dev/tty';
    }
}
$onTerminal = stream_isatty(STDOUT) || $toTerminal !== '';

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
        escapeshellarg(PHP_BINARY) . ' bin/build/check-vendor.php --quiet',
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
    $quick
        ? ['unit tests', "{$composer} test -- --exclude-testsuite Integration", 'composer qc:quick']
        : ['tests', "{$composer} test", 'composer test'],
    ['security advisories', "{$composer} audit", 'composer audit'],
];

// The tests read the saved test MySQL login from the environment, so load it
// here, where every check inherits it. On the first run on a terminal, offer
// to set it up.
require_once $root . '/tests/Support/TestDbSettings.php';
if (!\Tests\Support\TestDbSettings::saved() && stream_isatty(STDIN) && getenv('INTELIS_TEST_DB_HOST') === false) {
    echo "First run: set up the test environment. Rerun this any time with composer setup-tests.\n\n";
    passthru(escapeshellarg(PHP_BINARY) . ' bin/build/setup-tests.php' . $toTerminal);
    echo "\n";
}
\Tests\Support\TestDbSettings::load();

$formatSeconds = static fn (float $seconds): string => sprintf('%.1fs', $seconds);

// Run a command with its output captured. On a terminal, show the time spent
// so far after the check's name, so a long check does not look stuck.
$runCaptured = static function (string $command, string $label, float $started) use ($onTerminal): array {
    $process = proc_open($command . ' 2>&1', [1 => ['pipe', 'w']], $pipes);
    if ($process === false) {
        return [1, "could not start: {$command}"];
    }
    stream_set_blocking($pipes[1], false);
    $output = '';
    while (!feof($pipes[1])) {
        $read = [$pipes[1]];
        $write = $except = null;
        if (stream_select($read, $write, $except, 1) > 0) {
            $output .= (string) fread($pipes[1], 65536);
        }
        if ($onTerminal) {
            printf("\r%s ... %ds", $label, (int) (microtime(true) - $started));
        }
    }
    fclose($pipes[1]);
    $exitCode = proc_close($process);
    if ($onTerminal) {
        echo "\r\033[K{$label} ... ";
    }
    return [$exitCode, rtrim($output)];
};

$runStarted = microtime(true);
$results = [];
foreach ($checks as $check) {
    [$name, $command, $rerun] = $check;
    echo "{$name} ... ";
    $started = microtime(true);

    if ($check['interactive'] ?? false) {
        passthru($command . $toTerminal . ' 2>&1', $exitCode);
        $output = '';
    } elseif ($verbose) {
        echo "\n";
        passthru($command . $toTerminal . ' 2>&1', $exitCode);
        $output = '';
    } else {
        [$exitCode, $output] = $runCaptured($command, $name, $started);
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
if ($quick) {
    $notes[] = 'Integration tests were not run (--quick). CI runs them on every pull request.';
} elseif (!\Tests\Support\TestDbSettings::configured()) {
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
