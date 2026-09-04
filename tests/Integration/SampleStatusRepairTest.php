<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\SampleStatusRepairService;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The repair that puts a sample's status back to what its own record proves.
 *
 * Each seeded row is one case the repair has to tell apart, and the test asserts
 * on the row after the sweep rather than on a count, because what matters is
 * that the right column moved on the right row: an invented test date gone, a
 * real-looking one kept, a genuine result never touched.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SampleStatusRepairTest extends TestCase
{
    private const DATABASE = 'intelis_status_repair_test';

    private const LAB_ID = 701;
    private const FACILITY_ID = 702;

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

        LegacyAppHarness::boot(self::DATABASE, [
            'facility_details', 'r_sample_status', 'form_eid', 'form_hepatitis',
            'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $this->seed('CLEAR-COPIED-RECEIPT', [
            // Test date is the receipt stamp copied. Invented, so it goes.
            'result_status' => 7,
            'sample_received_at_lab_datetime' => '2026-03-18 11:46:00',
            'sample_tested_datetime' => '2026-03-18 11:46:00',
            'result_approved_datetime' => '2026-06-30 13:58:09',
            'result_approved_by' => 'someone',
            'result_reviewed_by' => 'someone',
        ]);
        $this->seed('CLEAR-COPIED-COLLECTION', [
            // Same, but copied from the collection stamp instead.
            'result_status' => 7,
            'sample_collection_date' => '2026-03-10 09:00:00',
            'sample_received_at_lab_datetime' => '2026-03-12 09:00:00',
            'sample_tested_datetime' => '2026-03-10 09:00:00',
            'result_approved_datetime' => '2026-06-30 13:58:09',
        ]);
        $this->seed('KEEP-REAL-TEST-DATE', [
            // Four days after receipt: what a real bench date looks like.
            'result_status' => 8,
            'sample_received_at_lab_datetime' => '2026-03-12 09:00:00',
            'sample_tested_datetime' => '2026-03-16 14:20:00',
            'result_approved_datetime' => '2026-06-30 13:58:09',
            'tested_by' => 'the technician',
        ]);
        $this->seed('NEVER-REACHED-A-LAB', [
            // Marked accepted with no lab receipt at all.
            'result_status' => 7,
            'result_approved_datetime' => '2026-06-30 13:58:09',
        ]);
        $this->seed('HAS-A-RESULT', [
            // A finished sample. Nothing here is wrong; it must not be touched.
            'result_status' => 7,
            'result' => 'Not Detected',
            'sample_received_at_lab_datetime' => '2026-03-12 09:00:00',
            'sample_tested_datetime' => '2026-03-16 14:20:00',
            'result_approved_datetime' => '2026-03-17 10:00:00',
        ]);
        $this->seed('REJECTED-NO-RESULT', [
            // Rejected samples never have a result and are not this defect.
            'result_status' => 7,
            'is_sample_rejected' => 'yes',
            'sample_received_at_lab_datetime' => '2026-03-12 09:00:00',
            'result_approved_datetime' => '2026-06-30 13:58:09',
        ]);
        $this->seed('STILL-AT-THE-LAB', [
            // Correctly recorded as waiting. Outside the repair's predicate.
            'result_status' => 6,
            'sample_received_at_lab_datetime' => '2026-03-12 09:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $columns */
    private function seed(string $sampleCode, array $columns): void
    {
        $row = $columns + [
            'vlsm_instance_id' => 'test',
            'sample_code' => $sampleCode,
            'facility_id' => self::FACILITY_ID,
            'lab_id' => self::LAB_ID,
            'sample_collection_date' => '2026-03-10 09:00:00',
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            $values[] = $value === null ? 'NULL' : "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_eid (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
        );
    }

    /** @return array<string, mixed> */
    private function fetch(string $sampleCode): array
    {
        return (array) LegacyAppHarness::db()->rawQueryOne(
            "SELECT * FROM form_eid WHERE sample_code = '" . LegacyAppHarness::db()->escape($sampleCode) . "'"
        );
    }

    private function sweep(?int $sinceMonths = null): array
    {
        /** @var SampleStatusRepairService $service */
        $service = ContainerRegistry::get(SampleStatusRepairService::class);
        // No pause: the sleep is there to be kind to a live server, not to slow
        // the test down.
        return $service->repairAcceptedWithoutResult('eid', $sinceMonths, null, 200, 0);
    }

    #[RunInSeparateProcess]
    public function testAnInventedTestDateIsClearedAndTheSampleGoesBackToTheLabQueue(): void
    {
        $result = $this->sweep();
        self::assertSame(4, $result['repaired'], 'the four contradicting rows, and only those');
        self::assertSame(3, $result['datesCleared'], 'two copied test dates, plus the row that never had one');

        foreach (['CLEAR-COPIED-RECEIPT', 'CLEAR-COPIED-COLLECTION'] as $sampleCode) {
            $row = $this->fetch($sampleCode);
            self::assertSame(6, (int) $row['result_status'], "$sampleCode is back at the lab");
            self::assertNull($row['sample_tested_datetime'], "$sampleCode had a copied test date, not a real one");
            self::assertNull($row['result_approved_datetime']);
            self::assertNull($row['result_approved_by']);
            self::assertNull($row['result_reviewed_by']);
            self::assertSame(0, (int) $row['data_sync'], 'the correction has to reach STS too');
        }
    }

    #[RunInSeparateProcess]
    public function testARealLookingTestDateSurvivesTheRepair(): void
    {
        $this->sweep();

        $row = $this->fetch('KEEP-REAL-TEST-DATE');
        self::assertSame('2026-03-16 14:20:00', $row['sample_tested_datetime'], 'four days after receipt');
        self::assertSame('the technician', $row['tested_by'], 'who ran it is not in dispute');
        self::assertSame(6, (int) $row['result_status'], 'but nothing about it was approved');
        self::assertNull($row['result_approved_datetime']);
    }

    #[RunInSeparateProcess]
    public function testASampleNoLabEverReceivedGoesBackToTheCollectionPoint(): void
    {
        $this->sweep();

        $row = $this->fetch('NEVER-REACHED-A-LAB');
        self::assertSame(9, (int) $row['result_status'], 'no lab receipt, so it never left the clinic');
        self::assertNull($row['result_approved_datetime']);
    }

    #[RunInSeparateProcess]
    public function testRowsThatAreNotContradictoryAreLeftAlone(): void
    {
        $this->sweep();

        $finished = $this->fetch('HAS-A-RESULT');
        self::assertSame(7, (int) $finished['result_status'], 'a real result stays accepted');
        self::assertSame('2026-03-17 10:00:00', $finished['result_approved_datetime']);
        self::assertSame('2026-03-16 14:20:00', $finished['sample_tested_datetime']);

        $rejected = $this->fetch('REJECTED-NO-RESULT');
        self::assertSame(7, (int) $rejected['result_status'], 'a rejected sample never had a result to lose');

        $waiting = $this->fetch('STILL-AT-THE-LAB');
        self::assertSame(6, (int) $waiting['result_status']);
    }

    #[RunInSeparateProcess]
    public function testTheNightlyWindowLeavesOlderRowsToTheOneTimePass(): void
    {
        // Everything seeded here is dated 2026-03, well outside a one-month
        // window, and none of it has been modified since.
        LegacyAppHarness::db()->rawQuery("UPDATE form_eid SET last_modified_datetime = '2026-03-20 00:00:00'");

        $result = $this->sweep(1);
        self::assertSame(0, $result['repaired'], 'the nightly job does not re-scan settled history');

        $row = $this->fetch('CLEAR-COPIED-RECEIPT');
        self::assertSame(7, (int) $row['result_status'], 'left exactly as it was, for the one-time pass');
    }

    #[RunInSeparateProcess]
    public function testAHepatitisSampleResultedByTheAnalyzerIsNotMistakenForAnEmptyOne(): void
    {
        // Hepatitis is the one type whose result can live in three columns. A
        // row the analyzer resulted holds a viral load count and no `result`,
        // so a sweep reading `result` alone would call it empty and strip a
        // finished sample's status and approval.
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_hepatitis
                (vlsm_instance_id, sample_code, facility_id, lab_id, result_status,
                 hbv_vl_count, sample_received_at_lab_datetime, sample_tested_datetime,
                 result_approved_datetime)
             VALUES ('test', 'HEP-RESULTED', " . self::FACILITY_ID . ", " . self::LAB_ID . ", 7,
                     '1200', '2026-03-12 09:00:00', '2026-03-12 09:00:00', '2026-03-13 09:00:00')"
        );

        /** @var SampleStatusRepairService $service */
        $service = ContainerRegistry::get(SampleStatusRepairService::class);
        $result = $service->repairAcceptedWithoutResult('hepatitis', null, null, 200, 0);

        self::assertSame(0, $result['repaired'], 'a counted result is still a result');

        $row = (array) LegacyAppHarness::db()->rawQueryOne(
            "SELECT * FROM form_hepatitis WHERE sample_code = 'HEP-RESULTED'"
        );
        self::assertSame(7, (int) $row['result_status']);
        self::assertSame('2026-03-13 09:00:00', $row['result_approved_datetime']);
        self::assertSame('2026-03-12 09:00:00', $row['sample_tested_datetime']);
    }

    #[RunInSeparateProcess]
    public function testASweepThatFindsNothingLeavesTheTableUntouched(): void
    {
        $this->sweep();
        $second = $this->sweep();

        self::assertSame(0, $second['repaired'], 'the repair drains its own working set and then stops');
    }
}
