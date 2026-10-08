<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\InterfacingService;
use App\Services\TestAttemptService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Support\LegacyAppHarness;

use const COUNTRY\RWANDA;

/**
 * The assay an analyzer reports reaches assay_name (5.7.82) on VL and EID samples.
 *
 * A result the analyzer re-sends unchanged must stay a no-op: GeneXpert "Automatic
 * Result Upload" re-sends its whole history, and if the newly saved assay made each
 * old row look changed, every one would be archived as an attempt, rewritten and
 * queued for the STS again. Those rows only get the assay filled in.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class InterfacingAssayTest extends TestCase
{
    private const DATABASE = 'intelis_interfacing_assay_test';

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
            'r_sample_status', 'global_config', 'form_vl', 'form_eid', 'roles', 'user_details',
            'instruments', 'instrument_machines', 'test_result_attempts',
        ]);

        LegacyAppHarness::addMigrationColumns('5.7.82', ['form_vl', 'form_eid']);

        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO roles (role_id, role_name, status) VALUES (4, 'Lab Technician', 'active')"
        );
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name)
             VALUES (5, 'Failed'), (6, 'Received at lab'), (7, 'Accepted'), (8, 'Awaiting approval')"
        );
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $columns */
    private function seed(string $table, array $columns): void
    {
        LegacyAppHarness::db()->insert($table, $columns + [
            'vlsm_instance_id' => 'test',
            'facility_id' => 1,
            'lab_id' => 1,
            'result_status' => 6,
        ]);
    }

    private function service(): InterfacingService
    {
        $service = ContainerRegistry::get(InterfacingService::class);
        $reflection = new ReflectionClass(InterfacingService::class);
        $reflection->getProperty('activeModules')->setValue(
            $service,
            ['vl_sample_id' => 'form_vl', 'eid_id' => 'form_eid']
        );
        $reflection->getProperty('formId')->setValue($service, RWANDA);
        $reflection->getProperty('autoApprove')->setValue($service, false);

        return $service;
    }

    /** @return array<string, mixed> */
    private function analyzerRow(string $sampleCode, string $result, string $assay): array
    {
        return [
            'id' => 1,
            'order_id' => $sampleCode,
            'test_id' => $sampleCode,
            'test_type' => $assay,
            'test_unit' => 'cp/mL',
            'results' => $result,
            'notes' => '',
            'tested_by' => 'Example Operator',
            'machine_used' => 'GeneXpert',
            'analysed_date_time' => '2026-10-01 10:00:00',
            'result_accepted_date_time' => '2026-10-01 10:00:00',
            'authorised_date_time' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function row(string $table, string $sampleCode): array
    {
        return LegacyAppHarness::db()->rawQueryOne("SELECT * FROM `$table` WHERE sample_code = ?", [$sampleCode]);
    }

    private function attempts(): int
    {
        return (int) LegacyAppHarness::db()->rawQueryOne('SELECT COUNT(*) AS n FROM test_result_attempts')['n'];
    }

    #[RunInSeparateProcess]
    public function testAViralLoadResultKeepsTheAssayTheAnalyzerReported(): void
    {
        $this->seed('form_vl', ['sample_code' => 'VL-1']);

        $outcome = $this->service()->importResult($this->analyzerRow('VL-1', '1250', ' Xpert HIV-1 Viral Load '), 1);

        self::assertSame('updated', $outcome['reason']);
        self::assertSame('Xpert HIV-1 Viral Load', $this->row('form_vl', 'VL-1')['assay_name']);
    }

    #[RunInSeparateProcess]
    public function testAnEidResultKeepsTheAssayTheAnalyzerReported(): void
    {
        $this->seed('form_eid', ['sample_code' => 'EID-1']);

        $outcome = $this->service()->importResult($this->analyzerRow('EID-1', 'Negative', 'Xpert HIV-1 Qual'), 1);

        self::assertSame('updated', $outcome['reason']);
        self::assertSame('Xpert HIV-1 Qual', $this->row('form_eid', 'EID-1')['assay_name']);
    }

    #[RunInSeparateProcess]
    public function testNoAssayIsStoredAsNullRatherThanBlank(): void
    {
        $this->seed('form_vl', ['sample_code' => 'VL-2']);

        $this->service()->importResult($this->analyzerRow('VL-2', '1250', '  '), 1);

        self::assertNull($this->row('form_vl', 'VL-2')['assay_name']);
    }

    #[RunInSeparateProcess]
    public function testAnUnchangedResentResultOnlyGetsItsAssayFilledIn(): void
    {
        $this->seed('form_vl', ['sample_code' => 'VL-3']);
        $service = $this->service();
        $service->importResult($this->analyzerRow('VL-3', '1250', ''), 1);

        // The row as an install held it before 5.7.82: same result, no assay.
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_vl SET assay_name = NULL, last_modified_datetime = '2026-10-01 11:00:00'
              WHERE sample_code = 'VL-3'"
        );
        $before = $this->row('form_vl', 'VL-3');
        $attemptsBefore = $this->attempts();

        $outcome = $service->importResult($this->analyzerRow('VL-3', '1250', 'Xpert HIV-1 Viral Load'), 1);

        $after = $this->row('form_vl', 'VL-3');
        self::assertSame('already_up_to_date', $outcome['reason'], 're-sending the same result is not an update');
        self::assertFalse($outcome['updated']);
        self::assertSame('Xpert HIV-1 Viral Load', $after['assay_name']);
        self::assertSame($before['last_modified_datetime'], $after['last_modified_datetime'], 'not re-sent to the STS');
        self::assertSame($before['data_sync'], $after['data_sync']);
        self::assertSame($attemptsBefore, $this->attempts(), 'nothing is archived as a superseded attempt');
    }

    #[RunInSeparateProcess]
    public function testAnUnchangedResentResultLeavesARecordedAssayAlone(): void
    {
        $this->seed('form_vl', ['sample_code' => 'VL-4']);
        $service = $this->service();
        $service->importResult($this->analyzerRow('VL-4', '1250', 'HIV1.0mlDBS'), 1);

        $outcome = $service->importResult($this->analyzerRow('VL-4', '1250', 'Something Else'), 1);

        self::assertSame('already_up_to_date', $outcome['reason']);
        self::assertSame('HIV1.0mlDBS', $this->row('form_vl', 'VL-4')['assay_name']);
    }

    #[RunInSeparateProcess]
    public function testAResetForRetestClearsTheAssayAndKeepsItWithTheArchivedRun(): void
    {
        foreach (['form_vl' => 'VL-6', 'form_eid' => 'EID-6'] as $table => $code) {
            $this->seed($table, ['sample_code' => $code]);
            $this->service()->importResult($this->analyzerRow($code, 'Invalid', 'Xpert HIV-1'), 1);
            $id = (int) LegacyAppHarness::db()->rawQueryOne(
                "SELECT " . ($table === 'form_vl' ? 'vl_sample_id' : 'eid_id') . " AS id
                   FROM `$table` WHERE sample_code = ?",
                [$code]
            )['id'];

            ContainerRegistry::get(TestAttemptService::class)
                ->resetForRetest($table === 'form_vl' ? 'vl' : 'eid', [$id], 6);

            self::assertNull($this->row($table, $code)['assay_name'], "$table: a hand-entered re-test has no assay");
            $archived = LegacyAppHarness::db()->rawQueryOne(
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(attempt_data, '$.row.assay_name')) AS assay
                   FROM test_result_attempts WHERE form_table = ? AND record_id = ?",
                [$table, $id]
            );
            self::assertSame('Xpert HIV-1', $archived['assay'], "$table: the failed run keeps its assay");
        }
    }

    #[RunInSeparateProcess]
    public function testANewResultReplacesTheAssayWithTheNewRunsOne(): void
    {
        $this->seed('form_vl', ['sample_code' => 'VL-5']);
        $service = $this->service();
        $service->importResult($this->analyzerRow('VL-5', 'Invalid', 'HIV1.0mlDBS'), 1);

        $outcome = $service->importResult($this->analyzerRow('VL-5', '1250', 'HIV1.0mlPlasma'), 1);

        self::assertSame('updated', $outcome['reason']);
        self::assertSame('HIV1.0mlPlasma', $this->row('form_vl', 'VL-5')['assay_name']);
    }

    /** A GeneXpert message as the Interface Tool keeps it in raw_text. */
    private function genexpertMessage(string $sampleCode, string $result): string
    {
        return implode('<CR>', [
            'H|@^\\|GXM-00000000001||EXAMPLE HOSPITAL^GeneXpert^6.2|||||Example Hospital||P|1394-97|20261001100000',
            "O|1|$sampleCode||^^^HIV-1_VL 2 2|R|20261001093000|||||||||ORH||||||||||F",
            "R|1|^^^HIV-1_VL 2 2^Xpert_HIV-1 Viral Load^2^^|^$result|copies/mL|40.00 to 10000000.00|A||F"
                . '||Example Operator|20261001093000|20261001100000'
                . '|Cepheid-1F21001^806911^630912^1113243530^72203^20260825|',
            'L|1|N',
        ]);
    }

    #[RunInSeparateProcess]
    public function testAResultSentOverTheApiGetsItsAssayAndLotFromTheMessage(): void
    {
        $this->seed('form_vl', ['sample_code' => 'VL-7']);
        // The results API carries raw_text but no test_type.
        $row = ['raw_text' => $this->genexpertMessage('VL-7', '1250')] + $this->analyzerRow('VL-7', '1250', '');
        unset($row['test_type']);

        $outcome = $this->service()->importResult($row, 1);

        $stored = $this->row('form_vl', 'VL-7');
        self::assertSame('updated', $outcome['reason']);
        self::assertSame('Xpert_HIV-1 Viral Load', $stored['assay_name']);
        self::assertSame('72203', $stored['lot_number']);
        self::assertSame('2026-08-25', $stored['lot_expiration_date']);
    }

    #[RunInSeparateProcess]
    public function testTheMessagesAssayIsPreferredToTheLabsOwnTestCode(): void
    {
        $this->seed('form_eid', ['sample_code' => 'EID-7']);
        $row = ['raw_text' => $this->genexpertMessage('EID-7', '1250')]
            + $this->analyzerRow('EID-7', 'Negative', 'HIV-1_VL 2 2');

        $this->service()->importResult($row, 1);

        self::assertSame('Xpert_HIV-1 Viral Load', $this->row('form_eid', 'EID-7')['assay_name']);
        self::assertSame('72203', $this->row('form_eid', 'EID-7')['lot_number']);
    }

    #[RunInSeparateProcess]
    public function testAnUnchangedResentResultGetsItsLotFilledInQuietly(): void
    {
        $this->seed('form_vl', ['sample_code' => 'VL-8']);
        $service = $this->service();
        $row = ['raw_text' => $this->genexpertMessage('VL-8', '1250')] + $this->analyzerRow('VL-8', '1250', '');
        $service->importResult($row, 1);

        // The row as an install held it before the lot was read: same result, no lot.
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_vl SET lot_number = NULL, lot_expiration_date = NULL,
                    last_modified_datetime = '2026-10-01 11:00:00' WHERE sample_code = 'VL-8'"
        );
        $before = $this->row('form_vl', 'VL-8');
        $attemptsBefore = $this->attempts();

        $outcome = $service->importResult($row, 1);

        $after = $this->row('form_vl', 'VL-8');
        self::assertSame('already_up_to_date', $outcome['reason']);
        self::assertSame('72203', $after['lot_number']);
        self::assertSame('2026-08-25', $after['lot_expiration_date']);
        self::assertSame($before['last_modified_datetime'], $after['last_modified_datetime'], 'not re-sent to the STS');
        self::assertSame($attemptsBefore, $this->attempts(), 'nothing is archived as a superseded attempt');
    }

    #[RunInSeparateProcess]
    public function testAnUnchangedResentResultNeverReplacesALotAUserTyped(): void
    {
        $this->seed('form_vl', ['sample_code' => 'VL-9']);
        $service = $this->service();
        $row = ['raw_text' => $this->genexpertMessage('VL-9', '1250')] + $this->analyzerRow('VL-9', '1250', '');
        $service->importResult($row, 1);
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_vl SET lot_number = 'TYPED-1', lot_expiration_date = NULL WHERE sample_code = 'VL-9'"
        );

        $outcome = $service->importResult($row, 1);

        $after = $this->row('form_vl', 'VL-9');
        self::assertSame('already_up_to_date', $outcome['reason']);
        self::assertSame('TYPED-1', $after['lot_number']);
        self::assertNull($after['lot_expiration_date'], 'no expiry of another lot next to the typed one');
    }

    #[RunInSeparateProcess]
    public function testAnUnchangedResentResultNeverPutsALotNextToAnExpiryAUserTyped(): void
    {
        $this->seed('form_vl', ['sample_code' => 'VL-10']);
        $service = $this->service();
        $row = ['raw_text' => $this->genexpertMessage('VL-10', '1250')] + $this->analyzerRow('VL-10', '1250', '');
        $service->importResult($row, 1);
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_vl SET lot_number = NULL, lot_expiration_date = '2025-12-01' WHERE sample_code = 'VL-10'"
        );

        $service->importResult($row, 1);

        $after = $this->row('form_vl', 'VL-10');
        self::assertNull($after['lot_number']);
        self::assertSame('2025-12-01', $after['lot_expiration_date']);
    }

    #[RunInSeparateProcess]
    public function testALotWrittenElsewhereAfterTheRowWasReadIsNotGivenAnotherLotsExpiry(): void
    {
        $this->seed('form_vl', ['sample_code' => 'VL-11']);
        $service = $this->service();
        $row = ['raw_text' => $this->genexpertMessage('VL-11', '1250')] + $this->analyzerRow('VL-11', '1250', '');
        $service->importResult($row, 1);
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_vl SET lot_number = NULL, lot_expiration_date = NULL WHERE sample_code = 'VL-11'"
        );
        $readEarlier = $this->row('form_vl', 'VL-11');
        // A result file import records its own lot, without an expiry, in between.
        LegacyAppHarness::db()->rawQuery("UPDATE form_vl SET lot_number = 'FILE-1' WHERE sample_code = 'VL-11'");

        (new ReflectionClass(InterfacingService::class))->getMethod('backfillRunDetails')->invoke(
            $service,
            'form_vl',
            'vl_sample_id',
            $readEarlier,
            InterfacingService::runDetails($row)
        );

        $after = $this->row('form_vl', 'VL-11');
        self::assertSame('FILE-1', $after['lot_number']);
        self::assertNull($after['lot_expiration_date']);
    }
}
