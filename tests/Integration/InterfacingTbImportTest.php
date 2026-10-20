<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\InterfacingService;
use App\Services\TbPoolService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Tests\Support\LegacyAppHarness;

use const COUNTRY\BURKINA_FASO;
use const COUNTRY\RWANDA;

/**
 * GeneXpert MTB/RIF Ultra results reaching TB samples, pooled or not.
 *
 * On the per-test form (one tb_tests row per test) an import only ever adds a row,
 * and recognises its own rows by their content, which is what survives a save of
 * the result page. The single-result forms keep the Xpert result on form_tb.
 * Neither ever writes the final interpretation or the sample's status.
 *
 * A pool is one run whose sample ID is a pool code from a TB batch, or lists its
 * members. A negative pool is applied whole or not at all, and only where the lab has
 * turned that on. Any other pool sends its members back to be tested on their own.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class InterfacingTbImportTest extends TestCase
{
    private const DATABASE = 'intelis_interfacing_tb_test';

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    protected function setUp(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            'r_sample_status', 'form_vl', 'form_tb', 'tb_tests', 'r_tb_results',
            'roles', 'user_details', 'instruments', 'instrument_machines', 'test_result_attempts',
            'batch_details',
        ]);

        // The Xpert results exactly as an install is seeded with them.
        $initSql = (string) file_get_contents(dirname(__DIR__, 2) . '/sql/init.sql');
        preg_match('/^INSERT INTO `r_tb_results` VALUES .*?;$/m', $initSql, $seed);
        LegacyAppHarness::db()->rawQuery($seed[0]);
        // And as migration 5.7.79 leaves them.
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . '/sys/migrations/5.7.79.sql');
        preg_match('/^UPDATE `r_tb_results`.*?;$/ms', $migration, $fix);
        LegacyAppHarness::db()->rawQuery($fix[0]);
        // The pool columns from migration 5.7.90.
        $pooling = (string) file_get_contents(dirname(__DIR__, 2) . '/sys/migrations/5.7.90.sql');
        preg_match_all('/^ALTER TABLE `form_tb` .*?;$/m', $pooling, $alters);
        foreach ($alters[0] as $alter) {
            LegacyAppHarness::db()->rawQuery($alter);
        }
        // The role an analyzer's tester is created with.
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO roles (role_id, role_name, status) VALUES (4, 'Lab Technician', 'active')"
        );
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Received at lab')"
        );
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $columns */
    private function seedTb(array $columns): int
    {
        static $key = 0;
        $key++;
        $row = $columns + [
            'vlsm_instance_id' => 'test',
            'sample_code' => "TB-$key",
            'sample_code_key' => $key,
            'sample_collection_date' => '2026-09-15 09:00:00',
            'sample_received_at_lab_datetime' => '2026-09-16 10:00:00',
            'facility_id' => 1,
            'lab_id' => 1,
            'result_status' => 6,
        ];
        LegacyAppHarness::db()->insert('form_tb', $row);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function service(int $form, bool $releaseNegativePools = false): InterfacingService
    {
        /** @var InterfacingService $service */
        $service = ContainerRegistry::get(InterfacingService::class);
        $reflection = new ReflectionClass(InterfacingService::class);
        $reflection->getProperty('activeModules')->setValue(
            $service,
            ['vl_sample_id' => 'form_vl', 'tb_id' => 'form_tb']
        );
        $reflection->getProperty('formId')->setValue($service, $form);
        $reflection->getProperty('releaseNegativePools')->setValue($service, $releaseNegativePools);

        return $service;
    }

    /** @return array<string, mixed> */
    private function ultra(
        string $sampleId,
        string $result,
        string $notes = '',
        string $testedAt = '2026-09-18 19:34:31'
    ): array {
        return [
            'id' => 1,
            'order_id' => $sampleId,
            'test_id' => $sampleId,
            'test_type' => 'UV2',
            'test_unit' => '',
            'results' => $result,
            'notes' => $notes,
            'tested_by' => 'Example Operator',
            'machine_used' => 'GeneXpert',
            'analysed_date_time' => $testedAt,
            'result_accepted_date_time' => $testedAt,
            'authorised_date_time' => null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function tests(int $tbId): array
    {
        return LegacyAppHarness::db()->rawQuery('SELECT * FROM tb_tests WHERE tb_id = ? ORDER BY tb_test_id', [$tbId]);
    }

    /** @return array<string, mixed> */
    private function sample(int $tbId): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM form_tb WHERE tb_id = ?', [$tbId]);
    }

    // -----------------------------------------------------------------
    // The per-test form
    // -----------------------------------------------------------------

    #[RunInSeparateProcess]
    public function testAResultIsAddedAsATestAndNothingIsInterpreted(): void
    {
        $tbId = $this->seedTb(['lab_assigned_code' => '0733', 'specimen_type' => '2']);

        $outcome = $this->service(RWANDA)->importResult(
            $this->ultra('0733', 'DETECTED LOW', 'RIF Resistance NOT DETECTED'),
            1
        );

        self::assertSame('updated', $outcome['reason']);
        $tests = $this->tests($tbId);
        self::assertCount(1, $tests);
        self::assertSame('MTB/ RIF Ultra', $tests[0]['test_type']);
        self::assertSame('MTB Detected Low/RIF not detected', $tests[0]['test_result']);
        self::assertSame('2026-09-18 19:34:31', $tests[0]['sample_tested_datetime']);
        self::assertSame('2', $tests[0]['specimen_type']);

        $sample = $this->sample($tbId);
        self::assertNull($sample['result'], 'the final interpretation is the clinician\'s');
        self::assertSame('no', $sample['is_result_finalized']);
        self::assertSame('6', (string) $sample['result_status'], 'the status is left where the lab put it');
        self::assertSame('2026-09-18 19:34:31', $sample['sample_tested_datetime']);
    }

    #[RunInSeparateProcess]
    public function testTheSameRunIsNotAddedTwiceEvenAfterTheResultPageRecreatedTheRows(): void
    {
        $tbId = $this->seedTb(['lab_assigned_code' => '0733']);
        $service = $this->service(RWANDA);
        $row = $this->ultra('0733', 'NOT DETECTED');
        $service->importResult($row, 1);

        // What saving the result page does: delete every row and insert what it
        // posted, with the time back from the form without its seconds, and here
        // with the lab's own correction to the result.
        $test = $this->tests($tbId)[0];
        LegacyAppHarness::db()->rawQuery('DELETE FROM tb_tests WHERE tb_id = ?', [$tbId]);
        LegacyAppHarness::db()->insert('tb_tests', [
            'tb_id' => $tbId,
            'lab_id' => 1,
            'test_type' => $test['test_type'],
            'test_result' => 'MTB Detected Low/RIF not detected',
            'sample_tested_datetime' => '2026-09-18 19:34:00',
        ]);

        self::assertSame('already_up_to_date', $service->importResult($row, 1)['reason']);
        $tests = $this->tests($tbId);
        self::assertCount(1, $tests);
        self::assertSame('MTB Detected Low/RIF not detected', $tests[0]['test_result'], 'the lab\'s edit stands');
    }

    #[RunInSeparateProcess]
    public function testARetestIsAddedAfterTheFirstTest(): void
    {
        $tbId = $this->seedTb(['lab_assigned_code' => '0733']);
        $service = $this->service(RWANDA);

        $service->importResult(
            $this->ultra('0733', 'ERROR', 'Error 2014: probe check failed', '2026-09-18 10:00:00'),
            1
        );
        $service->importResult(
            $this->ultra('0733', 'MTB Trace DETECTED', 'RIF Resistance INDETERMINATE', '2026-09-18 14:00:00'),
            1
        );

        $tests = $this->tests($tbId);
        self::assertSame(
            ['No result/ invalid', 'MTB detected TRACE/RIF indeterminate'],
            array_column($tests, 'test_result')
        );
        self::assertSame('Error 2014: probe check failed', $tests[0]['comments']);
    }

    #[RunInSeparateProcess]
    public function testAnXpertRunAfterEveryOtherTestBecomesTheSamplesLatestTest(): void
    {
        // As the result page leaves a sample whose latest test is the smear.
        $tbId = $this->seedTb([
            'lab_assigned_code' => '0734',
            'sample_tested_datetime' => '2026-09-17 09:00:00',
            'tested_by' => 'smear-reader',
            'result_reviewed_by' => 'smear-reviewer',
        ]);
        LegacyAppHarness::db()->insert('tb_tests', [
            'tb_id' => $tbId,
            'lab_id' => 1,
            'test_type' => 'Smear Microscopy',
            'test_result' => 'Negative',
            'sample_tested_datetime' => '2026-09-17 09:00:00',
            'tested_by' => 'smear-reader',
            'result_reviewed_by' => 'smear-reviewer',
        ]);

        $outcome = $this->service(RWANDA)->importResult($this->ultra('0734', 'NOT DETECTED'), 1);

        self::assertSame('updated', $outcome['reason']);
        $sample = $this->sample($tbId);
        self::assertSame('2026-09-18 19:34:31', $sample['sample_tested_datetime']);
        self::assertNotSame('smear-reader', $sample['tested_by']);
        // Not reviewed yet: the smear's reviewer is not this test's.
        self::assertNull($sample['result_reviewed_by']);
        self::assertSame('interface', $sample['import_machine_file_name']);
    }

    #[RunInSeparateProcess]
    public function testAnXpertRunIsRecordedAfterALaterSmearWithoutTakingOverTheSample(): void
    {
        // The smear was entered first; the GeneXpert run reached VLSM later.
        $tbId = $this->seedTb(
            [
                'lab_assigned_code' => '0733',
                'sample_tested_datetime' => '2026-09-20 09:00:00',
                'tested_by' => 'smear-reader'
            ]
        );
        LegacyAppHarness::db()->insert('tb_tests', [
            'tb_id' => $tbId,
            'lab_id' => 1,
            'test_type' => 'Smear Microscopy',
            'test_result' => 'Negative',
            'sample_tested_datetime' => '2026-09-20 09:00:00',
            'tested_by' => 'smear-reader',
        ]);

        $outcome = $this->service(RWANDA)->importResult($this->ultra('0733', 'NOT DETECTED'), 1);

        self::assertSame('updated', $outcome['reason']);
        self::assertSame(['Negative', 'MTB not detected'], array_column($this->tests($tbId), 'test_result'));
        $sample = $this->sample($tbId);
        self::assertSame(
            '2026-09-20 09:00:00',
            $sample['sample_tested_datetime'],
            'the latest test is still the smear'
        );
        self::assertSame('smear-reader', $sample['tested_by']);
        self::assertNull($sample['import_machine_file_name']);
    }

    #[RunInSeparateProcess]
    public function testAnOlderXpertRunDoesNotFollowANewerOne(): void
    {
        $tbId = $this->seedTb(['lab_assigned_code' => '0733']);
        $service = $this->service(RWANDA);
        $service->importResult(
            $this->ultra('0733', 'DETECTED LOW', 'RIF Resistance NOT DETECTED', '2026-09-20 09:00:00'),
            1
        );

        $outcome = $service->importResult($this->ultra('0733', 'NOT DETECTED', '', '2026-09-18 19:34:31'), 1);

        self::assertSame('newer_result_on_record', $outcome['reason']);
        self::assertTrue($outcome['synced']);
        self::assertSame(['MTB Detected Low/RIF not detected'], array_column($this->tests($tbId), 'test_result'));
    }

    #[RunInSeparateProcess]
    public function testNothingIsAddedUnderAFinalInterpretation(): void
    {
        $tbId = $this->seedTb(['lab_assigned_code' => '0733', 'result' => 'Negative', 'is_result_finalized' => 'yes']);

        self::assertSame(
            'result_finalized',
            $this->service(RWANDA)->importResult($this->ultra('0733', 'NOT DETECTED'), 1)['reason']
        );
        self::assertSame([], $this->tests($tbId));
    }

    #[RunInSeparateProcess]
    public function testAResultThePerTestFormCannotShowIsLeftForTheLab(): void
    {
        $tbId = $this->seedTb(['lab_assigned_code' => '0733']);

        $outcome = $this->service(RWANDA)->importResult(
            $this->ultra('0733', 'DETECTED LOW', 'RIF Resistance INDETERMINATE'),
            1
        );

        self::assertSame('tb_result_not_on_form', $outcome['reason']);
        self::assertFalse($outcome['synced']);
        self::assertSame([], $this->tests($tbId));
    }

    // -----------------------------------------------------------------
    // Finding the sample
    // -----------------------------------------------------------------

    #[RunInSeparateProcess]
    public function testACodeTwoTbSamplesCarryMatchesNeither(): void
    {
        $first = $this->seedTb(['lab_assigned_code' => '724']);
        $second = $this->seedTb(['lab_assigned_code' => '724']);

        $outcome = $this->service(RWANDA)->importResult($this->ultra('724', 'NOT DETECTED'), 1);

        self::assertSame('ambiguous_sample', $outcome['reason']);
        self::assertSame([], $this->tests($first));
        self::assertSame([], $this->tests($second));
    }

    #[RunInSeparateProcess]
    public function testATbResultNeverLandsOnAViralLoadSampleWithTheSameCode(): void
    {
        LegacyAppHarness::db()->insert('form_vl', [
            'vlsm_instance_id' => 'test', 'sample_code' => 'VL-1', 'lab_assigned_code' => '0733',
            'facility_id' => 1, 'lab_id' => 1, 'result_status' => 6,
        ]);

        $outcome = $this->service(RWANDA)->importResult($this->ultra('0733', 'NOT DETECTED'), 1);

        self::assertSame('no_matching_sample', $outcome['reason']);
        self::assertNull(
            LegacyAppHarness::db()->rawQueryOne("SELECT result FROM form_vl WHERE sample_code = 'VL-1'")['result']
        );
    }

    #[RunInSeparateProcess]
    public function testMtbXdrAndResultsWithoutTheirRifampicinReadingAreLeftForTheLab(): void
    {
        $tbId = $this->seedTb(['lab_assigned_code' => '0733']);
        $service = $this->service(RWANDA);

        $xdr = ['test_type' => 'MTBXDR'] + $this->ultra('0733', 'DETECTED', 'INH Resistance NOT DETECTED');
        self::assertSame('unsupported_tb_test', $service->importResult($xdr, 1)['reason']);
        self::assertSame(
            'unreadable_tb_result',
            $service->importResult($this->ultra('0733', 'DETECTED LOW'), 1)['reason']
        );
        self::assertSame([], $this->tests($tbId));
    }

    #[RunInSeparateProcess]
    public function testARejectedSampleIsNotGivenAResult(): void
    {
        $tbId = $this->seedTb(['lab_assigned_code' => '0733', 'is_sample_rejected' => 'yes']);

        self::assertSame(
            'sample_rejected',
            $this->service(RWANDA)->importResult($this->ultra('0733', 'NOT DETECTED'), 1)['reason']
        );
        self::assertSame([], $this->tests($tbId));
    }

    // -----------------------------------------------------------------
    // Pools
    // -----------------------------------------------------------------

    /** @return list<int> */
    private function seedPool(): array
    {
        return array_map(
            fn(string $code): int => $this->seedTb(['lab_assigned_code' => $code]),
            ['0734', '0735', '0736', '0737']
        );
    }

    #[RunInSeparateProcess]
    public function testANegativePoolIsLeftForTheLabUntilTheyTurnReleaseOn(): void
    {
        $members = $this->seedPool();

        $outcome = $this->service(RWANDA)->importResult($this->ultra('0734,0735,0736,0737', 'NOT DETECTED'), 1);

        self::assertSame('pool_release_off', $outcome['reason']);
        self::assertFalse($outcome['synced']);
        foreach ($members as $tbId) {
            self::assertSame([], $this->tests($tbId));
        }
    }

    #[RunInSeparateProcess]
    public function testANegativePoolIsRecordedOnEveryMemberWhenReleaseIsOn(): void
    {
        $members = $this->seedPool();

        $outcome = $this->service(RWANDA, releaseNegativePools: true)
            ->importResult($this->ultra('0734, 0735,0736,0737', 'NOT DETECTED'), 1);

        self::assertSame('updated', $outcome['reason']);
        foreach ($members as $tbId) {
            $tests = $this->tests($tbId);
            self::assertCount(1, $tests);
            self::assertSame('MTB not detected', $tests[0]['test_result']);
            self::assertNull($this->sample($tbId)['pool_result'], 'a pool listed by sample codes is not tracked');
        }
        self::assertSame('Pooled with 0734, 0736, 0737: pool NOT DETECTED', $this->tests($members[1])[0]['comments']);
    }

    #[RunInSeparateProcess]
    public function testAPoolThatIsNotNegativeIsNeverRecorded(): void
    {
        $members = $this->seedPool();
        LegacyAppHarness::db()->rawQuery(
            'UPDATE form_tb SET sample_batch_id = 7 WHERE tb_id IN (' . implode(',', $members) . ')'
        );
        $service = $this->service(RWANDA, releaseNegativePools: true);

        foreach (
            [
            ['MTB Trace DETECTED', 'RIF Resistance INDETERMINATE', 'pool_positive_test_individually'],
            ['DETECTED LOW', 'RIF Resistance NOT DETECTED', 'pool_positive_test_individually'],
            ['ERROR', '', 'pool_invalid_retest'],
            ] as [$result, $notes, $reason]
        ) {
            $outcome = $service->importResult($this->ultra('0734,0735,0736,0737', $result, $notes), 1);
            self::assertSame($reason, $outcome['reason'], $result);
            self::assertFalse($outcome['synced']);
        }
        foreach ($members as $tbId) {
            self::assertSame([], $this->tests($tbId));
            $sample = $this->sample($tbId);
            self::assertNull($sample['pool_result'], 'a pool listed by sample codes is left for the lab');
            self::assertSame(7, (int) $sample['sample_batch_id']);
        }
    }

    // -----------------------------------------------------------------
    // Pools made in a TB batch
    // -----------------------------------------------------------------

    /** @return list<int> */
    private function seedBatchPool(string $poolCode = 'POOL-20261020001-1'): array
    {
        LegacyAppHarness::db()->rawQuery(
            "INSERT IGNORE INTO batch_details (batch_id, batch_code, machine, test_type)
                VALUES (7, '20261020001', 1, 'tb')"
        );
        return array_map(
            fn(string $code): int => $this->seedTb([
                'sample_code' => $code,
                'sample_batch_id' => 7,
                'pool_code' => $poolCode,
            ]),
            ['TB26-0101', 'TB26-0102', 'TB26-0103', 'TB26-0104']
        );
    }

    #[RunInSeparateProcess]
    public function testANegativeBatchPoolIsRecordedOnEverySampleInIt(): void
    {
        $members = $this->seedBatchPool();

        $outcome = $this->service(RWANDA, releaseNegativePools: true)
            ->importResult($this->ultra('POOL-20261020001-1', 'NOT DETECTED'), 1);

        self::assertSame('updated', $outcome['reason']);
        foreach ($members as $tbId) {
            self::assertSame(['MTB not detected'], array_column($this->tests($tbId), 'test_result'));
            self::assertSame('negative', $this->sample($tbId)['pool_result']);
            self::assertSame(7, (int) $this->sample($tbId)['sample_batch_id']);
        }
        self::assertSame(
            'Pool POOL-20261020001-1 NOT DETECTED, pooled with TB26-0101, TB26-0103, TB26-0104',
            $this->tests($members[1])[0]['comments']
        );
    }

    #[RunInSeparateProcess]
    public function testAPositiveBatchPoolSendsItsSamplesBackToBeTestedOnTheirOwn(): void
    {
        $members = $this->seedBatchPool();
        $service = $this->service(RWANDA, releaseNegativePools: true);
        $positive = $this->ultra('POOL-20261020001-1', 'DETECTED LOW', 'RIF Resistance NOT DETECTED');

        $outcome = $service->importResult($positive, 1);

        self::assertSame('pool_positive_test_individually', $outcome['reason']);
        self::assertTrue($outcome['updated']);
        foreach ($members as $tbId) {
            self::assertSame([], $this->tests($tbId), 'a pool result is never a sample result');
            self::assertSame('retest', $this->sample($tbId)['pool_result']);
            self::assertNull($this->sample($tbId)['sample_batch_id']);
        }

        // The lab puts one in a new batch. The same run sent again leaves it there.
        LegacyAppHarness::db()->rawQuery('UPDATE form_tb SET sample_batch_id = 8 WHERE tb_id = ?', [$members[0]]);
        $again = $service->importResult($positive, 1);
        self::assertFalse($again['updated']);
        self::assertSame(8, (int) $this->sample($members[0])['sample_batch_id']);

        // Its own result arrives under its own code.
        $own = $service->importResult(
            $this->ultra('TB26-0101', 'DETECTED LOW', 'RIF Resistance NOT DETECTED', '2026-09-20 10:00:00'),
            1
        );
        self::assertSame('updated', $own['reason']);
        self::assertSame(['MTB Detected Low/RIF not detected'], array_column($this->tests($members[0]), 'test_result'));
    }

    #[RunInSeparateProcess]
    public function testAPositiveBatchPoolLeavesAFinalisedSampleWhereItIs(): void
    {
        $members = $this->seedBatchPool();
        LegacyAppHarness::db()->rawQuery("UPDATE form_tb SET result = '1' WHERE tb_id = ?", [$members[2]]);

        $outcome = $this->service(RWANDA, releaseNegativePools: true)->importResult(
            $this->ultra('POOL-20261020001-1', 'DETECTED LOW', 'RIF Resistance NOT DETECTED'),
            1
        );

        self::assertSame('pool_positive_test_individually', $outcome['reason']);
        self::assertSame('retest', $this->sample($members[2])['pool_result'], 'the pool is recorded on every member');
        self::assertSame(7, (int) $this->sample($members[2])['sample_batch_id']);
        self::assertSame('retest', $this->sample($members[0])['pool_result']);
    }

    #[RunInSeparateProcess]
    public function testAPositiveBatchPoolLeavesASampleInTheBatchItMovedTo(): void
    {
        $members = $this->seedBatchPool();
        LegacyAppHarness::db()->rawQuery('UPDATE form_tb SET sample_batch_id = 9 WHERE tb_id = ?', [$members[3]]);

        $this->service(RWANDA, releaseNegativePools: true)
            ->importResult($this->ultra('POOL-20261020001-1', 'ERROR'), 1);

        self::assertSame('retest', $this->sample($members[3])['pool_result']);
        self::assertSame(9, (int) $this->sample($members[3])['sample_batch_id']);
        self::assertNull($this->sample($members[0])['sample_batch_id']);
    }

    #[RunInSeparateProcess]
    public function testABatchPoolWithALockedSampleIsNotRecordedUnlessForced(): void
    {
        $members = $this->seedBatchPool();
        LegacyAppHarness::db()->rawQuery("UPDATE form_tb SET locked = 'yes' WHERE tb_id = ?", [$members[1]]);

        $negative = $this->ultra('POOL-20261020001-1', 'NOT DETECTED');
        $outcome = $this->service(RWANDA, releaseNegativePools: true)->importResult($negative, 1);
        self::assertSame('pool_member_locked', $outcome['reason']);
        foreach ($members as $tbId) {
            self::assertSame([], $this->tests($tbId));
            self::assertSame('negative', $this->sample($tbId)['pool_result'], 'the run is still noted');
        }

        $forced = $this->service(RWANDA, releaseNegativePools: true)->importResult($negative, 1, includeLocked: true);
        self::assertSame('updated', $forced['reason']);
    }

    #[RunInSeparateProcess]
    public function testANegativeBatchPoolIsNotedEvenWhenReleaseIsOff(): void
    {
        $members = $this->seedBatchPool();

        $outcome = $this->service(RWANDA)->importResult($this->ultra('POOL-20261020001-1', 'NOT DETECTED'), 1);

        self::assertSame('pool_release_off', $outcome['reason']);
        self::assertFalse($outcome['synced']);
        foreach ($members as $tbId) {
            self::assertSame([], $this->tests($tbId), 'left for the lab to enter');
            self::assertSame('negative', $this->sample($tbId)['pool_result']);
        }
    }

    #[RunInSeparateProcess]
    public function testAPoolKeepsTheFirstResultRecordedForIt(): void
    {
        $members = $this->seedBatchPool();
        $service = $this->service(RWANDA, releaseNegativePools: true);
        $service->importResult($this->ultra('POOL-20261020001-1', 'ERROR'), 1);
        LegacyAppHarness::db()->rawQuery('UPDATE form_tb SET sample_batch_id = 8 WHERE tb_id = ?', [$members[0]]);

        $outcome = $service->importResult($this->ultra('POOL-20261020001-1', 'NOT DETECTED'), 1);

        self::assertSame('pool_result_on_record', $outcome['reason']);
        self::assertFalse($outcome['synced']);
        foreach ($members as $tbId) {
            self::assertSame([], $this->tests($tbId));
            self::assertSame('retest', $this->sample($tbId)['pool_result']);
        }
        self::assertSame(8, (int) $this->sample($members[0])['sample_batch_id']);
    }

    #[RunInSeparateProcess]
    public function testAPoolCodeThatIsAlsoASampleCodeIsNotRecorded(): void
    {
        $members = $this->seedBatchPool();
        $lookalike = $this->seedTb(['lab_assigned_code' => 'POOL-20261020001-1']);

        $outcome = $this->service(RWANDA, releaseNegativePools: true)
            ->importResult($this->ultra('POOL-20261020001-1', 'NOT DETECTED'), 1);

        self::assertSame('pool_code_ambiguous', $outcome['reason']);
        foreach ([...$members, $lookalike] as $tbId) {
            self::assertSame([], $this->tests($tbId));
            self::assertNull($this->sample($tbId)['pool_result']);
        }
    }

    #[RunInSeparateProcess]
    public function testABatchPoolIsPooledInWorksheetOrderAndNeverAgain(): void
    {
        $ids = array_map(
            fn(int $n): int => $this->seedTb(['sample_batch_id' => 7]),
            range(1, 6)
        );
        // The third was in a pool that was not negative.
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_tb SET pool_code = 'POOL-20260901001-2', pool_result = 'retest' WHERE tb_id = ?",
            [$ids[2]]
        );
        $pools = new TbPoolService(LegacyAppHarness::db());

        self::assertTrue($pools->assignPools(7, '20261020001', null, array_reverse($ids), 4));

        $codes = array_column(
            LegacyAppHarness::db()->rawQuery('SELECT tb_id, pool_code FROM form_tb ORDER BY tb_id'),
            'pool_code',
            'tb_id'
        );
        self::assertSame('POOL-20261020001-1', $codes[$ids[5]]);
        self::assertSame('POOL-20261020001-1', $codes[$ids[3]]);
        self::assertSame('POOL-20261020001-1', $codes[$ids[1]]);
        self::assertSame('POOL-20260901001-2', $codes[$ids[2]], 'its earlier pool is kept');
        self::assertSame('POOL-20261020001-1', $codes[$ids[4]]);
        self::assertNull($codes[$ids[0]], 'a last pool of one is no pool');

        // Once a pool has a result its cartridges have been run, and the pools stay.
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_tb SET pool_result = 'negative' WHERE pool_code = 'POOL-20261020001-1'"
        );
        self::assertFalse($pools->assignPools(7, '20261020001', null, $ids, 2));
        self::assertSame('POOL-20261020001-1', $this->sample($ids[5])['pool_code']);
    }

    #[RunInSeparateProcess]
    public function testOnlyAPrintedWorksheetWithPoolsFixesThem(): void
    {
        $ids = array_map(fn(int $n): int => $this->seedTb(['sample_batch_id' => 7]), range(1, 4));
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO batch_details (batch_id, batch_code, machine, test_type) VALUES (7, 'B7', 1, 'tb')"
        );
        $pools = new TbPoolService(LegacyAppHarness::db());
        $batch = fn(): array => LegacyAppHarness::db()->rawQueryOne('SELECT * FROM batch_details WHERE batch_id = 7');

        // Printed before it had pools: nothing on paper carries a pool code.
        self::assertTrue($pools->assignPools(7, 'B7', TbPoolService::poolsPrintedAt($batch()), $ids, 4));
        self::assertFalse($pools->isBatchLocked($batch()));

        $pools->markPoolsPrinted(7, '2026-10-20 10:00:00');
        self::assertTrue($pools->isBatchLocked($batch()));
        self::assertSame('2026-10-20 10:00:00', TbPoolService::poolsPrintedAt($batch()));
        self::assertFalse($pools->assignPools(7, 'B7', TbPoolService::poolsPrintedAt($batch()), $ids, 2));
    }

    #[RunInSeparateProcess]
    public function testAPoolLeftWithOneSampleIsNoPool(): void
    {
        $ids = array_map(fn(int $n): int => $this->seedTb(['sample_batch_id' => 7]), range(1, 6));
        $pools = new TbPoolService(LegacyAppHarness::db());
        self::assertTrue($pools->assignPools(7, 'B7', null, $ids, 4));
        self::assertSame('POOL-B7-2', $this->sample($ids[5])['pool_code']);

        // One sample of the pool of two leaves the batch.
        LegacyAppHarness::db()->rawQuery('UPDATE form_tb SET sample_batch_id = NULL WHERE tb_id = ?', [$ids[4]]);
        $pools->releaseRemovedSamples(7, 'B7');

        self::assertNull($this->sample($ids[4])['pool_code']);
        self::assertNull($this->sample($ids[5])['pool_code'], 'the one left runs on its own');
        self::assertSame('POOL-B7-1', $this->sample($ids[0])['pool_code']);
    }

    #[RunInSeparateProcess]
    public function testTheWorksheetKeepsControlsInTheirPlace(): void
    {
        $ids = array_map(fn(int $n): int => $this->seedTb(['sample_batch_id' => 7]), range(1, 3));
        $pools = new TbPoolService(LegacyAppHarness::db());
        $pools->assignPools(7, 'B7', null, $ids, 2);

        $order = json_encode([
            'in_house_controls_1', 's_' . $ids[0], 's_' . $ids[1], 's_' . $ids[2], 'manufacturer_controls_1',
        ]);
        $rows = $pools->cartridgesOfBatch(7, 'B7', $order);

        self::assertSame(['control', 'pool', 'sample', 'control'], array_column($rows, 'kind'));
        self::assertSame(
            ['in_house_controls_1', 'POOL-B7-1', $this->sample($ids[2])['sample_code'], 'manufacturer_controls_1'],
            array_column($rows, 'code')
        );
        self::assertCount(2, $rows[1]['samples']);
    }

    #[RunInSeparateProcess]
    public function testPoolsAreFixedOncePrintedAndKeptApartFromLookalikeBatches(): void
    {
        $ids = array_map(fn(int $n): int => $this->seedTb(['sample_batch_id' => 7]), range(1, 4));
        // Batch "TB1-2" is a different batch whose code starts with "TB1-".
        $other = $this->seedTb(['sample_batch_id' => 9, 'pool_code' => 'POOL-TB1-2-1']);
        $pools = new TbPoolService(LegacyAppHarness::db());

        self::assertTrue($pools->assignPools(7, 'TB1', null, $ids, 4));
        self::assertSame('POOL-TB1-1', $this->sample($ids[0])['pool_code']);
        self::assertSame('POOL-TB1-2-1', $this->sample($other)['pool_code'], 'another batch\'s pool is left alone');

        self::assertFalse($pools->assignPools(7, 'TB1', '2026-10-20 09:00:00', array_reverse($ids), 2));
        self::assertSame('POOL-TB1-1', $this->sample($ids[3])['pool_code'], 'printed pools stay as printed');

        $pools->clearPools('TB1');
        self::assertSame('POOL-TB1-2-1', $this->sample($other)['pool_code']);

        // A batch whose code differs only in case is another batch.
        $upper = $this->seedTb(['sample_batch_id' => 10, 'pool_code' => 'POOL-tb1-1']);
        $pools->clearPools('TB1');
        self::assertSame('POOL-tb1-1', $this->sample($upper)['pool_code']);
    }

    #[RunInSeparateProcess]
    public function testAPoolWithAMemberNotFoundIsNotRecordedOnAnyMember(): void
    {
        $members = $this->seedPool();

        $outcome = $this->service(RWANDA, releaseNegativePools: true)
            ->importResult($this->ultra('0734,0735,0736,0799', 'NOT DETECTED'), 1);

        self::assertSame('pool_member_not_found', $outcome['reason']);
        foreach ($members as $tbId) {
            self::assertSame([], $this->tests($tbId));
        }
    }

    #[RunInSeparateProcess]
    public function testAPoolWithARejectedMemberIsNotRecordedOnAnyMember(): void
    {
        $members = $this->seedPool();
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_tb SET is_sample_rejected = 'yes' WHERE tb_id = ?",
            [$members[2]]
        );

        foreach ([RWANDA, BURKINA_FASO] as $form) {
            $outcome = $this->service($form, releaseNegativePools: true)
                ->importResult($this->ultra('0734,0735,0736,0737', 'NOT DETECTED'), 1);

            self::assertSame('pool_member_sample_rejected', $outcome['reason']);
            self::assertFalse($outcome['synced']);
            foreach ($members as $tbId) {
                self::assertSame([], $this->tests($tbId));
                self::assertNull($this->sample($tbId)['xpert_mtb_result']);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testAPoolDoesNotOverwriteAMemberTestedOnItsOwnSince(): void
    {
        $members = $this->seedPool();
        $service = $this->service(RWANDA, releaseNegativePools: true);
        $service->importResult(
            $this->ultra('0735', 'DETECTED LOW', 'RIF Resistance NOT DETECTED', '2026-09-19 08:00:00'),
            1
        );

        $outcome = $service->importResult($this->ultra('0734,0735,0736,0737', 'NOT DETECTED'), 1);

        self::assertSame('updated', $outcome['reason']);
        self::assertSame(['MTB Detected Low/RIF not detected'], array_column($this->tests($members[1]), 'test_result'));
        self::assertSame(['MTB not detected'], array_column($this->tests($members[0]), 'test_result'));
    }

    #[RunInSeparateProcess]
    public function testAPoolThatFailsPartWayThrowsSoTheCallerRollsBack(): void
    {
        $this->seedPool();
        // Only the first member's row can be added.
        LegacyAppHarness::db()->mysqli()->query('CREATE TRIGGER fail_second BEFORE INSERT ON tb_tests FOR EACH ROW
            BEGIN IF (SELECT COUNT(*) FROM tb_tests) >= 1 THEN SIGNAL SQLSTATE \'45000\'; END IF; END');

        $this->expectException(RuntimeException::class);
        $this->service(RWANDA, releaseNegativePools: true)->importResult(
            $this->ultra('0734,0735,0736,0737', 'NOT DETECTED'),
            1
        );
    }

    // -----------------------------------------------------------------
    // The single-result forms
    // -----------------------------------------------------------------

    #[RunInSeparateProcess]
    public function testTheSingleResultFormsKeepTheXpertCodeOnTheSample(): void
    {
        $tbId = $this->seedTb(['lab_assigned_code' => '0733', 'lab_tech_comments' => 'Sputum, salivary']);

        $outcome = $this->service(BURKINA_FASO)->importResult(
            $this->ultra('0733', 'DETECTED HIGH', 'RIF Resistance DETECTED'),
            1
        );

        self::assertSame('updated', $outcome['reason']);
        $sample = $this->sample($tbId);
        self::assertSame('RR (MTB detected rifampicin resistance detected)', LegacyAppHarness::db()->rawQueryOne(
            'SELECT result FROM r_tb_results WHERE result_id = ?',
            [$sample['xpert_mtb_result']]
        )['result']);
        self::assertSame('2026-09-18', $sample['xpert_result_date']);
        self::assertSame('Sputum, salivary', $sample['lab_tech_comments'], 'the lab\'s comments are kept');
        self::assertNull($sample['result']);
        self::assertSame([], $this->tests($tbId), 'tb_tests holds microscopy on these forms');

        self::assertSame('already_up_to_date', $this->service(BURKINA_FASO)->importResult(
            $this->ultra('0733', 'DETECTED HIGH', 'RIF Resistance DETECTED'),
            1
        )['reason']);
    }

    /** The r_tb_results id of the Xpert result that starts with this code. */
    private function xpertId(string $code): string
    {
        return (string) LegacyAppHarness::db()->rawQueryOne(
            "SELECT result_id FROM r_tb_results WHERE result_type = 'x-pert' AND result LIKE ?",
            ["$code (%"]
        )['result_id'];
    }

    #[RunInSeparateProcess]
    public function testTheSingleResultFormsNeverReplaceARecordedXpertResult(): void
    {
        $tbId = $this->seedTb(
            [
                'lab_assigned_code' => '0733',
                'xpert_mtb_result' => $this->xpertId('T'),
                'xpert_result_date' => '2026-09-17'
            ]
        );

        $outcome = $this->service(BURKINA_FASO)->importResult($this->ultra('0733', 'NOT DETECTED'), 1);

        self::assertSame('xpert_result_on_record', $outcome['reason']);
        self::assertFalse($outcome['synced']);
        self::assertSame($this->xpertId('T'), $this->sample($tbId)['xpert_mtb_result']);
    }

    #[RunInSeparateProcess]
    public function testTheSingleResultFormsTakeTheRetestOfAnInvalidRun(): void
    {
        $tbId = $this->seedTb(
            [
                'lab_assigned_code' => '0733',
                'xpert_mtb_result' => $this->xpertId('I'),
                'xpert_result_date' => '2026-09-17'
            ]
        );
        $service = $this->service(BURKINA_FASO);

        self::assertSame(
            'updated',
            $service->importResult($this->ultra('0733', 'DETECTED LOW', 'RIF Resistance NOT DETECTED'), 1)['reason']
        );
        self::assertSame($this->xpertId('T'), $this->sample($tbId)['xpert_mtb_result']);
    }

    #[RunInSeparateProcess]
    public function testAnRrResultIsNotWrittenWhileTheFormsCannotShowIt(): void
    {
        // What an STS not yet on 5.7.79 sends back on a metadata sync.
        LegacyAppHarness::db()->rawQuery("UPDATE r_tb_results SET result_type = 'lam' WHERE result LIKE 'RR (%'");
        $tbId = $this->seedTb(['lab_assigned_code' => '0733']);

        $outcome = $this->service(BURKINA_FASO)->importResult(
            $this->ultra('0733', 'DETECTED HIGH', 'RIF Resistance DETECTED'),
            1
        );

        self::assertSame('tb_result_not_on_form', $outcome['reason']);
        self::assertNull($this->sample($tbId)['xpert_mtb_result']);
    }

    #[RunInSeparateProcess]
    public function testTheSingleResultFormsRecordAPoolNoteWhereThereIsNoComment(): void
    {
        $members = $this->seedPool();

        $this->service(BURKINA_FASO, releaseNegativePools: true)->importResult(
            $this->ultra('0734,0735,0736,0737', 'NOT DETECTED'),
            1
        );

        $sample = $this->sample($members[0]);
        self::assertSame('N (MTB not detected)', LegacyAppHarness::db()->rawQueryOne(
            'SELECT result FROM r_tb_results WHERE result_id = ?',
            [$sample['xpert_mtb_result']]
        )['result']);
        self::assertSame('Pooled with 0735, 0736, 0737: pool NOT DETECTED', $sample['lab_tech_comments']);
    }
}
