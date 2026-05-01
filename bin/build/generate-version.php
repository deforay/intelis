#!/usr/bin/env php
<?php

// Bumps the project version. Dead simple.
//
//   php bin/build/generate-version.php           (no arg = interactive menu)
//   php bin/build/generate-version.php patch     5.4.5 -> 5.4.6
//   php bin/build/generate-version.php minor     5.4.5 -> 5.5.0
//   php bin/build/generate-version.php major     5.4.5 -> 6.0.0
//   php bin/build/generate-version.php 5.6.0     set explicit version
//
// Add -y to skip the confirmation prompt. --help for help.
//
// On every release bump, the script:
//   1) shows you what it WILL do,
//   2) asks "apply? [Y/n]",
//   3) updates composer.json + app/system/version.php (kept identical),
//   4) closes out every still-open older migration with EOV markers,
//   5) creates a new sys/migrations/<x.y.z>.sql stub.

require_once __DIR__ . '/../../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

const EOV_MARKER = "-- END OF VERSION --";
const EOV_COUNT  = 12;

$paths = [
    'composer'   => ROOT_PATH . '/composer.json',
    'version'    => APPLICATION_PATH . '/system/version.php',
    'migrations' => ROOT_PATH . '/sys/migrations/',
];

try {
    [$arg, $skipConfirm, $help] = parseArgs($argv);
    if ($help) { printUsage(); exit(0); }

    $current = readCurrentState($paths);
    $action  = resolveAction($arg, $current);
    $plan    = buildPlan($action, $current, $paths);

    printHeader($current);
    printPlan($plan);

    if (!$skipConfirm) {
        if (!stdinIsTty()) {
            throw new RuntimeException("No -y given and stdin is not a TTY. Re-run with -y to apply non-interactively.");
        }
        if (!confirm("Apply?")) {
            info("Aborted.");
            exit(0);
        }
    }

    applyPlan($plan, $paths);
    info("\n✓ Done. Version is now {$plan['newVersion']}.");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "✗ " . $e->getMessage() . "\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// CLI parsing — one positional, two flags. That's it.
// ---------------------------------------------------------------------------

function parseArgs(array $argv): array
{
    $positional = null;
    $skipConfirm = false;
    $help = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '-y' || $arg === '--yes')              { $skipConfirm = true; continue; }
        if ($arg === '-h' || $arg === '--help')             { $help = true; continue; }
        if (str_starts_with($arg, '-')) {
            throw new InvalidArgumentException("Unknown flag: {$arg} (try --help)");
        }
        if ($positional !== null) {
            throw new InvalidArgumentException("Too many arguments. Pass exactly one of: patch, minor, major, or X.Y.Z");
        }
        $positional = $arg;
    }
    return [$positional, $skipConfirm, $help];
}

function printUsage(): void
{
    echo <<<TXT
Bump the project version.

Usage:
  php bin/build/generate-version.php [<bump>] [-y]

Bumps:
  patch               5.4.5 -> 5.4.6
  minor               5.4.5 -> 5.5.0
  major               5.4.5 -> 6.0.0
  X.Y.Z               set explicit version (must be greater than current)

Flags:
  -y, --yes           don't prompt for confirmation
  -h, --help          show this help

If no bump is given, drops into an interactive menu. The script always
previews the plan and asks before changing files unless you pass -y.
composer.json and app/system/version.php are kept identical.

TXT;
}

// ---------------------------------------------------------------------------
// Reading current state
// ---------------------------------------------------------------------------

function readCurrentState(array $paths): array
{
    if (!is_file($paths['composer'])) {
        throw new RuntimeException("composer.json not found at {$paths['composer']}");
    }
    $data = json_decode((string) file_get_contents($paths['composer']), true, flags: JSON_THROW_ON_ERROR);
    if (empty($data['version']) || !preg_match('/^\d+\.\d+\.\d+$/', (string) $data['version'])) {
        throw new RuntimeException("composer.json 'version' is missing or not in X.Y.Z form.");
    }
    $composer = (string) $data['version'];

    // version.php may legitimately be missing (regenerated on every run) or
    // carry an old four-part shape (X.Y.Z.b) from before this script dropped
    // the build counter. We only care about the X.Y.Z core for drift detection.
    $versionFileCore = $composer;
    if (is_file($paths['version'])
        && preg_match("/define\\('VERSION',\\s*'([\\d.]+)'\\)/", (string) file_get_contents($paths['version']), $m)) {
        $parts = explode('.', $m[1]);
        $versionFileCore = implode('.', array_slice($parts, 0, 3));
    }

    return [
        'composer'        => $composer,
        'versionFileCore' => $versionFileCore,
        'drifted'         => $versionFileCore !== $composer,
    ];
}

// ---------------------------------------------------------------------------
// What does the user want?
// ---------------------------------------------------------------------------

