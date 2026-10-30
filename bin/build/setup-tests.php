<?php

declare(strict_types=1);

// Set up a development checkout to run the whole test suite:
//
//   1. a MySQL user for the integration tests, intelis_test, saved in configs/test-db.php
//   2. PHP's SQLite driver, which the error index tests need
//
// intelis_test can only reach databases named itest_*, the prefix every test
// database has (Tests\Support\TestRun), so a test can never drop the app's
// database. Creating it needs an admin login once: the app's own
// login is offered first, and the admin password is never saved. The saved
// login is proved by creating and dropping a database.
//
// composer qc runs this on its first run on a terminal. Run it again at any
// time: it resets the intelis_test password and saves the new login.
//
// Run: composer setup-tests   (or: php bin/build/setup-tests.php)

// A command-line script that defines its helpers and runs: PSR1's side-effects
// rule does not fit it.
// phpcs:disable PSR1.Files.SideEffects

require_once dirname(__DIR__, 2) . '/tests/Support/TestDbSettings.php';

use Tests\Support\TestDbSettings;

if (!stream_isatty(STDIN)) {
    fwrite(STDERR, "setup-tests: asks questions, so it needs a terminal.\n");
    exit(1);
}

function ask(string $question, string $default = ''): string
{
    echo $default === '' ? "{$question}: " : "{$question} [{$default}]: ";
    $line = fgets(STDIN);
    if ($line === false) {
        echo "\n";
        return $default;
    }
    $answer = trim($line);
    return $answer === '' ? $default : $answer;
}

function confirm(string $question): bool
{
    echo "{$question} [Y/n] ";
    $line = fgets(STDIN);
    if ($line === false) {
        echo "\n";
        return false;
    }
    $answer = strtolower(trim($line));
    return $answer === '' || $answer === 'y' || $answer === 'yes';
}

function askSecret(string $question): string
{
    echo "{$question}: ";
    shell_exec('stty -echo 2>/dev/null');
    $line = fgets(STDIN);
    shell_exec('stty echo 2>/dev/null');
    echo "\n";
    return $line === false ? '' : rtrim($line, "\r\n");
}

/**
 * Connect, or return the reason the login cannot connect.
 */
