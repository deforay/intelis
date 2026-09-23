<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\InterfacingService;
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
 * A pool is one run whose sample ID lists its members. It is applied whole or not
 * at all, only when negative, and only where the lab has turned that on.
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
        ]);

        // The Xpert results exactly as an install is seeded with them.
        $initSql = (string) file_get_contents(dirname(__DIR__, 2) . '/sql/init.sql');
        preg_match('/^INSERT INTO `r_tb_results` VALUES .*?;$/m', $initSql, $seed);
        LegacyAppHarness::db()->rawQuery($seed[0]);
        // And as migration 5.7.79 leaves them.
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . '/sys/migrations/5.7.79.sql');
        preg_match('/^UPDATE `r_tb_results`.*?;$/ms', $migration, $fix);
        LegacyAppHarness::db()->rawQuery($fix[0]);
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
        }
        self::assertSame('Pooled with 0734, 0736, 0737: pool NOT DETECTED', $this->tests($members[1])[0]['comments']);
    }

    #[RunInSeparateProcess]
    public function testAPoolThatIsNotNegativeIsNeverRecorded(): void
    {
        $members = $this->seedPool();
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
        }
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
