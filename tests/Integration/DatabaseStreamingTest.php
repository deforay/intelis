<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use mysqli;
use mysqli_sql_exception;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\TestRun;

/**
 * rawQueryGenerator() handing back rows as they are read, against a real server.
 *
 * It read the whole result into memory before the first row came back, so an export
 * of a large lab's data held all of it at once. The rows now come over a connection
 * of their own. What must hold for the fifty-odd callers: the same rows with the same
 * values; queries of their own between rows; stopping part way; an open transaction's
 * own writes still seen; the same session settings; no connection left behind.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run. Without them the suite skips.
 */
final class DatabaseStreamingTest extends TestCase
{
    private const ROWS = 20000;

    private static ?DatabaseService $db = null;
    private static ?mysqli $observer = null;
    private static string $database = '';

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');

        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }

        $port = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        self::$database = TestRun::database('intelis_streaming_test');
        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::$database . '`');
        $bootstrap->query('CREATE DATABASE `' . self::$database . '`');
        $bootstrap->select_db(self::$database);
        $bootstrap->query(
            'CREATE TABLE samples (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tag VARCHAR(32) NULL,
                amount DECIMAL(10,2) NULL,
                ratio DOUBLE NULL,
                taken_on DATETIME NULL,
                note TEXT NULL
            ) ENGINE=InnoDB'
        );
        $bootstrap->query('SET SESSION cte_max_recursion_depth = ' . (self::ROWS + 1));
        $bootstrap->query(
            'INSERT INTO samples (tag, amount, ratio, taken_on, note)
                WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM n WHERE i < ' . self::ROWS . ')
                SELECT IF(i % 7 = 0, NULL, CONCAT(\'t\', i)), i / 4, i / 3, \'2026-10-13 08:30:00\' + INTERVAL i MINUTE,
                       REPEAT(\'x\', 1000)
                  FROM n'
        );
        $bootstrap->close();

        self::$db = new DatabaseService([
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => self::$database,
            'port' => $port,
        ]);
        self::$observer = new mysqli($host, $user, $password, self::$database, $port);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$observer instanceof mysqli) {
            self::$observer->query('DROP DATABASE IF EXISTS `' . self::$database . '`');
            self::$observer->close();
        }
    }

    protected function setUp(): void
    {
        if (!self::$db instanceof DatabaseService) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        self::$db->ensureConnection();
        self::$db->rollbackTransaction();
    }

    /** Connections to the test database, as the server counts them. */
    private function openConnections(): int
    {
        $row = self::$observer->query(
            "SELECT COUNT(*) AS c FROM information_schema.PROCESSLIST WHERE DB = '" . self::$database . "'"
        )->fetch_assoc();
        return (int) $row['c'];
    }

    /**
     * The connections are back to $before. A closed connection leaves the server's
     * list a moment after close() returns, so the count is read again for up to
     * two seconds. A connection left open stays in the list, and still fails here.
     */
    private function assertConnectionsBackTo(int $before): void
    {
        $deadline = microtime(true) + 2.0;
        while (($open = $this->openConnections()) !== $before && microtime(true) < $deadline) {
            usleep(20000);
        }
        $this->assertSame($before, $open);
    }

    public function testTheRowsAreNotHeldInMemoryAllAtOnce(): void
    {
        // About 20 MB of rows. Held at once, that is what memory grows by.
        $before = memory_get_usage();
        $peak = 0;
        $count = 0;
        foreach (self::$db->rawQueryGenerator('SELECT * FROM samples ORDER BY id') as $row) {
            $count++;
            $peak = max($peak, memory_get_usage() - $before);
        }

        $this->assertSame(self::ROWS, $count);
        $this->assertLessThan(2 * 1024 * 1024, $peak, 'grew by ' . round($peak / 1048576, 1) . ' MB');
    }

    public function testTheRowsAndTheirValuesAreTheSameAsAnOrdinaryQueryGives(): void
    {
        $sql = 'SELECT * FROM samples WHERE id <= ? OR tag IS NULL ORDER BY id';

        $streamed = iterator_to_array(self::$db->rawQueryGenerator($sql, [300]), false);

        $this->assertSame(self::$db->rawQuery($sql, [300]), $streamed);
        $this->assertNull($streamed[6]['tag']);
        $this->assertIsInt($streamed[0]['id']);
    }

    public function testTheCallerCanQueryBetweenRows(): void
    {
        $seen = [];
        foreach (self::$db->rawQueryGenerator('SELECT id FROM samples WHERE id <= 5 ORDER BY id') as $row) {
            $seen[] = self::$db->rawQueryOne('SELECT tag FROM samples WHERE id = ?', [$row['id']])['tag'];
        }

        $this->assertSame(['t1', 't2', 't3', 't4', 't5'], $seen);
    }

    public function testStoppingPartWayIsQuickAndLeavesNothingBehind(): void
    {
        $before = $this->openConnections();
        $started = microtime(true);

        $taken = 0;
        foreach (self::$db->rawQueryGenerator('SELECT * FROM samples a CROSS JOIN samples b LIMIT 5000000') as $row) {
            if (++$taken === 10) {
                break;
            }
        }
        gc_collect_cycles();

        $this->assertLessThan(5.0, microtime(true) - $started);
        $this->assertConnectionsBackTo($before);
        $this->assertSame(1, self::$db->rawQueryOne('SELECT 1 AS one')['one']);
    }

    public function testFinishingClosesItsConnection(): void
    {
        $before = $this->openConnections();

        iterator_to_array(self::$db->rawQueryGenerator('SELECT id FROM samples WHERE id <= 3'));

        $this->assertConnectionsBackTo($before);
    }

    public function testInsideATransactionItSeesWhatTheTransactionWroteAndHasNotCommitted(): void
    {
        self::$db->beginTransaction();
        try {
            self::$db->rawQuery("INSERT INTO samples (tag) VALUES ('uncommitted')");

            $tags = array_column(
                iterator_to_array(self::$db->rawQueryGenerator(
                    "SELECT tag FROM samples WHERE tag = 'uncommitted'"
                ), false),
                'tag'
            );

            $this->assertSame(['uncommitted'], $tags);
        } finally {
            self::$db->rollbackTransaction();
        }
    }

    public function testItReadsWithTheSameSessionSettingsAsTheApplicationConnection(): void
    {
        $sql = 'SELECT @@session.time_zone AS tz, @@collation_connection AS coll, @@character_set_connection AS cs';
        $zone = date_default_timezone_get();
        date_default_timezone_set('Asia/Kolkata');
        try {
            self::$db->applySessionTimeZone();

            $streamed = iterator_to_array(self::$db->rawQueryGenerator($sql), false)[0];

            $this->assertSame(self::$db->rawQueryOne($sql), $streamed);
            $this->assertSame('+05:30', $streamed['tz']);
        } finally {
            date_default_timezone_set($zone);
            self::$db->applySessionTimeZone();
        }
    }

    public function testABadQueryStillThrowsAndLeavesNoConnection(): void
    {
        $before = $this->openConnections();

        try {
            iterator_to_array(self::$db->rawQueryGenerator('SELECT no_such_column FROM samples'));
            $this->fail('the query should have failed');
        } catch (mysqli_sql_exception) {
        }

        $this->assertConnectionsBackTo($before);
        $this->assertSame(1, self::$db->rawQueryOne('SELECT 1 AS one')['one']);
    }

    public function testWhenNoOtherConnectionCanBeOpenedTheRowsAreStillRead(): void
    {
        $settings = new ReflectionProperty(DatabaseService::class, 'connectionsSettings');
        $original = $settings->getValue(self::$db);
        $unreachable = $original;
        $unreachable['default']['host'] = '127.0.0.1';
        $unreachable['default']['port'] = 1;
        $settings->setValue(self::$db, $unreachable);
        try {
            $rows = self::$db->rawQueryGenerator('SELECT id FROM samples WHERE id <= 3 ORDER BY id');
            $ids = array_column(iterator_to_array($rows, false), 'id');
        } finally {
            $settings->setValue(self::$db, $original);
        }

        $this->assertSame([1, 2, 3], $ids);
    }
}
