<?php

declare(strict_types=1);

/**
 * Raise SHARED_FN_VERSION in scripts/shared-functions.sh by one, and set every
 * script's intelis_require_shared_fn_version line to the new number.
 *
 * Run it in any commit that changes shared-functions.sh.
 * bin/build/check-shell-loaders.php fails the push otherwise.
 *
 * Usage:
 *   php bin/dev/bump-shared-fn-version.php          raise the version, update every script
 *   php bin/dev/bump-shared-fn-version.php --sync   keep the version, update every script
 */

const SHARED = 'scripts/shared-functions.sh';

chdir(dirname(__DIR__, 2));
$sync = in_array('--sync', $argv, true);

$shared = (string) file_get_contents(SHARED);
if (!preg_match('/^SHARED_FN_VERSION=(\d+)$/m', $shared, $m)) {
    fwrite(STDERR, SHARED . " does not declare SHARED_FN_VERSION=<number>.\n");
    exit(1);
}
$version = $sync ? (int) $m[1] : (int) $m[1] + 1;

if (!$sync) {
    $shared = (string) preg_replace('/^SHARED_FN_VERSION=\d+$/m', "SHARED_FN_VERSION={$version}", $shared, 1);
    file_put_contents(SHARED, $shared);
    echo SHARED . ": SHARED_FN_VERSION={$version}\n";
}

foreach (glob('scripts/*.sh') as $file) {
    if ($file === SHARED) {
        continue;
    }
    $text = (string) file_get_contents($file);
    $updated = (string) preg_replace('/^intelis_require_shared_fn_version \d+$/m', "intelis_require_shared_fn_version {$version}", $text);
    if ($updated !== $text) {
        file_put_contents($file, $updated);
        echo "{$file}: requires version {$version}\n";
    }
}
