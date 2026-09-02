<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\SystemException;
use App\Repositories\Reference\SampleTypeRepository;
use App\Services\DatabaseService;
use mysqli;
use PHPUnit\Framework\TestCase;

/**
 * The sample-type repository against a real server.
 *
 * This class replaced six cloned reference helpers, so what needs proving is
 * that its writes land exactly the way the helpers' did: an insert marks the
 * row for reference-data sync, an update touches only the addressed row in the
 * addressed module's table, and the status toggle only ever sees valid ids and
 * statuses. The tables here mirror the production schema (they are identical
 * across all six modules).
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run. Without them the suite
 * skips, so `composer test` stays green on a machine with no database.
 */
final class SampleTypeRepositoryTest extends TestCase
{
    private const DATABASE = 'intelis_sample_type_repo_test';

    /**
     * The full production mapping, so the table-isolation test fails if a
     * module is dropped from the repository or pointed at the wrong table.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        'vl' => 'r_vl_sample_type',
        'eid' => 'r_eid_sample_type',
        'cd4' => 'r_cd4_sample_types',
        'tb' => 'r_tb_sample_type',
        'covid19' => 'r_covid19_sample_type',
        'hepatitis' => 'r_hepatitis_sample_type',
    ];

    private static ?DatabaseService $db = null;

    private SampleTypeRepository $repository;

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
        foreach (self::TABLES as $table) {
            $bootstrap->query(
                "CREATE TABLE `$table` (
                    sample_id INT AUTO_INCREMENT PRIMARY KEY,
                    sample_name VARCHAR(255) DEFAULT NULL,
                    status VARCHAR(45) DEFAULT NULL,
                    updated_datetime DATETIME DEFAULT NULL,
                    data_sync INT NOT NULL DEFAULT 0
                ) ENGINE=InnoDB"
            );
        }
        $bootstrap->close();

        self::$db = new DatabaseService([
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => self::DATABASE,
            'port' => $port,
        ]);
    }

    protected function setUp(): void
    {
        if (!self::$db instanceof DatabaseService) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        foreach (self::TABLES as $table) {
            self::$db->rawQuery("DELETE FROM `$table`");
        }
        $this->repository = new SampleTypeRepository(self::$db);
    }

    /** @return array<string, mixed>|null */
    private function row(string $table, int $id): ?array
    {
        return self::$db->rawQueryOne("SELECT * FROM `$table` WHERE sample_id = ?", [$id]) ?: null;
    }

    public function testInsertTrimsTheNameAndMarksTheRowForSync(): void
    {
        $id = $this->repository->save('vl', '  Plasma  ', 'active');

        $row = $this->row('r_vl_sample_type', $id);
        $this->assertNotNull($row);
        $this->assertSame('Plasma', $row['sample_name']);
        $this->assertSame('active', $row['status']);
        $this->assertSame(0, (int) $row['data_sync']);
        $this->assertNotEmpty($row['updated_datetime']);
    }

    public function testUpdateChangesOnlyTheAddressedRowAndKeepsItsSyncState(): void
    {
        $id = $this->repository->save('vl', 'Plasma', 'active');
        $other = $this->repository->save('vl', 'Serum', 'active');
        // A row the sync has already sent; an edit must not silently re-flag or unflag it.
        self::$db->rawQuery('UPDATE r_vl_sample_type SET data_sync = 1 WHERE sample_id = ?', [$id]);

        $returned = $this->repository->save('vl', 'Whole Blood', 'inactive', $id);

        $this->assertSame($id, $returned);
        $row = $this->row('r_vl_sample_type', $id);
        $this->assertSame('Whole Blood', $row['sample_name']);
        $this->assertSame('inactive', $row['status']);
        $this->assertSame(1, (int) $row['data_sync']);
        $this->assertSame('Serum', $this->row('r_vl_sample_type', $other)['sample_name']);
    }

    public function testEachModuleWritesItsOwnTableAndOnlyItsOwnTable(): void
    {
        foreach (array_keys(self::TABLES) as $testType) {
            $this->repository->save($testType, "Specimen for $testType", 'active');
        }

        foreach (self::TABLES as $testType => $table) {
            $rows = self::$db->rawQuery("SELECT sample_name FROM `$table`");
            $this->assertCount(1, $rows, "$table must hold exactly its own module's row");
            $this->assertSame("Specimen for $testType", $rows[0]['sample_name']);
        }
    }

    public function testUpdateStatusTouchesOnlyValidIds(): void
    {
        $first = $this->repository->save('vl', 'Plasma', 'active');
        $second = $this->repository->save('vl', 'Serum', 'active');

        // The endpoint hands over an exploded CSV verbatim, junk and all.
        $changed = $this->repository->updateStatus('vl', [(string) $first, 'abc', '0', ''], 'inactive');

        $this->assertSame(1, $changed);
        $this->assertSame('inactive', $this->row('r_vl_sample_type', $first)['status']);
        $this->assertSame('active', $this->row('r_vl_sample_type', $second)['status']);
    }

    public function testUpdateStatusWithNoUsableIdsWritesNothing(): void
    {
        $id = $this->repository->save('vl', 'Plasma', 'active');

        $this->assertSame(0, $this->repository->updateStatus('vl', ['', 'abc', '-3'], 'inactive'));
        $this->assertSame('active', $this->row('r_vl_sample_type', $id)['status']);
    }

    public function testUnknownTestTypeIsRefused(): void
    {
        $this->expectException(SystemException::class);
        $this->repository->save('recency', 'Plasma', 'active');
    }

    public function testInvalidStatusIsRefused(): void
    {
        $this->expectException(SystemException::class);
        $this->repository->updateStatus('vl', ['1'], 'deleted');
    }

    public function testAMangledEditIdIsRefusedRatherThanInsertingADuplicate(): void
    {
        $this->repository->save('vl', 'Plasma', 'active');

        // What a helper produces from an invalid base64 sampleId: (int) false = 0.
        try {
            $this->repository->save('vl', 'Plasma', 'active', 0);
            $this->fail('A non-positive edit id must be refused');
        } catch (SystemException) {
        }
        $this->assertSame(1, (int) self::$db->rawQueryOne('SELECT COUNT(*) AS c FROM r_vl_sample_type')['c']);
    }

    public function testBlankNameIsRefused(): void
    {
        $this->expectException(SystemException::class);
        $this->repository->save('vl', '   ', 'active');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db instanceof DatabaseService) {
            self::$db->rawQuery('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
            self::$db = null;
        }
    }
}
