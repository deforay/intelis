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
        $bootstrap->close();

        self::load();

        return self::$db = new DatabaseService([
            'host' => $host, 'username' => $user, 'password' => $password,
            'db' => self::DATABASE, 'port' => $port,
        ]);
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
