<?php

declare(strict_types=1);

// Compile the PHP-DI container in isolation so a broken definition — e.g. an
// exception or service whose constructor has an argument the container cannot
// guess — fails here instead of 500-ing every page in production.
//
// Why this is needed: production compiles the container only under the web SAPI
// (see app/system/di.php). A plain CLI bootstrap never compiles, so a bad
// definition stays invisible until an upgrade purges the compiled container and
// the first browser request tries — and fails — to recompile it. This harness
// forces compilation from the CLI via INTELIS_DI_COMPILE_CHECK and points the
// compiled output at a throwaway directory, because PHP-DI reuses an existing
// CompiledContainer.php as-is: compiling into a fresh dir every run guarantees a
// real recompile rather than a false pass off a stale artifact.
//
// Run: composer check-di   (or: php bin/build/check-di.php)
// Exit 0 on a clean compile, 1 on failure.

define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');
define('ROOT_PATH', dirname(__DIR__, 2));
define('APPLICATION_PATH', ROOT_PATH . '/app');

$compileDir = sys_get_temp_dir() . '/intelis-di-check-' . getmypid();
if (!is_dir($compileDir) && !mkdir($compileDir, 0777, true) && !is_dir($compileDir)) {
    fwrite(STDERR, "check-di: could not create temp compile dir: {$compileDir}\n");
    exit(1);
}
define('CACHE_PATH', $compileDir);

require ROOT_PATH . '/vendor/autoload.php';

// Force di.php to enable compilation even though we are on the CLI.
putenv('INTELIS_DI_COMPILE_CHECK=1');

$cleanup = static function () use ($compileDir): void {
    foreach (glob($compileDir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($compileDir);
};

try {
    // Building the container with compilation on resolves every definition to
    // code without instantiating services (factories stay lazy), so no database
    // or runtime environment is required.
    require ROOT_PATH . '/app/system/di.php';
    $cleanup();
    fwrite(STDOUT, "check-di: DI container compiled cleanly.\n");
    exit(0);
} catch (\Throwable $e) {
    $cleanup();
    fwrite(
        STDERR,
        "check-di: DI container failed to compile.\n\n"
            . get_class($e) . ': ' . $e->getMessage() . "\n"
    );
    exit(1);
}
