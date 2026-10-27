<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use mysqli;
use mysqli_sql_exception;
use PHPUnit\Framework\TestCase;

/**
 * A statement InnoDB picks as a deadlock victim runs again, against a real server.
 *
 * On one lab a foreground and a background run-once script updated form_vl in
 * the same second, and both died on 1213. Outside a transaction the statement
 * is the whole transaction and InnoDB rolled it back, so running it again is
 * safe. Inside one, everything before it was rolled back too, and the caller
 * has to see the error.
 *
 * The other writer is a separate PHP process. It locks many rows, including
 * row 1, so it is the heavier transaction and the statement under test is the
 * victim. It then asks for row 2, which the statement under test holds while
 * it waits for row 1, and commits shortly after.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run. Without them the suite skips.
 */
final class DatabaseDeadlockRetryTest extends TestCase
{
    private const DATABASE = 'intelis_deadlock_test';

    private static ?DatabaseService $db = null;
    private static array $settings = [];

    /** @var resource|null */
    private $child = null;
    private array $pipes = [];

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');
        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }

        $port = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        // One database per process: a second run against the same server must not
        // drop this one's tables underneath it.
        $database = self::DATABASE . '_' . getmypid();
        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . $database . '`');
        $bootstrap->query('CREATE DATABASE `' . $database . '`');
        $bootstrap->close();

        self::$settings = [
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => $database,
            'port' => $port,
        ];
        self::$db = new DatabaseService(self::$settings);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db instanceof DatabaseService) {
            self::$db->rawQuery('DROP DATABASE IF EXISTS `' . self::$settings['db'] . '`');
        }
    }

    protected function setUp(): void
    {
        if (!self::$db instanceof DatabaseService) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        self::$db->ensureConnection();
        self::$db->rollbackTransaction();
        self::$db->rawQuery('DROP TABLE IF EXISTS rows_probe');
        self::$db->rawQuery('CREATE TABLE rows_probe (id INT PRIMARY KEY, v INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
        self::$db->rawQuery(
            'INSERT INTO rows_probe (id) WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM n WHERE i < 300)'
                . ' SELECT i FROM n'
        );
        // Without statistics the optimizer may scan from row 1 up, and the statement
        // under test then queues behind the other writer instead of deadlocking.
        self::$db->rawQuery('ANALYZE TABLE rows_probe');
    }

    /** Deadlocks InnoDB has detected since the server started. */
    private function deadlocks(): int
    {
        return (int) self::$db->rawQueryOne(
            "SELECT `count` AS c FROM information_schema.INNODB_METRICS WHERE name = 'lock_deadlocks'"
        )['c'];
    }

    protected function tearDown(): void
    {
        if (is_resource($this->child)) {
            foreach ($this->pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($this->child);
        }
        $this->child = null;
    }

    /**
     * Start the other writer and return once it holds row 1 and rows 10 to 300.
     *
     * Half a second later it asks for row 2, and commits half a second after that.
     */
    private function startHeavyWriter(): void
    {
        $code = <<<'PHP'
            $s = json_decode(getenv('DEADLOCK_TEST_DB'), true);
            $c = new mysqli($s['host'], $s['username'], $s['password'], $s['db'], $s['port']);
            $c->begin_transaction();
            $c->query('UPDATE rows_probe SET v = v + 1 WHERE id = 1 OR id >= 10');
            echo "ready\n";
            usleep(500000);
            $c->query('UPDATE rows_probe SET v = v + 1 WHERE id = 2');
            usleep(500000);
            $c->commit();
            PHP;

        $this->child = proc_open(
            [PHP_BINARY, '-r', $code],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $this->pipes,
            null,
            ['DEADLOCK_TEST_DB' => json_encode(self::$settings)] + getenv()
        );
        $this->assertIsResource($this->child);
        // Its error output is read only on failure: reading it blocks until the writer exits.
        $ready = fgets($this->pipes[1]);
        if ($ready !== "ready\n") {
            $this->fail('The other writer did not start: ' . stream_get_contents($this->pipes[2]));
        }
    }

    /** Locks row 2, then waits on row 1, which the other writer holds. */
    private const VICTIM = 'UPDATE rows_probe SET v = v + 10 WHERE id BETWEEN 1 AND 2 ORDER BY id DESC';

    public function testADeadlockedStatementOutsideATransactionRunsAgain(): void
    {
        $before = $this->deadlocks();
        $this->startHeavyWriter();

        self::$db->rawQuery(self::VICTIM);

        $this->assertSame($before + 1, $this->deadlocks(), 'The statement must have been a deadlock victim.');

        $rows = self::$db->rawQuery('SELECT id, v FROM rows_probe WHERE id IN (1, 2) ORDER BY id');
        $this->assertSame(
            [['id' => 1, 'v' => 11], ['id' => 2, 'v' => 11]],
            $rows,
            'Both writers must have landed, each exactly once.'
        );
    }

    public function testADeadlockInsideATransactionReachesTheCaller(): void
    {
        $before = $this->deadlocks();
        $this->startHeavyWriter();

        $code = 0;
        self::$db->beginTransaction();
        try {
            self::$db->rawQuery(self::VICTIM);
        } catch (mysqli_sql_exception $e) {
            $code = (int) $e->getCode();
        } finally {
            self::$db->rollbackTransaction();
        }

        $this->assertSame($before + 1, $this->deadlocks(), 'The statement must have been a deadlock victim.');
        $this->assertSame(1213, $code, 'Only the caller can redo the rest of its transaction.');
    }
}