function resolveAction(?string $arg, array $current): array
{
    if ($arg === null) {
        if (!stdinIsTty()) {
            throw new RuntimeException("No bump argument given and stdin is not a TTY. Pass patch / minor / major / X.Y.Z, or run interactively.");
        }
        return promptForAction($current);
    }
    return parseBumpArg($arg, $current);
}

function parseBumpArg(string $arg, array $current): array
{
    $arg = strtolower($arg);
    if (in_array($arg, ['patch', 'minor', 'major'], true)) {
        return ['newVersion' => bumpCore($current['composer'], $arg)];
    }
    if (preg_match('/^\d+\.\d+\.\d+$/', $arg)) {
        return ['newVersion' => $arg];
    }
    throw new InvalidArgumentException("Don't know what '{$arg}' means. Try patch, minor, major, or X.Y.Z (e.g. 5.6.0).");
}

function promptForAction(array $current): array
{
    $patch = bumpCore($current['composer'], 'patch');
    $minor = bumpCore($current['composer'], 'minor');
    $major = bumpCore($current['composer'], 'major');

    echo "\nWhat do you want to bump?\n";
    echo "  1) patch   -> {$patch}   (default, press Enter)\n";
    echo "  2) minor   -> {$minor}\n";
    echo "  3) major   -> {$major}\n";
    echo "  4) custom — enter X.Y.Z\n";
    echo "  q) quit\n";

    $line = strtolower(readLineOrFail("> "));
    return match ($line) {
        '', '1', 'patch' => ['newVersion' => $patch],
        '2', 'minor'     => ['newVersion' => $minor],
        '3', 'major'     => ['newVersion' => $major],
        '4', 'custom'    => ['newVersion' => promptForCustomCore($current['composer'])],
        'q', 'quit'      => throw new RuntimeException('Aborted.'),
        default          => throw new InvalidArgumentException("Invalid choice: '{$line}'. Press Enter for patch, or 1-4 / q."),
    };
}

function promptForCustomCore(string $current): string
{
    $value = readLineOrFail("Enter new version (X.Y.Z), must be > {$current}: ");
    if (!preg_match('/^\d+\.\d+\.\d+$/', $value)) {
        throw new InvalidArgumentException("Not in X.Y.Z form: '{$value}'");
    }
    if (version_compare($value, $current, '<=')) {
        throw new InvalidArgumentException("New version '{$value}' must be greater than current '{$current}'.");
    }
    return $value;
}

function bumpCore(string $core, string $kind): string
{
    [$maj, $min, $pat] = array_map('intval', explode('.', $core));
    // Parens kept for readability — PHP 8 lowered '.' below '+' so they're
    // not strictly required, but mixing arithmetic with concatenation is
    // hard to scan otherwise.
    return match ($kind) {
        'major' => ($maj + 1) . '.0.0',
        'minor' => $maj . '.' . ($min + 1) . '.0',
        'patch' => $maj . '.' . $min . '.' . ($pat + 1),
    };
}

// ---------------------------------------------------------------------------
// Plan
// ---------------------------------------------------------------------------

function buildPlan(array $action, array $current, array $paths): array
{
    $newVersion = $action['newVersion'];
    if (version_compare($newVersion, $current['composer'], '<=')) {
        throw new InvalidArgumentException("Refusing to set version to {$newVersion}: not greater than current composer version {$current['composer']}.");
    }

    return [
        'newVersion'        => $newVersion,
        'closeMigrations'   => findOpenMigrationsBetween($paths['migrations'], $current['composer'], $newVersion),
        'openMigrationFile' => $paths['migrations'] . $newVersion . '.sql',
    ];
}

// Every X.Y.Z.sql with $currentCore <= version < $newCore that doesn't already
// carry an EOV marker.
//
// Two bounds, two reasons:
//   - lower bound (>= current composer version) — leaves long-historical
//     migrations alone. They pre-date the EOV convention and shouldn't be
//     mass-marked retroactively.
//   - upper bound (< new version) — anything at or above the new version
//     is "open" by definition; we don't mark it.
//
// Fixes the bug we hit earlier: when composer was at 5.4.2 but migrations
// 5.4.3.sql / 5.4.4.sql had drifted ahead, bumping to 5.4.5 needs to close
// out all three. The old script closed only one (the version.php prior).
function findOpenMigrationsBetween(string $migrationsDir, string $currentCore, string $newCore): array
{
    if (!is_dir($migrationsDir)) {
        return [];
    }
    $candidates = [];
    foreach (glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [] as $file) {
        $name = basename($file, '.sql');
        if (!preg_match('/^\d+\.\d+\.\d+$/', $name)) {
            continue;
        }
        if (version_compare($name, $currentCore, '<')) {
            continue;
        }
        if (version_compare($name, $newCore, '>=')) {
            continue;
        }
        if (str_contains((string) file_get_contents($file), EOV_MARKER)) {
            continue;
        }
        $candidates[$name] = $file;
    }
    uksort($candidates, 'version_compare');
    return array_values($candidates);
}

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------

