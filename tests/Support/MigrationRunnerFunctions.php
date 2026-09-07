<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\DatabaseService;
use mysqli;

/**
 * Lifts bin/migrate.php's functions into the test process without running it.
 *
 * The runner is a script, not a class: including it would connect to a
 * database, read the recorded version and start applying migrations. Its
 * functions are what the tests need, so they are extracted with the tokenizer
 * and required on their own. What runs under test is the shipped code, byte for
 * byte -- a copy kept here would pass long after the runner stopped behaving
 * the way the copy describes.
 *
 * Every function is taken rather than a chosen few. A dispatcher test is only
 * worth anything if it reaches the same helpers production reaches, and a
 * hand-kept list of names is one more thing to fall out of step.
 *
 * It also owns the database those functions run against, and there is only one
 * of it per process on purpose. `current_db()` in the runner caches the schema
 * name in a static the first time it is asked, which is correct for the runner
 * -- one process migrates one installation -- and means a second test class
 * working in a database of its own would have every column_exists() answered
 * against the first one's schema. Everything would report itself already
 * applied and every assertion about the runner would be meaningless.
 */
final class MigrationRunnerFunctions
{
    /** The one schema every migration test shares; see the class note. */
    public const DATABASE = 'intelis_migration_test';

    private static ?DatabaseService $db = null;

    /**
     * The shared test database, or null when the environment does not name a
     * server -- which is how the migration suites skip themselves.
     */
    public static function connect(): ?DatabaseService
    {
        if (self::$db !== null) {
            return self::$db;
        }

        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');
        if ($host === false || $host === '' || $user === false || $user === '') {
            return null;
        }

        $port     = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $bootstrap->query('CREATE DATABASE `' . self::DATABASE . '`');
        $bootstrap->select_db(self::DATABASE);
        self::loadRealSchema($bootstrap);
        $bootstrap->close();

        self::load();

        return self::$db = new DatabaseService([
            'host' => $host, 'username' => $user, 'password' => $password,
            'db' => self::DATABASE, 'port' => $port,
        ]);
    }

    /**
     * Build the real schema, from sql/init.sql, rather than a stand-in.
     *
     * A test that invents `CREATE TABLE probe (id INT)` is testing the runner
     * against a table no installation has. That gap has cost real time: a
     * foreign key with nothing to point at, an AFTER clause naming a column the
     * stand-in never had -- failures that say nothing about the code and have
     * to be diagnosed anyway. The columns, keys, defaults and collations here
     * are the ones a lab actually carries.
     *
     * Through multi_query rather than the SQL parser the runner uses: init.sql
     * opens with SET SQL_MODE and START TRANSACTION, and the parser's build()
     * runs those together into one statement the server rejects, leaving an
     * empty database and every later assertion meaningless.
     *
     * Around half a second for 139 tables, once per process, which is why the
     * connection is cached rather than the schema rebuilt per test.
     */
    private static function loadRealSchema(mysqli $bootstrap): void
    {
        $path = dirname(__DIR__, 2) . '/sql/init.sql';
        $sql = @file_get_contents($path);
        if ($sql === false || $sql === '') {
            return;     // no seed to build from; tests fall back to their own tables
        }

        if ($bootstrap->multi_query($sql)) {
            do {
                if ($result = $bootstrap->store_result()) {
                    $result->free();
                }
            } while ($bootstrap->more_results() && $bootstrap->next_result());
        }
    }

    public static function load(): void
    {
        if (function_exists('handle_idempotent_ddl')) {
            return;
        }

        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/migrate.php');
        $tokens = token_get_all($source);

        $out = "<?php\nuse App\\Services\\DatabaseService;\n"
            . "use Symfony\\Component\\Console\\Style\\SymfonyStyle;\n"
            . "const MIG_NOT_HANDLED = 0;\nconst MIG_EXECUTED = 1;\nconst MIG_SKIPPED = 2;\n";
        for ($i = 0, $n = count($tokens); $i < $n; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            // The name follows the keyword, past whitespace.
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
                continue;
            }

            // Copy from the keyword to the brace that closes the body.
            $depth = 0;
            $started = false;
            $body = '';
            for ($k = $i; $k < $n; $k++) {
                $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                $body .= $text;
                if ($text === '{') {
                    $depth++;
                    $started = true;
                } elseif ($text === '}') {
                    $depth--;
                    if ($started && $depth === 0) {
                        break;
                    }
                }
            }
            $out .= "\n" . $body . "\n";
        }

        $tmp = sys_get_temp_dir() . '/intelis-migrate-fns-' . getmypid() . '.php';
        file_put_contents($tmp, $out);
        require $tmp;
        unlink($tmp);
    }
}
