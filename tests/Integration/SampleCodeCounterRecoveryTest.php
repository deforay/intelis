<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Abstracts\AbstractTestService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Utilities\FileCacheUtility;
use mysqli;
use PHPUnit\Framework\TestCase;

/**
 * Sample-code generation when the counter is behind the form table, against a
 * real server.
 *
 * A sequence counter can fall behind the codes its form table already holds --
 * a database restored from a dump, or rows synced in carrying codes minted
 * elsewhere in the same series. The generator then claims a number whose code
 * is already on a sample, and because a failed attempt rolls the claim back,
 * the next try re-claims the SAME number. Before the walk-forward recovery in
 * AbstractTestService this wedged the series permanently: in the field a
 * manifest of 89 samples got 5 codes (the numbers still free) and the other 84
 * failed on the identical collision however often activation was re-run.
 *
 * What is under test is the interplay of the counter claim, the duplicate
 * check and the surrounding transaction, so it runs against MySQL rather than
 * a double.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SampleCodeCounterRecoveryTest extends TestCase
{
    private const DATABASE = 'intelis_sample_code_recovery_test';

    /** Mirrors the claim year for a 2026 collection date. */
    private const YEAR = 2026;

    private const COLLECTION_DATE = '2026-08-19 10:00:00';

    /** MMYY format for the collection date above, VL prefix. */
    private const CODE_BASE = 'VL0826';

    private static ?DatabaseService $db = null;

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
        // The columns generateSampleCode() and its recovery path actually touch,
        // with the same key indexes the real form tables carry.
        $bootstrap->query(
            'CREATE TABLE form_vl (
                vl_sample_id INT AUTO_INCREMENT PRIMARY KEY,
                unique_id VARCHAR(64) NULL,
                sample_code VARCHAR(64) NULL,
                sample_code_format VARCHAR(64) NULL,
                sample_code_key INT NULL,
                remote_sample_code VARCHAR(64) NULL,
                remote_sample_code_format VARCHAR(64) NULL,
                remote_sample_code_key INT NULL,
                sample_collection_date DATETIME NULL,
                KEY sample_code_key (sample_code_key),
                KEY remote_sample_code_key (remote_sample_code_key)
            ) ENGINE=InnoDB'
        );
        $bootstrap->query(
            'CREATE TABLE sequence_counter (
                test_type VARCHAR(32) NOT NULL,
                year INT NOT NULL,
                code_type VARCHAR(32) NOT NULL,
                max_sequence_number INT DEFAULT NULL,
                PRIMARY KEY (test_type, year, code_type)
            ) ENGINE=InnoDB'
        );
        // CommonService is final, so the real one answers the generator's two
        // questions (instance type, vl_form) from these tables.
        $bootstrap->query(
            'CREATE TABLE system_config (name VARCHAR(64) PRIMARY KEY, value VARCHAR(64) NULL) ENGINE=InnoDB'
        );
        $bootstrap->query("INSERT INTO system_config VALUES ('sc_user_type', 'vluser')");
        $bootstrap->query(
            'CREATE TABLE global_config (name VARCHAR(64) PRIMARY KEY, value VARCHAR(64) NULL) ENGINE=InnoDB'
        );
        $bootstrap->close();

        self::$db = new DatabaseService([
            'host' => $host,
            'username' => $user,
            'password' => $password,
            'db' => self::DATABASE,
            'port' => $port,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        self::$db?->rawQuery('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        self::$db = null;
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run the counter recovery tests.');
        }

        self::$db->rawQuery('DELETE FROM form_vl');
        self::$db->rawQuery('DELETE FROM sequence_counter');
    }

    /** A concrete test service on a LIS instance, so codes go into sample_code. */
    private function service(): AbstractTestService
    {
        // Compute every time, touch no filesystem -- the same pass-through the
        // unit suite uses (see Tests\Support\VlServiceFactory).
        $passThroughCache = new class extends FileCacheUtility {
            public function __construct()
            {
            }

            public function get(
                string $key,
                callable $computeValueCallback,
                ?array $tags = [],
                int $expiration = 3600
            ): mixed {
                return $computeValueCallback();
            }
        };

        $commonService = new CommonService(self::$db, new FacilitiesService(self::$db), $passThroughCache);

        return new class (self::$db, $commonService) extends AbstractTestService {
            public string $testType = 'vl';

            public function getSampleCode($params)
            {
                return $this->generateSampleCode($this->table, $params);
            }

            public function insertSample($params, $returnSampleData = false)
            {
                return null;
            }
        };
    }

    /** The full code the generator emits for $key: base + the %04d-padded number. */
    private static function code(int $key): string
    {
        return self::CODE_BASE . sprintf('%04d', $key);
    }

    /** A sample already carrying the code for $key, as a restore or sync leaves it. */
    private function seedUsedCode(int $key): void
    {
        self::$db->insert('form_vl', [
            'unique_id' => 'seeded-' . $key,
            'sample_code' => self::code($key),
            'sample_code_format' => self::CODE_BASE,
            'sample_code_key' => $key,
            'sample_collection_date' => self::COLLECTION_DATE,
        ]);
    }

    private function seedCounter(int $value): void
    {
        self::$db->insert('sequence_counter', [
            'test_type' => 'vl',
            'year' => self::YEAR,
            'code_type' => 'sample_code',
            'max_sequence_number' => $value,
        ]);
    }

    private function counterValue(): int
    {
        $row = self::$db->rawQueryOne(
            "SELECT max_sequence_number FROM sequence_counter
              WHERE test_type = 'vl' AND year = ? AND code_type = 'sample_code'",
            [self::YEAR]
        );
        return (int) ($row['max_sequence_number'] ?? -1);
    }

    /**
     * One generation call, driven the way processSampleCodeQueue drives it: the
     * caller owns the transaction, writes the code onto a sample, and commits
     * only then. Returns the decoded generator result.
     *
     * @return array<string, mixed>
     */
    private function mintOntoSample(string $uniqueId): array
    {
        self::$db->insert('form_vl', [
            'unique_id' => $uniqueId,
            'sample_collection_date' => self::COLLECTION_DATE,
        ]);

        self::$db->beginTransaction();
        try {
            $decoded = json_decode((string) $this->service()->getSampleCode([
                'sampleCollectionDate' => self::COLLECTION_DATE,
                'testType' => 'vl',
                'sampleCodeFormat' => 'MMYY',
                'prefix' => 'VL',
                'postfix' => '',
                'insertOperation' => true,
                'manageTransaction' => false,
            ]), true);

            self::$db->reset();
            self::$db->where('unique_id', $uniqueId);
            self::$db->update('form_vl', [
                'sample_code' => $decoded['sampleCode'],
                'sample_code_format' => $decoded['sampleCodeFormat'],
                'sample_code_key' => $decoded['sampleCodeKey'],
            ]);
            self::$db->commitTransaction();

            return $decoded;
        } catch (\Throwable $e) {
            self::$db->rollbackTransaction();
            throw $e;
        }
    }

    public function testWalksPastASingleForeignCodeInsteadOfWedging(): void
    {
        // The counter says the next number is 101, but a synced-in row already
        // carries it. 102 is free and must be what comes out -- every retry used
        // to re-claim 101 forever.
        $this->seedCounter(100);
        $this->seedUsedCode(101);

        $decoded = $this->mintOntoSample('local-1');

        $this->assertSame(self::code(102), $decoded['sampleCode']);
        $this->assertSame(102, $this->counterValue());
    }

    public function testStepsIntoGapsSoNoFreeNumberIsSkipped(): void
    {
        // Used: 101 and 103. Free: 102 and 104. Both free numbers must be
        // minted -- jumping straight past 103 would leave a hole in the series.
        $this->seedCounter(100);
        $this->seedUsedCode(101);
        $this->seedUsedCode(103);

        $first = $this->mintOntoSample('local-1');
        $second = $this->mintOntoSample('local-2');

        $this->assertSame(self::code(102), $first['sampleCode']);
        $this->assertSame(self::code(104), $second['sampleCode']);
    }

    public function testJumpsAContiguousUsedBlockInOneCall(): void
    {
        // A restored database: the counter is behind a large contiguous block of
        // used numbers -- far more than the generator's per-call walk budget, so
        // one-at-a-time stepping could not recover. The run jump must clear the
        // whole block in a single call.
        $this->seedCounter(100);
        for ($key = 101; $key <= 300; $key++) {
            $this->seedUsedCode($key);
        }

        $decoded = $this->mintOntoSample('local-1');

        $this->assertSame(self::code(301), $decoded['sampleCode']);
        $this->assertSame(301, $this->counterValue());
    }

    public function testManifestSizedBatchAllGetDistinctCodes(): void
    {
        // The field failure: a manifest batch hits the collision part-way and
        // every sample after it used to fail. All of them must come out with
        // distinct, consecutive-where-free codes.
        $this->seedCounter(100);
        $this->seedUsedCode(103);
        $this->seedUsedCode(104);

        $codes = [];
        for ($i = 1; $i <= 10; $i++) {
            $codes[] = $this->mintOntoSample("batch-{$i}")['sampleCode'];
        }

        $expected = array_map(
            fn (int $key): string => self::code($key),
            [101, 102, 105, 106, 107, 108, 109, 110, 111, 112]
        );
        $this->assertSame($expected, $codes);
    }

    public function testOwnTransactionPathRecoversToo(): void
    {
        // The default mode (the generator manages its own transaction) had the
        // same wedge: the rollback before each retry returned the same number.
        $this->seedCounter(100);
        $this->seedUsedCode(101);

        $decoded = json_decode((string) $this->service()->getSampleCode([
            'sampleCollectionDate' => self::COLLECTION_DATE,
            'testType' => 'vl',
            'sampleCodeFormat' => 'MMYY',
            'prefix' => 'VL',
            'postfix' => '',
            'insertOperation' => true,
        ]), true);

        $this->assertSame(self::code(102), $decoded['sampleCode']);
        $this->assertSame(102, $this->counterValue());
    }
}
