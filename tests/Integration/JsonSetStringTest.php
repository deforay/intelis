<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use App\Utilities\JsonUtility;
use InvalidArgumentException;
use mysqli;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * JsonUtility::jsonToSetString() builds a JSON_SET() expression that callers hand to
 * $db->func(), so everything in it reaches MySQL as SQL text rather than as a bound
 * value. Keys and values come from API clients (the Custom Tests `testTypeForm`
 * object) and from the STS sync, so whatever they hold has to arrive as data.
 *
 * Tested against a real server because what is under test is what MySQL makes of
 * the expression, under the fleet's empty sql_mode and under NO_BACKSLASH_ESCAPES.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class JsonSetStringTest extends TestCase
{
    private const DATABASE = 'intelis_json_set_string_test';

    private static ?DatabaseService $db = null;

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

        // Per process, so two runs on one server cannot drop each other's database.
        self::$database = self::DATABASE . '_' . getmypid();

        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::$database . '`');
        $bootstrap->query('CREATE DATABASE `' . self::$database . '`');
        $bootstrap->select_db(self::$database);
        $bootstrap->query('CREATE TABLE samples (id INT PRIMARY KEY, attrs JSON NULL) ENGINE=InnoDB');
        $bootstrap->query('CREATE TABLE secrets (secret VARCHAR(32)) ENGINE=InnoDB');
        $bootstrap->query("INSERT INTO secrets VALUES ('hunter2')");
        $bootstrap->close();

        self::$db = new DatabaseService([
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => self::$database,
            'port' => $port,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db instanceof DatabaseService) {
            self::$db->rawQuery('DROP DATABASE IF EXISTS `' . self::$database . '`');
            self::$db = null;
        }
    }

    protected function setUp(): void
    {
        if (!self::$db instanceof DatabaseService) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        self::$db->rawQuery("SET SESSION sql_mode = ''");
        self::$db->rawQuery('DELETE FROM samples');
        self::$db->rawQuery("INSERT INTO samples (id, attrs) VALUES (1, NULL)");
    }

    /**
     * Runs the expression the way callers do, then reads the column back, keys
     * sorted: MySQL stores an object's keys in its own order.
     */
    private function apply(array $data, string $column = 'attrs'): array
    {
        $expression = JsonUtility::jsonToSetString(json_encode($data), $column);
        self::assertNotNull($expression);

        self::$db->where('id', 1);
        self::$db->update('samples', [$column => self::$db->func($expression)]);

        $row = self::$db->rawQueryOne('SELECT attrs FROM samples WHERE id = 1');
        $stored = json_decode((string) ($row['attrs'] ?? 'null'), true) ?? [];
        ksort($stored);
        return $stored;
    }

    public function testAKeyCannotInjectAnExpression(): void
    {
        // Closes the path literal, adds a subquery as a value, then opens a new path.
        $key = 'x", (SELECT secret FROM secrets), "$.y';

        $stored = $this->apply([$key => 'v']);

        self::assertSame([$key => 'v'], $stored);
        self::assertNotContains('hunter2', $stored);
    }

    public function testAKeyCannotEndTheStatement(): void
    {
        $key = "x', '1') WHERE 0; -- ";

        self::assertSame([$key => 'v'], $this->apply([$key => 'v']));
    }

    /** @return iterable<string, array{mixed}> */
    public static function awkwardValues(): iterable
    {
        yield 'double quote' => ['say "hi"'];
        yield 'backslash' => ['C:\\lab\\results'];
        yield 'trailing backslash' => ['ends with \\'];
        yield 'backslash before quote' => ['\\"'];
        yield 'single quote' => ["O'Brien"];
        yield 'backslash before single quote' => ["\\'"];
        yield 'newline and tab' => ["line1\nline2\tend"];
        yield 'unicode' => ['Kinshasa – Ngaliema ✓'];
        yield 'nested' => [['a' => ['b' => "q\"uo\\te"], 'list' => [1, 'two', null]]];
        yield 'number' => [42];
        yield 'bool' => [true];
        yield 'null' => [null];
        yield 'empty string' => [''];
    }

    #[DataProvider('awkwardValues')]
    public function testAValueRoundTrips(mixed $value): void
    {
        self::assertSame(['field' => $value], $this->apply(['field' => $value]));
    }

    #[DataProvider('awkwardValues')]
    public function testAValueRoundTripsWithNoBackslashEscapes(mixed $value): void
    {
        self::$db->rawQuery("SET SESSION sql_mode = 'NO_BACKSLASH_ESCAPES'");

        self::assertSame(['field' => $value], $this->apply(['field' => $value]));
    }

    /** @return iterable<string, array{string}> */
    public static function awkwardKeys(): iterable
    {
        yield 'dot' => ['a.b'];
        yield 'space' => ['first name'];
        yield 'double quote' => ['say "hi"'];
        yield 'backslash' => ['a\\b'];
        yield 'single quote' => ["it's"];
        yield 'dollar and brackets' => ['$[0]'];
        yield 'unicode' => ['nom_du_médecin'];
        yield 'numeric' => ['123'];
    }

    #[DataProvider('awkwardKeys')]
    public function testAKeyIsStoredLiterally(string $key): void
    {
        self::assertSame([$key => 'v'], $this->apply([$key => 'v']));
    }

    public function testKeysAlreadyStoredAreKept(): void
    {
        $this->apply(['deviceId' => 'd1', 'storage' => ['rack' => 'R1']]);

        self::assertSame(
            ['apiTransactionId' => 't1', 'deviceId' => 'd1', 'storage' => ['rack' => 'R1']],
            $this->apply(['apiTransactionId' => 't1'])
        );
    }

    public function testAColumnNameThatIsNotAnIdentifierIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JsonUtility::jsonToSetString(json_encode(['a' => 1]), 'attrs), id = (id');
    }
}
