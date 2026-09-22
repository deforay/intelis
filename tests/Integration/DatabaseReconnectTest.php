<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use mysqli;
use mysqli_sql_exception;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Reopening a connection the server has dropped, against a real server.
 *
 * A long-running task (a sync pull, a results send, an import) sits idle past
 * wait_timeout, or the server restarts under it, and its next statement fails with
 * "server has gone away". A statement that fails while being prepared never ran, so
 * it can be sent again on a new connection. One that failed while running may have
 * done its work and must not be; nor may anything inside a transaction, which the
 * server threw away with the connection.
 *
 * Connections are killed from a second connection, the way the server drops them.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run. Without them the suite skips.
 */
final class DatabaseReconnectTest extends TestCase
{
    private const DATABASE = 'intelis_reconnect_test';
    private const CONNECTION_LOST = [2006, 2013, 4031];

    private static ?DatabaseService $db = null;
    private static ?mysqli $killer = null;
    private static array $settings = [];

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');

        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }

        $port = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $bootstrap->query('CREATE DATABASE `' . self::DATABASE . '`');
        $bootstrap->select_db(self::DATABASE);
        $bootstrap->query(
            'CREATE TABLE samples (id INT AUTO_INCREMENT PRIMARY KEY, tag VARCHAR(32)) ENGINE=InnoDB'
        );
        $bootstrap->close();

        self::$settings = [
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => self::DATABASE,
            'port' => $port,
        ];
        self::$db = new DatabaseService(self::$settings);
        self::$killer = new mysqli($host, $user, $password, self::DATABASE, $port);
    }

    protected function setUp(): void
    {
        if (!self::$db instanceof DatabaseService) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        // A test that leaves the connection dead or a transaction open must not
        // hand that to the next one.
        self::$db->ensureConnection();
        self::$db->rollbackTransaction();
        self::$killer->query('DELETE FROM samples');
    }

    private function connectionId(): int
    {
        return (int) self::$db->rawQueryOne('SELECT CONNECTION_ID() AS id')['id'];
    }

    /** Drop the connection from the server side, the way wait_timeout or a restart does. */
    private function killConnection(): int
    {
        $id = $this->connectionId();
        self::$killer->query('KILL ' . $id);
        return $id;
    }

    /** Rows as the server has them, read on a connection of their own. */
    private function tags(): array
    {
        $result = self::$killer->query('SELECT tag FROM samples ORDER BY id');
        return array_column($result->fetch_all(MYSQLI_ASSOC), 'tag');
    }

    public function testAQueryAfterTheConnectionWasDroppedRunsOnANewConnection(): void
    {
        $old = $this->killConnection();

        $new = $this->connectionId();

        $this->assertNotSame($old, $new);
    }

    public function testAConnectionTheServerClosedForIdlingIsReopened(): void
    {
        self::$db->rawQuery('SET SESSION wait_timeout = 1');
        sleep(3);

        $this->assertSame(1, (int) self::$db->rawQueryOne('SELECT 1 AS one')['one']);
    }

    public function testAWhereClauseIsKeptForTheRetriedStatement(): void
    {
        self::$killer->query("INSERT INTO samples (tag) VALUES ('a'), ('b'), ('c')");
        $this->killConnection();

        // Sent again without its WHERE, this would rename every row.
        self::$db->where('tag', 'b')->update('samples', ['tag' => 'x']);

        $this->assertSame(['a', 'x', 'c'], $this->tags());
    }

    public function testAWhereClauseIsKeptForARetriedRead(): void
    {
        self::$killer->query("INSERT INTO samples (tag) VALUES ('a'), ('b')");
        $this->killConnection();

        $rows = self::$db->where('tag', 'b')->get('samples');

        $this->assertSame(['b'], array_column($rows, 'tag'));
    }

    public function testBoundValuesAreKeptForARetriedRawQuery(): void
    {
        $this->killConnection();

        self::$db->rawQuery('INSERT INTO samples (tag) VALUES (?), (?)', ['p', 'q']);

        $this->assertSame(['p', 'q'], $this->tags());
    }

    public function testARetriedInsertWritesItsRowOnce(): void
    {
        $this->killConnection();

        self::$db->insert('samples', ['tag' => 'once']);

        $this->assertSame(['once'], $this->tags());
    }

    public function testAStreamedQueryRunsAfterTheConnectionWasDropped(): void
    {
        self::$killer->query("INSERT INTO samples (tag) VALUES ('a'), ('b')");
        $this->killConnection();

        $tags = [];
        foreach (self::$db->rawQueryGenerator('SELECT tag FROM samples WHERE tag <> ? ORDER BY id', ['z']) as $row) {
            $tags[] = $row['tag'];
        }

        $this->assertSame(['a', 'b'], $tags);
    }

    public function testTheNewConnectionGetsTheSameSessionSettings(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('Asia/Kolkata');
        try {
            self::$db->applySessionTimeZone();
            $before = self::$db->rawQueryOne(
                'SELECT @@session.time_zone AS tz, @@collation_connection AS coll,'
                    . ' @@character_set_connection AS cs'
            );
            $this->killConnection();

            $after = self::$db->rawQueryOne(
                'SELECT @@session.time_zone AS tz, @@collation_connection AS coll,'
                    . ' @@character_set_connection AS cs'
            );
        } finally {
            date_default_timezone_set($zone);
        }

        $this->assertSame('+05:30', $after['tz']);
        $this->assertSame($before, $after);
    }

    public function testATransactionStartedAfterTheConnectionWasDroppedWorks(): void
    {
        $this->killConnection();

        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'in-tx']);
        self::$db->commitTransaction();

        $this->assertSame(['in-tx'], $this->tags());
    }

    public function testAConnectionLostInsideATransactionIsNotReopened(): void
    {
        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'first']);
        $this->killConnection();

        $error = null;
        try {
            // On a new connection this would commit by itself, without "first".
            self::$db->insert('samples', ['tag' => 'second']);
        } catch (mysqli_sql_exception $e) {
            $error = $e;
        }

        $this->assertNotNull($error, 'The write inside the lost transaction must fail');
        $this->assertContains($error->getCode(), self::CONNECTION_LOST);
        $this->assertSame([], $this->tags());
    }

    public function testRollingBackALostTransactionDoesNotHideTheFailure(): void
    {
        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'first']);
        $this->killConnection();

        try {
            self::$db->insert('samples', ['tag' => 'second']);
        } catch (mysqli_sql_exception) {
            // The server has already discarded the transaction, so the caller's
            // rollback has nothing to undo and must not throw over the real error.
            self::$db->rollbackTransaction();
        }

        self::$db->insert('samples', ['tag' => 'after']);
        self::$db->beginTransaction();
        self::$db->insert('samples', ['tag' => 'next-tx']);
        self::$db->commitTransaction();

        $this->assertSame(['after', 'next-tx'], $this->tags());
    }

    public function testAStatementLostWhileRunningIsNotSentAgain(): void
    {
        $id = $this->connectionId();
        $this->killInBackground($id, 0.5);

        $error = null;
        try {
            // Killed while running. Sent again, it would insert its row a second time
            // for a write that already did its work.
            self::$db->rawQuery("INSERT INTO samples (tag) SELECT IF(SLEEP(3) = 0, 'slow', 'slow')");
        } catch (mysqli_sql_exception $e) {
            $error = $e;
        }

        $this->assertNotNull($error, 'A statement killed while running must fail');
        $this->assertContains($error->getCode(), self::CONNECTION_LOST);
        // The kill cuts SLEEP short, so the server may well have written the row
        // before dropping the connection. It must not have written it twice.
        $this->assertLessThanOrEqual(1, count($this->tags()));
    }

    public function testAnErrorThatIsNotALostConnectionIsNotRetried(): void
    {
        $id = $this->connectionId();

        try {
            self::$db->rawQuery('SELECT * FROM no_such_table');
            $this->fail('A query on a missing table must fail');
        } catch (mysqli_sql_exception $e) {
            $this->assertSame(1146, $e->getCode());
        }

        $this->assertSame($id, $this->connectionId());
    }

    public function testWhenTheServerCannotBeReachedTheLostConnectionIsReported(): void
    {
        $settings = new ReflectionProperty(DatabaseService::class, 'connectionsSettings');
        $saved = $settings->getValue(self::$db);
        $unreachable = $saved;
        $unreachable['default']['port'] = 1;
        $unreachable['default']['host'] = '127.0.0.1';

        $this->killConnection();
        $settings->setValue(self::$db, $unreachable);
        try {
            self::$db->rawQuery('SELECT 1');
            $this->fail('A query with no server to reach must fail');
        } catch (mysqli_sql_exception $e) {
            $this->assertContains($e->getCode(), self::CONNECTION_LOST);
        } finally {
            $settings->setValue(self::$db, $saved);
        }

        // Once the server is back, the next statement gets through.
        $this->assertSame(1, (int) self::$db->rawQueryOne('SELECT 1 AS one')['one']);
    }

    private function killInBackground(int $connectionId, float $afterSeconds): void
    {
        $s = self::$settings;
        $script = sprintf(
            'usleep(%d); $m = new mysqli(%s, %s, %s, null, %d); $m->query("KILL %d");',
            (int) ($afterSeconds * 1_000_000),
            var_export($s['host'], true),
            var_export($s['username'], true),
            var_export($s['password'], true),
            $s['port'],
            $connectionId
        );
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' > /dev/null 2>&1 &');
    }
}
