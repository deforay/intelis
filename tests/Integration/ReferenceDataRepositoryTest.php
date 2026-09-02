<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\SystemException;
use App\Repositories\Reference\ReferenceDataRepository;
use App\Services\DatabaseService;
use mysqli;
use PHPUnit\Framework\TestCase;

/**
 * The reference-data repository against a real server.
 *
 * This class replaced the cloned reference helpers, so what needs proving is
 * that its writes land exactly the way the helpers' did, for every entity and
 * every module mapping: an insert marks the row for reference-data sync, an
 * update touches only the addressed row in the addressed module's table, and
 * the status toggle only ever sees valid ids and statuses. The tables here
 * mirror the production schema, which is identical across the six modules of
 * each entity.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run. Without them the suite
 * skips, so `composer test` stays green on a machine with no database.
 */
final class ReferenceDataRepositoryTest extends TestCase
{
    private const DATABASE = 'intelis_reference_data_repo_test';

    /**
     * The full production mapping per entity, so the table-isolation test
     * fails if a module is dropped from the repository or pointed at the
     * wrong table.
     *
     * @var array<string, array{tables: array<string, string>, id: string, name: string, status: string}>
     */
    private const ENTITIES = [
        'sample-type' => [
            'tables' => [
                'vl' => 'r_vl_sample_type',
                'eid' => 'r_eid_sample_type',
                'cd4' => 'r_cd4_sample_types',
                'tb' => 'r_tb_sample_type',
                'covid19' => 'r_covid19_sample_type',
                'hepatitis' => 'r_hepatitis_sample_type',
            ],
            'id' => 'sample_id',
            'name' => 'sample_name',
            'status' => 'status',
        ],
        'rejection-reason' => [
            'tables' => [
                'vl' => 'r_vl_sample_rejection_reasons',
                'eid' => 'r_eid_sample_rejection_reasons',
                'cd4' => 'r_cd4_sample_rejection_reasons',
                'tb' => 'r_tb_sample_rejection_reasons',
                'covid19' => 'r_covid19_sample_rejection_reasons',
                'hepatitis' => 'r_hepatitis_sample_rejection_reasons',
            ],
            'id' => 'rejection_reason_id',
            'name' => 'rejection_reason_name',
            'status' => 'rejection_reason_status',
        ],
        'test-reason' => [
            'tables' => [
                'vl' => 'r_vl_test_reasons',
                'eid' => 'r_eid_test_reasons',
                'cd4' => 'r_cd4_test_reasons',
                'tb' => 'r_tb_test_reasons',
                'covid19' => 'r_covid19_test_reasons',
                'hepatitis' => 'r_hepatitis_test_reasons',
            ],
            'id' => 'test_reason_id',
            'name' => 'test_reason_name',
            'status' => 'test_reason_status',
        ],
        'test-failure-reason' => [
            'tables' => ['vl' => 'r_vl_test_failure_reasons'],
            'id' => 'failure_id',
            'name' => 'failure_reason',
            'status' => 'status',
        ],
        'vl-result' => [
            'tables' => ['vl' => 'r_vl_results'],
            'id' => 'result_id',
            'name' => 'result',
            'status' => 'status',
        ],
        'art-code' => [
            'tables' => ['vl' => 'r_vl_art_regimen'],
            'id' => 'art_id',
            'name' => 'art_code',
            'status' => 'art_status',
        ],
    ];

    /**
     * Production drift the repository must respect: these test-reason tables
     * have no data_sync column, so an insert must not write one there.
     *
     * @var list<string>
     */
    private const TEST_REASON_TABLES_WITHOUT_SYNC = [
        'r_tb_test_reasons',
        'r_covid19_test_reasons',
        'r_hepatitis_test_reasons',
    ];

    private static ?DatabaseService $db = null;

