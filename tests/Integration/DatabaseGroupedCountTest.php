<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use mysqli;
use PHPUnit\Framework\TestCase;

/**
 * getDataAndCount() counting a GROUP BY listing, against a real server.
 *
 * A grouped query is counted as a derived table, and a derived table refuses
 * two columns of the same name. "SELECT vl.*, f.* ... GROUP BY" joins two
 * tables that both have facility_id, so the count failed, the listing said
 * "0 of 0 entries" over rows it was showing, and the export refused to run.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run. Without them the suite skips.
 */
final class DatabaseGroupedCountTest extends TestCase
{
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

        self::$database = 'intelis_grouped_count_test_' . getmypid();
        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::$database . '`');
        $bootstrap->query('CREATE DATABASE `' . self::$database . '`');
        $bootstrap->select_db(self::$database);
        $bootstrap->query('CREATE TABLE facilities (facility_id INT PRIMARY KEY, name VARCHAR(32))');
        $bootstrap->query(
            'CREATE TABLE samples (sample_id INT PRIMARY KEY, facility_id INT, result VARCHAR(16) NULL,
                taken_on DATE)'
        );
        $bootstrap->query("INSERT INTO facilities VALUES (1, 'one'), (2, 'two')");
        $bootstrap->query(
            "INSERT INTO samples VALUES (1, 1, NULL, '2026-10-01'), (2, 1, NULL, '2026-10-01'),
                (3, 2, NULL, '2026-10-02'), (4, 2, '50', '2026-10-03')"
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
        // The count is cached in the session by query text; start each test clean.
        unset($_SESSION['queryCounters']);
    }

    /** @return array{0: list<array<string, mixed>>, 1: int} */
    private function dataAndCount(string $sql): array
    {
        [$rows, $count] = self::$db->getDataAndCount($sql . ' LIMIT 0,2');
        return [iterator_to_array($rows, false), $count];
    }

    public function testAGroupedListingOfTwoTablesWithSharedColumnsIsCounted(): void
    {
        [$rows, $count] = $this->dataAndCount(
            'SELECT s.*, f.* FROM samples s LEFT JOIN facilities f ON f.facility_id = s.facility_id
                WHERE s.result IS NULL GROUP BY s.sample_id ORDER BY s.sample_id'
        );

        $this->assertCount(2, $rows, 'the page itself');
        $this->assertSame(3, $count);
    }

    public function testAGroupByOnASelectAliasIsStillCounted(): void
    {
        [, $count] = $this->dataAndCount(
            'SELECT DATE_FORMAT(taken_on, "%Y-%m") AS month, taken_on AS day, COUNT(*) AS n
                FROM samples GROUP BY day'
        );

        $this->assertSame(3, $count);
    }

    public function testAGroupByOnASelectPositionIsStillCounted(): void
    {
        [, $count] = $this->dataAndCount('SELECT facility_id FROM samples GROUP BY 1');

        $this->assertSame(2, $count);

        [, $count] = $this->dataAndCount('SELECT sample_id, taken_on FROM samples GROUP BY 2');

        $this->assertSame(3, $count);
    }

    public function testHavingStillFiltersTheGroupsCounted(): void
    {
        [, $count] = $this->dataAndCount(
            'SELECT facility_id, COUNT(*) AS n FROM samples GROUP BY facility_id HAVING n > 1'
        );

        $this->assertSame(2, $count);

        [, $count] = $this->dataAndCount(
            'SELECT facility_id, COUNT(result) AS n FROM samples GROUP BY facility_id HAVING n > 0'
        );

        $this->assertSame(1, $count);
    }

    public function testDistinctOverGroupsStillCountsDistinctRows(): void
    {
        [, $count] = $this->dataAndCount(
            'SELECT DISTINCT facility_id FROM samples GROUP BY sample_id'
        );

        $this->assertSame(2, $count);
    }
}
