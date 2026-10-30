<?php

declare(strict_types=1);

// Make sure vendor/ matches composer.lock, dev packages included, before
// `composer qc` runs anything that needs them. A checkout that pulled a new
// composer.json without running `composer install` otherwise gets as far as
// the tests and stops with "Could not open input file: vendor/bin/paratest".
//
// On a terminal it offers to run `composer install`; anywhere else (CI, a
// pipe) it names the problem and exits 1.
//
// Run: composer check-vendor   (or: php bin/build/check-vendor.php)
//      --quiet   print nothing when vendor/ matches (composer qc uses this)

$root = dirname(__DIR__, 2);
$lock = json_decode((string) @file_get_contents($root . '/composer.lock'), true);
if (!is_array($lock)) {
    fwrite(STDERR, "check-vendor: composer.lock is missing or unreadable.\n");
    exit(1);
}

$installed = json_decode((string) @file_get_contents($root . '/vendor/composer/installed.json'), true);

$problems = [];
if (!is_array($installed)) {
    $problems[] = 'vendor/ is not installed';
} else {
    if (($installed['dev'] ?? false) !== true) {
        $problems[] = 'dev packages are not installed (vendor was installed with --no-dev)';
    }

    $have = [];
    foreach ($installed['packages'] ?? [] as $package) {
        $have[$package['name']] = $package['version'];
    }
    foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
        $name = $package['name'];
        if (!isset($have[$name])) {
            $problems[] = "{$name} {$package['version']} is not installed";
        } elseif ($have[$name] !== $package['version']) {
            $problems[] = "{$name} is {$have[$name]}, composer.lock wants {$package['version']}";
        }
    }
}

$quiet = in_array('--quiet', array_slice($argv, 1), true);
if ($problems === []) {
    if (!$quiet) {
        echo "check-vendor: vendor/ matches composer.lock.\n";
    }
    exit(0);
}

// composer qc prints this check's name without a line break before running it.
if ($quiet) {
    fwrite(STDERR, "\n");
}
fwrite(STDERR, "check-vendor: vendor/ does not match composer.lock:\n  " . implode("\n  ", $problems) . "\n");

// A lab install gets vendor/ from the release package, without dev packages.
// Offering to install them there would put test tools on a lab server, so name
// the problem and leave the decision to the person at the keyboard.
if (is_array($installed) && ($installed['dev'] ?? false) !== true) {
    fwrite(
        STDERR,
        "check-vendor: this vendor/ has no dev packages, as on a lab install. qc runs on a\n"
            . "development checkout. If this is one, run `composer install`, then run this again.\n"
    );
    exit(1);
}

if (!stream_isatty(STDIN)) {
    fwrite(STDERR, "check-vendor: run `composer install`, then run this again.\n");
    exit(1);
}

echo 'Run composer install now? [Y/n] ';
// End of input counts as no: only a person pressing Enter gets the default.
$line = fgets(STDIN);
$answer = strtolower(trim((string) $line));
if ($line === false || ($answer !== '' && $answer !== 'y' && $answer !== 'yes')) {
    echo "\n";
    fwrite(STDERR, "check-vendor: run `composer install`, then run this again.\n");
    exit(1);
}

// Composer exports its own path to the scripts it runs; fall back to PATH when
// this file is run on its own.
$composer = getenv('COMPOSER_BINARY');
$command = $composer !== false && $composer !== ''
    ? escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($composer)
    : 'composer';
passthru($command . ' install --no-interaction', $exitCode);
exit($exitCode);