    private ReferenceDataRepository $repository;

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
        foreach (self::ENTITIES['sample-type']['tables'] as $table) {
            $bootstrap->query(
                "CREATE TABLE `$table` (
                    sample_id INT AUTO_INCREMENT PRIMARY KEY,
                    sample_name VARCHAR(255) DEFAULT NULL,
                    status VARCHAR(45) DEFAULT NULL,
                    updated_datetime DATETIME DEFAULT NULL,
                    data_sync INT NOT NULL DEFAULT 1
                ) ENGINE=InnoDB"
            );
        }
        foreach (self::ENTITIES['rejection-reason']['tables'] as $table) {
            $bootstrap->query(
                "CREATE TABLE `$table` (
                    rejection_reason_id INT AUTO_INCREMENT PRIMARY KEY,
                    rejection_reason_name VARCHAR(255) DEFAULT NULL,
                    rejection_type VARCHAR(255) DEFAULT NULL,
                    rejection_reason_status VARCHAR(255) DEFAULT NULL,
                    rejection_reason_code VARCHAR(255) DEFAULT NULL,
                    updated_datetime DATETIME DEFAULT NULL,
                    data_sync INT NOT NULL DEFAULT 1,
                    contributed_by_lab_id INT DEFAULT NULL
                ) ENGINE=InnoDB"
            );
        }
        $bootstrap->query(
            'CREATE TABLE r_vl_test_failure_reasons (
                failure_id INT AUTO_INCREMENT PRIMARY KEY,
                failure_reason VARCHAR(255) DEFAULT NULL,
                status VARCHAR(45) DEFAULT NULL,
                updated_datetime DATETIME DEFAULT NULL,
                data_sync INT NOT NULL DEFAULT 1
            ) ENGINE=InnoDB'
        );
        $bootstrap->query(
            'CREATE TABLE r_vl_results (
                result_id INT AUTO_INCREMENT PRIMARY KEY,
                result VARCHAR(255) DEFAULT NULL,
                status VARCHAR(45) DEFAULT NULL,
                available_for_instruments TEXT DEFAULT NULL,
                interpretation VARCHAR(255) DEFAULT NULL,
                updated_datetime DATETIME DEFAULT NULL,
                data_sync INT NOT NULL DEFAULT 1
            ) ENGINE=InnoDB'
        );
        $bootstrap->query(
            'CREATE TABLE r_vl_art_regimen (
                art_id INT AUTO_INCREMENT PRIMARY KEY,
                art_code VARCHAR(255) DEFAULT NULL,
                parent_art INT DEFAULT NULL,
                headings VARCHAR(255) DEFAULT NULL,
                art_status VARCHAR(45) DEFAULT NULL,
                art_source VARCHAR(45) DEFAULT NULL,
                updated_datetime DATETIME DEFAULT NULL,
                data_sync INT NOT NULL DEFAULT 1
            ) ENGINE=InnoDB'
        );
        foreach (self::ENTITIES['test-reason']['tables'] as $table) {
            $syncColumn = in_array($table, self::TEST_REASON_TABLES_WITHOUT_SYNC, true)
                ? ''
                : ', data_sync INT NOT NULL DEFAULT 1';
            $bootstrap->query(
                "CREATE TABLE `$table` (
                    test_reason_id INT AUTO_INCREMENT PRIMARY KEY,
                    test_reason_name VARCHAR(255) DEFAULT NULL,
                    parent_reason INT DEFAULT NULL,
                    test_reason_status VARCHAR(255) DEFAULT NULL,
                    updated_datetime DATETIME DEFAULT NULL$syncColumn
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

        foreach (self::ENTITIES as $entity) {
            foreach ($entity['tables'] as $table) {
                self::$db->rawQuery("DELETE FROM `$table`");
            }
        }
        $this->repository = new ReferenceDataRepository(self::$db);
    }

    /** @return array<string, mixed>|null */
    private function row(string $entity, string $testType, int $id): ?array
    {
        $spec = self::ENTITIES[$entity];
        $table = $spec['tables'][$testType];
        return self::$db->rawQueryOne("SELECT * FROM `$table` WHERE {$spec['id']} = ?", [$id]) ?: null;
    }

    public function testAnInsertSucceedsWhereTheTableCarriesNoSyncColumn(): void
    {
        // The hepatitis, covid-19, and tb test-reason tables were created
        // without data_sync; writing it there is an SQL error, so the entity
        // declares which modules are sync-tracked and the insert must respect
        // that on both sides.
        foreach (['tb', 'covid19', 'hepatitis'] as $testType) {
            $id = $this->repository->save('test-reason', $testType, 'Baseline', 'active');
            $this->assertNotNull($this->row('test-reason', $testType, $id), $testType);
        }
        foreach (['vl', 'eid', 'cd4'] as $testType) {
            $tracked = $this->repository->save('test-reason', $testType, 'Baseline', 'active');
            $this->assertSame(0, (int) $this->row('test-reason', $testType, $tracked)['data_sync'], $testType);
        }
    }

    public function testANullFieldIsStoredAsSqlNull(): void
    {
        // NULL instruments means the result is available everywhere; '' would
        // read as a restriction to no instrument at all.
        $id = $this->repository->save('vl-result', 'vl', '< 20', 'active', [
            'interpretation' => 'Suppressed',
            'available_for_instruments' => null,
        ]);

        $row = $this->row('vl-result', 'vl', $id);
        $this->assertSame('< 20', $row['result']);
        $this->assertNull($row['available_for_instruments']);
        $this->assertSame('Suppressed', $row['interpretation']);
        $this->assertSame(0, (int) $row['data_sync']);
    }

    public function testAnEntitySpecificFieldEmptyFallsBackToItsDefault(): void
    {
        $id = $this->repository->save('test-reason', 'vl', 'Routine', 'active', ['parent_reason' => '']);
        // Uncast on purpose: the VL parent selector matches parent_reason = '0',
        // so the default must actually be written, not left NULL for a cast to hide.
        $this->assertSame('0', (string) $this->row('test-reason', 'vl', $id)['parent_reason']);
    }

    public function testInsertTrimsTheNameAndMarksTheRowForSync(): void
    {
        foreach (['sample-type', 'rejection-reason'] as $entity) {
            $id = $this->repository->save($entity, 'vl', '  Plasma  ', 'active');

            $row = $this->row($entity, 'vl', $id);
            $spec = self::ENTITIES[$entity];
            $this->assertNotNull($row, $entity);
            $this->assertSame('Plasma', $row[$spec['name']], $entity);
            $this->assertSame('active', $row[$spec['status']], $entity);
            $this->assertSame(0, (int) $row['data_sync'], $entity);
            $this->assertNotEmpty($row['updated_datetime'], $entity);
        }
    }

    public function testUpdateChangesOnlyTheAddressedRowAndKeepsItsSyncState(): void
    {
        $id = $this->repository->save('sample-type', 'vl', 'Plasma', 'active');
        $other = $this->repository->save('sample-type', 'vl', 'Serum', 'active');
        // A row the sync has already sent; an edit must not silently re-flag or unflag it.
        self::$db->rawQuery('UPDATE r_vl_sample_type SET data_sync = 1 WHERE sample_id = ?', [$id]);

        $returned = $this->repository->save('sample-type', 'vl', 'Whole Blood', 'inactive', [], $id);

        $this->assertSame($id, $returned);
        $row = $this->row('sample-type', 'vl', $id);
        $this->assertSame('Whole Blood', $row['sample_name']);
        $this->assertSame('inactive', $row['status']);
        $this->assertSame(1, (int) $row['data_sync']);
        $this->assertSame('Serum', $this->row('sample-type', 'vl', $other)['sample_name']);
    }

    public function testEachModuleWritesItsOwnTableAndOnlyItsOwnTable(): void
    {
        foreach (self::ENTITIES as $entity => $spec) {
            foreach (array_keys($spec['tables']) as $testType) {
                $this->repository->save($entity, $testType, "Specimen for $testType", 'active');
            }

            foreach ($spec['tables'] as $testType => $table) {
                $rows = self::$db->rawQuery("SELECT {$spec['name']} AS name FROM `$table`");
                $this->assertCount(1, $rows, "$table must hold exactly its own module's row");
                $this->assertSame("Specimen for $testType", $rows[0]['name']);
            }
        }
    }

    public function testEntitySpecificFieldsAreStoredAndEmptyOnesTakeTheDeclaredDefault(): void
    {
        $id = $this->repository->save('rejection-reason', 'vl', 'Broken & Leaking', 'active', [
            'rejection_type' => '  ',
            'rejection_reason_code' => ' VL-R-01 ',
        ]);

        $row = $this->row('rejection-reason', 'vl', $id);
        $this->assertSame('Broken & Leaking', $row['rejection_reason_name']);
        $this->assertSame('general', $row['rejection_type']);
        $this->assertSame('VL-R-01', $row['rejection_reason_code']);
    }

    public function testAFieldTheEntityDoesNotDeclareIsRefused(): void
    {
        $this->expectException(SystemException::class);
        $this->repository->save('sample-type', 'vl', 'Plasma', 'active', ['rejection_type' => 'general']);
    }

    public function testUpdateStatusTouchesOnlyValidIds(): void
    {
        $first = $this->repository->save('rejection-reason', 'vl', 'Hemolysed', 'active');
        $second = $this->repository->save('rejection-reason', 'vl', 'Clotted', 'active');

        // The endpoint hands over an exploded CSV verbatim, junk and all.
        // '12abc'-style values must not be truncated into some other row's id.
        $junkAndOne = [(string) $first, 'abc', '0', '', "{$second}abc", "$second.5"];
        $changed = $this->repository->updateStatus('rejection-reason', 'vl', $junkAndOne, 'inactive');

        $this->assertSame(1, $changed);
        $this->assertSame('inactive', $this->row('rejection-reason', 'vl', $first)['rejection_reason_status']);
        $this->assertSame('active', $this->row('rejection-reason', 'vl', $second)['rejection_reason_status']);
    }

    public function testUpdateStatusWithNoUsableIdsWritesNothing(): void
    {
        $id = $this->repository->save('sample-type', 'vl', 'Plasma', 'active');

        $this->assertSame(0, $this->repository->updateStatus('sample-type', 'vl', ['', 'abc', '-3'], 'inactive'));
        $this->assertSame('active', $this->row('sample-type', 'vl', $id)['status']);
    }

    public function testAMangledEditIdIsRefusedRatherThanInsertingADuplicate(): void
    {
        $this->repository->save('sample-type', 'vl', 'Plasma', 'active');

        // What a helper produces from an invalid base64 id: (int) false = 0.
        try {
            $this->repository->save('sample-type', 'vl', 'Plasma', 'active', [], 0);
            $this->fail('A non-positive edit id must be refused');
        } catch (SystemException) {
        }
        $this->assertSame(1, (int) self::$db->rawQueryOne('SELECT COUNT(*) AS c FROM r_vl_sample_type')['c']);
    }

    public function testUnknownEntityAndUnknownTestTypeAreRefused(): void
    {
        try {
            $this->repository->save('funding-source', 'vl', 'X', 'active');
            $this->fail('An undeclared entity must be refused');
        } catch (SystemException) {
        }

        $this->expectException(SystemException::class);
        $this->repository->save('sample-type', 'recency', 'Plasma', 'active');
    }

    public function testInvalidStatusIsRefused(): void
    {
        $this->expectException(SystemException::class);
        $this->repository->updateStatus('sample-type', 'vl', ['1'], 'deleted');
    }

    public function testBlankNameIsRefused(): void
    {
        $this->expectException(SystemException::class);
        $this->repository->save('rejection-reason', 'vl', '   ', 'active');
    }

    public function testMarkupIsRefusedWhilePlainTextSurvivesRaw(): void
    {
        try {
            $this->repository->save('sample-type', 'vl', '<img src=x onerror=alert(1)>', 'active');
            $this->fail('A tag in the name must be refused');
        } catch (SystemException) {
        }
        try {
            $this->repository->save('rejection-reason', 'vl', 'Hemolysed', 'active', [
                'rejection_type' => '<script>alert(1)</script>',
            ]);
            $this->fail('A tag in an entity-specific field must be refused');
        } catch (SystemException) {
        }
        try {
            $this->repository->save('rejection-reason', 'vl', 'Hemolysed', 'active', [
                'rejection_reason_code' => '</td><img src=x onerror=alert(1)>',
            ]);
            $this->fail('A tag in the reason code must be refused');
        } catch (SystemException) {
        }
        foreach (self::ENTITIES as $spec) {
            foreach ($spec['tables'] as $table) {
                $this->assertSame(
                    0,
                    (int) self::$db->rawQueryOne("SELECT COUNT(*) AS c FROM `$table`")['c'],
                    'Nothing may be stored when a value is refused'
                );
            }
        }

        // Ampersands and comparisons are legitimate vocabulary and stay byte-identical.
        $id = $this->repository->save('sample-type', 'vl', 'Broken & Leaking', 'active');
        $this->assertSame('Broken & Leaking', $this->row('sample-type', 'vl', $id)['sample_name']);
        $volume = $this->repository->save('rejection-reason', 'vl', 'Insufficient volume < 1 mL', 'active');
        $this->assertSame(
            'Insufficient volume < 1 mL',
            $this->row('rejection-reason', 'vl', $volume)['rejection_reason_name']
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db instanceof DatabaseService) {
            self::$db->rawQuery('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
            self::$db = null;
        }
    }
}
