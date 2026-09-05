<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use mysqli;
use PHPUnit\Framework\TestCase;

/**
 * Whether the migration runner can tell it already has an index.
 *
 * It used to answer that by name alone, so an index over the same column under
 * a different name was invisible: 5.7.56 added `idx_last_modified_datetime`
 * where sql/init.sql already ships `last_modified_datetime`, and every fresh
 * install came out carrying two copies of one index, paying the write
 * maintenance for both. Comparing the column list closes that.
 *
 * The comparison has to stay exact in two directions, which is most of what
 * these tests are for. A composite over (a, b) is not the index (a) and must
 * still be created. A UNIQUE index over the same columns as a plain one carries
 * a constraint the plain one does not, so treating them as the same would drop
 * that constraint silently -- a worse outcome than a redundant index.
 *
 * bin/migrate.php runs its own body on include, so the functions are lifted out
 * with the tokenizer rather than copied here. What runs below is the shipped
 * code, byte for byte.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class MigrationIndexGuardTest extends TestCase
{
    private const DATABASE = 'intelis_migration_index_test';
    private const WANTED = ['current_db', 'index_exists', 'index_column_list', 'equivalent_index_exists'];

    private static ?DatabaseService $db = null;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');
        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }

        $port     = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $bootstrap->query('CREATE DATABASE `' . self::DATABASE . '`');
        $bootstrap->select_db(self::DATABASE);
        $bootstrap->query(
            'CREATE TABLE probe (
                id INT AUTO_INCREMENT PRIMARY KEY,
                a VARCHAR(64) NULL,
                b VARCHAR(64) NULL,
                note VARCHAR(255) NULL,
                last_modified_datetime DATETIME NULL
            ) ENGINE=InnoDB'
        );
        // The shape sql/init.sql ships: the index is there, under its own name.
        $bootstrap->query('ALTER TABLE probe ADD INDEX `last_modified_datetime` (`last_modified_datetime`)');
        $bootstrap->query('ALTER TABLE probe ADD INDEX `plain_a` (`a`)');
        $bootstrap->query('ALTER TABLE probe ADD INDEX `note_prefix` (`note`(10))');
        $bootstrap->close();

        self::$db = new DatabaseService([
            'host' => $host, 'username' => $user, 'password' => $password,
            'db' => self::DATABASE, 'port' => $port,
        ]);

        self::loadRunnerFunctions();
    }

    /** Lift the named functions out of bin/migrate.php without running its body. */
    private static function loadRunnerFunctions(): void
    {
        if (function_exists('equivalent_index_exists')) {
            return;
        }

        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/migrate.php');
        $tokens = token_get_all($source);

        $out = "<?php\nuse App\\Services\\DatabaseService;\n";
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
            if (!in_array($tokens[$j][1], self::WANTED, true)) {
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

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run.');
        }
        $this->assertTrue(function_exists('equivalent_index_exists'), 'Runner functions were not loaded.');
    }

    /** The case that caused this: same column, different name. */
    public function testAnIndexOverTheSameColumnIsFoundUnderAnotherName(): void
    {
        $this->assertFalse(
            index_exists(self::$db, 'probe', 'idx_last_modified_datetime'),
            'The name is genuinely free, which is why the name check alone let the duplicate through.'
        );
        $this->assertTrue(
            equivalent_index_exists(self::$db, 'probe', ['last_modified_datetime'], false),
            'An index over that column already exists and must be recognised whatever it is called.'
        );
    }

    /** A composite is not the same index as one over its leading column. */
    public function testACompositeIsNotTheSameAsItsLeadingColumn(): void
    {
        $this->assertFalse(
            equivalent_index_exists(self::$db, 'probe', ['a', 'b'], false),
            '(a, b) must still be created when only (a) exists.'
        );
        $this->assertTrue(
            equivalent_index_exists(self::$db, 'probe', ['a'], false),
            'and (a) itself is already there.'
        );
    }

    /** A UNIQUE index carries a constraint a plain one does not. */
    public function testUniquenessMustMatch(): void
    {
        $this->assertFalse(
            equivalent_index_exists(self::$db, 'probe', ['a'], true),
            'A UNIQUE index over (a) must still be created when only a plain (a) exists.'
        );
    }

    /** A prefix length makes two indexes incomparable, so nothing is skipped. */
    public function testPrefixedIndexesAreNeverTreatedAsEquivalent(): void
    {
        $this->assertNull(index_column_list('`note`(10)'), 'A prefix length is not comparable.');
        $this->assertFalse(
            equivalent_index_exists(self::$db, 'probe', ['note'], false),
            'The existing note index is prefixed, so it must not satisfy a request for the whole column.'
        );
    }

    /** Column lists are parsed the way the ALTER routes hand them over. */
    public function testColumnListParsing(): void
    {
        $this->assertSame(['a'], index_column_list('`a`'));
        $this->assertSame(['a', 'b'], index_column_list('`a`, `b`'));
        $this->assertSame(['a', 'b'], index_column_list('a,b'));
        $this->assertNull(index_column_list(''), 'Nothing to compare.');
        $this->assertNull(index_column_list('`a` DESC'), 'An ordered index is not compared.');
    }
}
