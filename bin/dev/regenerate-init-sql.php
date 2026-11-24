#!/usr/bin/env php
<?php

/**
 * Rebuilds sql/init.sql from the migrations.
 *
 *   php bin/dev/regenerate-init-sql.php
 *   php bin/dev/regenerate-init-sql.php --output /tmp/init.sql --keep
 *
 * The script loads the current sql/init.sql into a throwaway database, runs
 * bin/migrate.php on it, and dumps the result back to sql/init.sql with
 * mysqldump. It never reads your own database, so local samples, users and
 * settings stay out of the file.
 *
 * Every row in the throwaway database is seed data, because it came from
 * init.sql or from a migration.
 *
 * The output keeps the mysqldump layout init.sql already has. Tests, preflight,
 * bin/heal-schema.php, setup.sh and CI all read that layout, and a git diff
 * shows only what the migrations changed.
 *
 * --output <file>  writes the dump to that file instead of sql/init.sql.
 * --keep           leaves the throwaway database in place for inspection.
 *
 * Needs the mysql and mysqldump clients, and the database login from
 * configs/config.production.php, which must be allowed to create and drop
 * databases.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/../../bootstrap.php';

use App\Services\CommonService;
use App\Registries\ContainerRegistry;

$output = ROOT_PATH . '/sql/init.sql';
$keep = false;
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if ($arg === '--keep') {
        $keep = true;
    } elseif ($arg === '--output' && isset($argv[$i + 1])) {
        $output = $argv[++$i];
    } elseif (str_starts_with($arg, '--output=')) {
        $output = substr($arg, 9);
    } else {
        fwrite(STDERR, "Usage: php bin/dev/regenerate-init-sql.php [--output <file>] [--keep]\n");
        exit(2);
    }
}

$config = ContainerRegistry::get('applicationConfig')['database'] ?? [];
$host = (string) (($config['host'] ?? '') ?: '127.0.0.1');
$port = (int) (($config['port'] ?? 0) ?: 3306);
$scratch = 'intelis_initsql_' . getmypid();

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$appVersion = (string) $general->getAppVersion();

// The clients read the login from a file, so the password never shows in the process
// list. --defaults-file makes it the only option file they read: a ~/.my.cnf or
// /etc/mysql/my.cnf would otherwise override the host or login set here.
$defaults = tempnam(sys_get_temp_dir(), 'intelis-my');
file_put_contents($defaults, "[client]\nhost=$host\nport=$port\nuser=" . ($config['username'] ?? '')
    . "\npassword=\"" . addcslashes((string) ($config['password'] ?? ''), '"\\') . "\"\n");
chmod($defaults, 0600);

$cleanup = static function () use ($scratch, $keep, $defaults): void {
    if ($keep) {
        echo "Kept the throwaway database `$scratch`.\n";
    } else {
        try {
            run(['mysql', "--defaults-file=$defaults", '-e', "DROP DATABASE IF EXISTS `$scratch`"], null);
        } catch (Throwable $e) {
            fwrite(STDERR, "Could not drop `$scratch`: " . $e->getMessage() . "\n");
        }
    }
    @unlink($defaults);
};

try {
    $source = file_get_contents(ROOT_PATH . '/sql/init.sql');
    if ($source === false) {
        throw new RuntimeException('Cannot read sql/init.sql.');
    }

    echo "1/3 Loading sql/init.sql into `$scratch`\n";
    run(['mysql', "--defaults-file=$defaults", '-e',
        "CREATE DATABASE `$scratch` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"], null);
    run(['mysql', "--defaults-file=$defaults", $scratch], $source);

    echo "2/3 Running the migrations\n";
    run([PHP_BINARY, ROOT_PATH . '/bin/migrate.php', '-q'], null, ['MIGRATE_DB_NAME' => $scratch]);

    $version = trim(run([
        'mysql', "--defaults-file=$defaults", '--batch', '--skip-column-names', $scratch,
        '-e', "SELECT `value` FROM `system_config` WHERE `name` = 'sc_version'",
    ], null));
    if ($version !== $appVersion) {
        throw new RuntimeException("The migrations stopped at $version, but composer.json is at $appVersion. Fix the failing migration first.");
    }

    echo "3/3 Writing $output\n";
    $dump = run([
        'mysqldump', "--defaults-file=$defaults",
        '--routines', '--no-tablespaces', '--skip-dump-date', '--set-gtid-purged=OFF',
        '--default-character-set=utf8mb4', $scratch,
    ], null);
    // The header and the routines comment name the database and the host. Name the
    // real database, and one host, so the file does not change with who ran this.
    $dump = (string) preg_replace('/^-- Host: .*$/m', '-- Host: localhost    Database: vlsm', $dump, 1);
    $dump = str_replace("for database '$scratch'", "for database 'vlsm'", $dump);
    if (str_contains($dump, $scratch)) {
        throw new RuntimeException("The dump still names `$scratch`. Check it with --keep.");
    }
    if (file_put_contents($output, $dump) === false) {
        throw new RuntimeException("Cannot write $output.");
    }
    echo "Done. init.sql is at $version. Review it with: git diff --stat sql/init.sql\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    $cleanup();
    exit(1);
}
$cleanup();

/**
 * Runs a command, feeding it $stdin, and returns its output. Throws with the
 * output if it fails.
 *
 * @param list<string> $command
 * @param array<string, string> $env
 */
function run(array $command, ?string $stdin, array $env = []): string
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + getenv());
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start ' . $command[0] . '.');
    }
    // Write stdin while draining stdout and stderr, or a large input or output
    // fills a pipe buffer and both sides wait on each other.
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $input = (string) $stdin;
    $out = $err = '';
    $written = 0;
    $length = strlen($input);
    if ($length === 0) {
        fclose($pipes[0]);
    }
    while (true) {
        $read = [$pipes[1], $pipes[2]];
        $write = $written < $length ? [$pipes[0]] : [];
        $except = null;
        if (stream_select($read, $write, $except, 1) === false) {
            break;
        }
        if ($write !== []) {
            $chunk = fwrite($pipes[0], substr($input, $written, 65536));
            $written += (int) $chunk;
            if ($written >= $length) {
                fclose($pipes[0]);
            }
        }
        foreach ($read as $pipe) {
            $data = (string) fread($pipe, 65536);
            if ($pipe === $pipes[1]) {
                $out .= $data;
            } else {
                $err .= $data;
            }
        }
        if (feof($pipes[1]) && feof($pipes[2])) {
            break;
        }
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException(basename($command[1] ?? $command[0]) . " failed:\n" . trim($err . "\n" . $out));
    }
    return $out;
}
