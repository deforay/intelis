<?php

declare(strict_types=1);

namespace Tests\Support;

use mysqli;
use RuntimeException;
use Throwable;

/**
 * Names the databases and the scratch directory of one test run, and removes them
 * when the run ends.
 *
 * Two runs on one machine -- two sessions, or two worktrees -- share a MySQL server
 * and a temporary directory. A fixed database name lets one run drop the tables the
 * other is reading, and the failures that follow look like flaky tests. Every name
 * here carries an id that only this run has.
 *
 * The first process to load the bootstrap makes the id and puts it in the
 * environment. A test that runs in a separate process inherits it, so a test and
 * its child agree on every name. ParaTest workers also add their worker number,
 * so two workers never share a database even when they inherit one id.
 *
 * The process that made the id drops the run's databases and deletes its
 * directory when it exits. A run that is killed leaves them behind; their names
 * end in that run's id.
 */
final class TestRun
{
    private const ENV = 'INTELIS_TEST_RUN';

    /** MySQL refuses a longer database name. */
    private const MAX_NAME = 64;

    /**
     * Every test database starts with this, so the MySQL user that composer
     * setup-tests creates is granted itest_% and can reach nothing else.
     */
    public const DATABASE_PREFIX = 'itest_';

    public static function start(): void
    {
        if (self::id() !== '') {
            return;
        }

        $id = bin2hex(random_bytes(4));
        putenv(self::ENV . '=' . $id);
        $_ENV[self::ENV] = $id;
        $_SERVER[self::ENV] = $id;

        register_shutdown_function(static function () use ($id): void {
            self::dropDatabases($id);
            self::removeDirectories($id);
        });
    }

    /**
     * The name of a test database, unique to this run and this worker.
     */
    public static function database(string $base): string
    {
        $name = self::DATABASE_PREFIX . $base . '_' . self::suffix();
        if (strlen($name) > self::MAX_NAME) {
            throw new RuntimeException("Test database name is longer than MySQL allows: $name");
        }
        return $name;
    }

    /**
     * This run's scratch directory: logs, caches and files a test writes.
     */
    public static function varPath(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'intelis-tests-' . self::suffix();
    }

    private static function id(): string
    {
        return (string) getenv(self::ENV);
    }

    private static function suffix(): string
    {
        $id = self::id();
        if ($id === '') {
            throw new RuntimeException('TestRun::start() has not run. tests/bootstrap.php calls it.');
        }
        $worker = (string) getenv('TEST_TOKEN');
        return $worker === '' ? $id : $id . '_w' . $worker;
    }

    private static function dropDatabases(string $id): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');
        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }

        try {
            $mysqli = new mysqli(
                $host,
                $user,
                (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
                null,
                (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
            );
            // A connection left inside a transaction would make DROP wait for its
            // lock. Give up on that database instead of hanging the run's exit.
            $mysqli->query('SET SESSION lock_wait_timeout = 10');
            $result = $mysqli->query(
                "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA
                  WHERE SCHEMA_NAME LIKE 'itest\\_%\\_{$id}%'"
            );
            foreach ($result->fetch_all() as [$name]) {
                try {
                    $mysqli->query("DROP DATABASE IF EXISTS `$name`");
                } catch (Throwable) {
                    // Left behind; its name ends in this run's id.
                }
            }
            $mysqli->close();
        } catch (Throwable) {
            // The server is gone or refused the login. Nothing to clean up with.
        }
    }

    private static function removeDirectories(string $id): void
    {
        $pattern = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'intelis-tests-' . $id . '*';
        foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $dir) {
            self::removeTree($dir);
        }
    }

    private static function removeTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path) && !is_link($path)) {
                self::removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