function connect(string $host, int $port, string $user, string $pass): mysqli|string
{
    if (!extension_loaded('mysqli')) {
        return 'PHP has no mysqli extension.';
    }
    try {
        return new mysqli($host, $user, $pass, null, $port);
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/**
 * Return null when the login can create and drop a test database, or the reason it cannot.
 */
function probe(string $host, int $port, string $user, string $pass): ?string
{
    $mysqli = connect($host, $port, $user, $pass);
    if (is_string($mysqli)) {
        return $mysqli;
    }
    $database = 'itest_setup_probe_' . bin2hex(random_bytes(4));
    try {
        $mysqli->query("CREATE DATABASE `{$database}`");
        $mysqli->query("DROP DATABASE `{$database}`");
        return null;
    } catch (Throwable $e) {
        return $e->getMessage();
    } finally {
        $mysqli->close();
    }
}

/**
 * The app's own MySQL login, from configs/config.<APPLICATION_ENV>.php.
 *
 * @return array{host: string, port: int, user: string, pass: string}|null
 */
function appLogin(): ?array
{
    $environment = getenv('APPLICATION_ENV') ?: 'production';
    $path = dirname(__DIR__, 2) . "/configs/config.{$environment}.php";
    if (!is_file($path)) {
        return null;
    }
    $config = (static fn (string $file) => include $file)($path);
    $database = is_array($config) ? ($config['database'] ?? []) : [];
    if (($database['host'] ?? '') === '' || ($database['username'] ?? '') === '') {
        return null;
    }
    return [
        'host' => (string) $database['host'],
        'port' => (int) ($database['port'] ?? 3306),
        'user' => (string) $database['username'],
        'pass' => (string) ($database['password'] ?? ''),
    ];
}

/**
 * Create or reset the intelis_test user with the admin connection, and return
 * its login, or the reason it could not be made.
 *
 * The user gets every privilege on databases named itest_* and nothing else,
 * so a test can never reach the app's own database.
 *
 * @return array{host: string, port: int, user: string, pass: string}|string
 */
function createTestUser(mysqli $admin, string $host, int $port): array|string
{
    $password = bin2hex(random_bytes(16));
    $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    // A connection to 127.0.0.1 reaches MySQL as 'localhost' or '127.0.0.1'
    // depending on its name resolution, so a local server gets both accounts.
    $accounts = $local ? ["'intelis_test'@'localhost'", "'intelis_test'@'127.0.0.1'"] : ["'intelis_test'@'%'"];
    try {
        foreach ($accounts as $account) {
            $admin->query("CREATE USER IF NOT EXISTS {$account}");
            $admin->query("ALTER USER {$account} IDENTIFIED BY '{$password}'");
            $admin->query("GRANT ALL PRIVILEGES ON `itest\\_%`.* TO {$account}");
            // Two tests also set session variables or read MySQL's error counters.
            // Neither reaches app data. MariaDB has no SESSION_VARIABLES_ADMIN, so
            // each grant is tried alone and those tests fail there if it is missing.
            foreach (['SESSION_VARIABLES_ADMIN ON *.*', 'SELECT ON performance_schema.*'] as $grant) {
                try {
                    $admin->query("GRANT {$grant} TO {$account}");
                } catch (Throwable) {
                }
            }
        }
    } catch (Throwable $e) {
        return $e->getMessage();
    }
    return ['host' => $host, 'port' => $port, 'user' => 'intelis_test', 'pass' => $password];
}

/**
 * Databases the intelis_test user could reach that are not left over from a
 * test run (those end in an 8-character run id, plus a worker number).
 *
 * @return list<string>
 */
function realDatabasesInReach(mysqli $admin): array
{
    $names = [];
    $result = $admin->query("SHOW DATABASES LIKE 'itest\\_%'");
    while ($row = $result->fetch_row()) {
        if (preg_match('/_[0-9a-f]{8}(_w\d+)?$/', $row[0]) !== 1) {
            $names[] = $row[0];
        }
    }
    return $names;
}

// 1. MySQL login for the integration tests.
echo "Integration tests\n";
echo "  These run as a MySQL user named intelis_test that can only create, use and\n";
echo "  drop databases named itest_*. Setting it up needs an admin login once, such\n";
echo "  as root. The admin password is not saved.\n";

$saved = null;
$admin = null;
$app = appLogin();
if ($app !== null && confirm("  Use the app's MySQL login, {$app['user']}@{$app['host']}, as the admin login?")) {
    $admin = connect($app['host'], $app['port'], $app['user'], $app['pass']);
    if (is_string($admin)) {
        echo "  Could not connect: {$admin}\n";
        $admin = null;
    } else {
        $adminHost = $app['host'];
        $adminPort = $app['port'];
    }
}

while ($saved === null) {
    if ($admin === null) {
        $user = ask('  Admin MySQL user (- skips the integration tests)', 'root');
        if ($user === '-') {
            $saved = [];
            break;
        }
        $adminHost = ask('  MySQL host', '127.0.0.1');
        $adminPort = (int) ask('  MySQL port', '3306');
        $connection = connect($adminHost, $adminPort, $user, askSecret('  Password'));
        if (is_string($connection)) {
            echo "  Could not connect: {$connection}\n";
            if (!confirm('  Try another login?')) {
                $saved = [];
            }
            continue;
        }
        $admin = $connection;
    }

    // A real database named itest_* is unlikely, but the test user could drop it.
    $inReach = realDatabasesInReach($admin);
    if ($inReach !== []) {
        echo '  Stopped: intelis_test could drop these databases: ' . implode(', ', $inReach) . "\n";
        echo "  Rename them, then run composer setup-tests again.\n";
        $saved = [];
        break;
    }

    $login = createTestUser($admin, $adminHost, $adminPort);
    $admin->close();
    $admin = null;
    if (is_string($login)) {
        echo "  That login cannot create users: {$login}\n";
        continue;
    }
    $reason = probe($login['host'], $login['port'], $login['user'], $login['pass']);
    if ($reason !== null) {
        echo "  intelis_test was created but cannot create a database: {$reason}\n";
        continue;
    }
    $saved = $login;
}

TestDbSettings::save($saved);
echo $saved === []
    ? "  Skipped. Run composer setup-tests to set up the integration tests later.\n"
    : "  Created intelis_test and saved its login in configs/test-db.php.\n";

// 2. SQLite driver for the error index tests.
echo "\nSQLite driver\n";
$package = 'php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-sqlite3';
if (extension_loaded('pdo_sqlite')) {
    echo "  Installed.\n";
} elseif (PHP_OS_FAMILY === 'Linux' && trim((string) shell_exec('command -v apt-get')) !== '') {
    if (confirm("  PHP has no pdo_sqlite. Install {$package} now (runs sudo apt-get)?")) {
        passthru('sudo apt-get install -y ' . escapeshellarg($package), $exitCode);
        echo $exitCode === 0 ? "  Installed.\n" : "  The install failed. Run: sudo apt-get install {$package}\n";
    } else {
        echo "  Skipped. The error index tests skip until pdo_sqlite is installed.\n";
    }
} else {
    echo "  PHP has no pdo_sqlite. Install PHP's SQLite extension. The error index tests skip until then.\n";
}
