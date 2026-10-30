<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Utilities\PmtctCascadeUtility;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\CANCELLED;

/**
 * The PMTCT Cascade Report snapshot numbers, shared by the report screen and its
 * export, against a real server.
 *
 * Each fixture is built so the old counting rules give a different answer:
 * children were counted per EID sample, a mother was "high VL" if any result
 * ever was, "before birth" had no time limit, and cancelled VL samples matched.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class PmtctCascadeSummaryTest extends TestCase
{
    private const WINDOW = ['sampleCollectionDate' => '01-Jan-2026 to 30-Jun-2026'];

    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(): mixed
    {
        if (!LegacyAppHarness::configured()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $database = TestRun::database('intelis_pmtct_cascade_test');
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['eid' => true]]);
        }
        $db = LegacyAppHarness::boot($database, ['r_sample_status', 'form_eid', 'form_vl']);
        LegacyAppHarness::seedDefaults('r_sample_status');
        $this->booted = true;
        return $db;
    }

    private static function eid(
        mixed $db,
        string $childId,
        string $motherId,
        string $collected,
        string $result,
        ?string $dob
    ): void {
        $db->insert('form_eid', [
            'child_id' => $childId,
            'mother_id' => $motherId,
            'child_dob' => $dob,
            'sample_collection_date' => $collected,
            'result' => $result,
            'result_status' => ACCEPTED,
        ]);
    }

    private static function vl(
        mixed $db,
        string $artNo,
        string $collected,
        ?string $category,
        string $pregnant = 'no',
        int $status = ACCEPTED
    ): void {
        $db->insert('form_vl', [
            'patient_art_no' => $artNo,
            'sample_collection_date' => $collected,
            'vl_result_category' => $category,
            'is_patient_pregnant' => $pregnant,
            'is_patient_breastfeeding' => 'no',
            'result_status' => $status,
        ]);
    }

    #[RunInSeparateProcess]
    public function testSnapshotCountsChildrenOnceAndUsesTheMothersLatestResult(): void
    {
        $db = $this->boot();

        // M1: high years ago, suppressed since. Child tested twice, negative.
        // The IDs carry stray whitespace on both sides, as real data does.
        self::vl($db, 'M1 ', '2024-01-10', 'not suppressed');
        self::vl($db, 'M1 ', '2025-12-01', 'suppressed');
        self::eid($db, 'C1', ' M1', '2026-02-01', 'negative', '2025-11-01');
        self::eid($db, 'C1', ' M1', '2026-04-01', 'negative', '2025-11-01');

        // M2: latest result high and pregnant; a newer test has no result yet.
        // The high result falls in the 280 days before C2 was born.
        self::vl($db, 'M2', '2025-03-01', 'suppressed');
        self::vl($db, 'M2', '2025-10-01', 'not suppressed', 'yes');
        self::vl($db, 'M2', '2026-02-01', null);
        self::eid($db, 'C2', 'M2', '2026-02-15', 'positive', '2026-01-15');
        self::eid($db, 'C2', 'M2', '2026-03-15', 'positive', '2026-01-15');

        // M3: high six years before C3 was born, so not during the pregnancy.
        self::vl($db, 'M3', '2020-01-01', 'not suppressed');
        self::vl($db, 'M3', '2025-12-01', 'suppressed');
        self::eid($db, 'C3', 'M3', '2026-02-10', 'positive', '2026-01-10');

        // M4: only a cancelled VL sample, so C4 is not matched.
        self::vl($db, 'M4', '2025-12-01', 'not suppressed', 'no', CANCELLED);
        self::eid($db, 'C4', 'M4', '2026-02-10', 'negative', '2026-01-10');

        // M5: no VL at all.
        self::eid($db, 'C5', 'M5', '2026-02-10', 'negative', '2026-01-10');

        // M6: latest high; positive child with no date of birth.
        self::vl($db, 'M6', '2025-12-01', 'not suppressed');
        self::eid($db, 'C6', 'M6', '2026-02-10', 'positive', null);

        // Outside the window: not counted.
        self::eid($db, 'C7', 'M2', '2025-06-01', 'positive', '2025-05-01');

        $s = PmtctCascadeUtility::summary($db, self::WINDOW);

        self::assertSame(6, $s['totalChildrenWithMotherId'], 'children, not EID samples');
        self::assertSame(4, $s['matchedChildren']);
        self::assertSame(2, $s['unmatchedChildren'], 'no VL, or only a cancelled one');
        self::assertSame(4, $s['testedChildren']);
        self::assertSame(3, $s['positiveChildren'], 'children, not EID samples');
        self::assertSame(1, $s['positiveChildrenNoDob']);
        self::assertSame(1, $s['preBirthHighVlChildren'], 'only a high result during the pregnancy');

        self::assertSame(4, $s['distinctMothers'], 'a cancelled VL sample is not a match');
        self::assertSame(8, $s['vlTests']);
        self::assertSame(7, $s['vlTestsWithResult']);
        self::assertSame(4, $s['mothersWithResult']);
        self::assertSame(2, $s['mothersHighVl'], 'latest result, not any result');
        self::assertSame(2, $s['mothersSuppressedAfterHighVl']);
        self::assertSame(1, $s['mothersHighVlPregnant'], 'as recorded on the latest result');
        self::assertSame(0, $s['mothersHighVlBreastfeeding']);
    }

    public function testHighVlCountsBeforeBirthOnlyInsideThePregnancyWindow(): void
    {
        // 2026-01-15 minus 280 days is 2025-04-10.
        self::assertSame('yes', PmtctCascadeUtility::preBirthFlag('2026-01-15', ['2025-04-10']));
        self::assertSame('yes', PmtctCascadeUtility::preBirthFlag('2026-01-15', ['2020-01-01', '2026-01-14']));
        self::assertSame('no', PmtctCascadeUtility::preBirthFlag('2026-01-15', ['2025-04-09']));
        self::assertSame('no', PmtctCascadeUtility::preBirthFlag('2026-01-15', ['2026-01-15']), 'on the birth date');
        self::assertSame('no', PmtctCascadeUtility::preBirthFlag('2026-01-15', []));
        self::assertSame('unknown', PmtctCascadeUtility::preBirthFlag('', ['2025-12-01']));
    }
}