function printHeader(array $current): void
{
    echo "\nCurrent version: {$current['composer']}";
    if ($current['drifted']) {
        echo "  (composer.json and version.php disagree — will reconcile on this run)";
    }
    echo "\n";
}

function printPlan(array $plan): void
{
    echo "\nPlan:\n";
    echo "  composer.json + version.php  -> {$plan['newVersion']}\n";
    if (!empty($plan['closeMigrations'])) {
        echo "  close out " . count($plan['closeMigrations']) . " migration file(s):\n";
        foreach ($plan['closeMigrations'] as $f) {
            echo "    + " . relPath($f) . "  (append " . EOV_COUNT . " EOV markers)\n";
        }
    }
    $verb = is_file($plan['openMigrationFile']) ? "reuse existing" : "create";
    echo "  {$verb}                -> " . relPath($plan['openMigrationFile']) . "\n";
    echo "\n";
}

// ---------------------------------------------------------------------------
// Apply
// ---------------------------------------------------------------------------

function applyPlan(array $plan, array $paths): void
{
    foreach ($plan['closeMigrations'] as $f) {
        appendEovMarkers($f);
        info("  closed   " . relPath($f));
    }

    writeComposerVersion($paths['composer'], $plan['newVersion']);
    info("  updated  composer.json -> {$plan['newVersion']}");

    writeVersionFile($paths['version'], $plan['newVersion']);
    info("  updated  app/system/version.php -> {$plan['newVersion']}");

    if (!is_file($plan['openMigrationFile'])) {
        if (!is_dir($paths['migrations'])) {
            mkdir($paths['migrations'], 0755, true);
        }
        writeMigrationStub($plan['openMigrationFile'], $plan['newVersion']);
        info("  created  " . relPath($plan['openMigrationFile']));
    } else {
        info("  reused   " . relPath($plan['openMigrationFile']));
    }
}

function appendEovMarkers(string $file): void
{
    $body = (string) file_get_contents($file);
    $needsLeadingNewline = $body !== '' && substr($body, -1) !== "\n";
    $marker = ($needsLeadingNewline ? "\n" : '') . str_repeat(EOV_MARKER . "\n", EOV_COUNT);
    file_put_contents($file, $marker, FILE_APPEND);
}

function writeComposerVersion(string $path, string $newVersion): void
{
    $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $data['version'] = $newVersion;
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    file_put_contents($path, $encoded);
}

function writeVersionFile(string $path, string $newVersion): void
{
    $contents = <<<PHP
<?php

// DO NOT MODIFY THIS FILE
// Version is defined in composer.json
// This file is automatically generated by bin/build/generate-version.php
defined('VERSION')
    || define('VERSION', '{$newVersion}');

PHP;
    file_put_contents($path, $contents);
}

function writeMigrationStub(string $file, string $version): void
{
    $real = realpath(dirname($file));
    if ($real === false || !str_starts_with($real, realpath(ROOT_PATH . '/sys/migrations'))) {
        throw new RuntimeException("Refusing to write migration outside sys/migrations/: {$file}");
    }
    $stub = "-- Migration file for version {$version}\n"
          . "-- Created on " . date('Y-m-d H:i:s') . "\n\n\n"
          . "UPDATE `system_config` SET `value` = '{$version}' WHERE `system_config`.`name` = 'sc_version';\n\n";
    file_put_contents($file, $stub);
}

// ---------------------------------------------------------------------------
// IO helpers
// ---------------------------------------------------------------------------

function info(string $msg): void { echo $msg . "\n"; }

function stdinIsTty(): bool
{
    return function_exists('posix_isatty') ? @posix_isatty(STDIN) : (defined('STDIN') && stream_isatty(STDIN));
}

function readLineOrFail(string $prompt): string
{
    echo $prompt;
    $line = fgets(STDIN);
    if ($line === false) {
        // Fixes the infinite-loop bug in the old script.
        throw new RuntimeException("Unexpected end of input. Pass a positional argument and -y to run non-interactively.");
    }
    return trim($line);
}

function confirm(string $prompt): bool
{
    // Default: yes (Enter accepts).
    $line = strtolower(readLineOrFail($prompt . ' [Y/n] '));
    return $line === '' || $line === 'y' || $line === 'yes';
}

function relPath(string $abs): string
{
    $root = realpath(ROOT_PATH) ?: ROOT_PATH;
    return str_starts_with($abs, $root) ? ltrim(substr($abs, strlen($root)), '/') : $abs;
}
