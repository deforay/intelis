<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REFERRED;
use const SAMPLE_STATUS\TEST_FAILED;

/**
 * The TB Cascade Report numbers (getTbCascadeReport.php), against a real server.
 *
 * Each fixture is built so the old counting rules give a different answer: a
 * test row with no result counted as tested, "Dispatched / Printed" took the
 * larger of two counts, In Pipeline counted a Rwanda sample awaiting approval
 * twice, the rejected flag was ignored, the test type filter summed a sample
 * once per matching test, and Tests Performed ignored the filters.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class TbCascadeReportTest extends TestCase
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
        $database = TestRun::database('intelis_tb_cascade_test');
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['tb' => true]]);
        }
        $db = LegacyAppHarness::boot($database, [
            'r_sample_status', 'system_config', 'global_config', 'facility_details',
            'form_tb', 'tb_tests', 'tb_referral_history',
        ]);
        LegacyAppHarness::seedDefaults('r_sample_status');
        $db->rawQuery("INSERT INTO facility_details (facility_id, facility_name, facility_type, status)
            VALUES (1, 'Lab One', 2, 'active'), (2, 'Lab Two', 2, 'active')");
        LegacyAppHarness::withSession();
        $this->booted = true;
        $this->seed($db);
        return $db;
    }

    /** @param array<string, mixed> $fields */
    private static function sample(mixed $db, string $code, array $fields, array $tests = []): int
    {
        $db->insert('form_tb', $fields + [
            'sample_code' => $code,
            'lab_id' => 1,
            'facility_id' => 1,
            'sample_collection_date' => '2026-02-01 09:00:00',
            'sample_received_at_lab_datetime' => '2026-02-02 09:00:00',
            'result_status' => RECEIVED_AT_TESTING_LAB,
        ]);
        $tbId = (int) $db->getInsertId();
        foreach ($tests as [$type, $result]) {
            $db->insert('tb_tests', ['tb_id' => $tbId, 'lab_id' => 1, 'test_type' => $type, 'test_result' => $result]);
        }
        return $tbId;
    }

    private function seed(mixed $db): void
    {
        // At the lab, a test card saved with no result yet: not tested.
        self::sample($db, 'S1', [], [['Xpert', null]]);
        // Rwanda: a result awaiting approval sits at status 6 with the flag set.
        self::sample(
            $db,
            'S2',
            ['result' => 'MTB detected', 'is_result_finalized' => 'yes'],
            [['Xpert', 'MTB detected']]
        );
        // Accepted and dispatched; accepted and printed only.
        self::sample($db, 'S3', [
            'result_status' => ACCEPTED, 'result' => 'Negative', 'sample_tested_datetime' => '2026-02-03 09:00:00',
            'result_dispatched_datetime' => '2026-02-04 09:00:00',
        ]);
        self::sample($db, 'S4', [
            'result_status' => ACCEPTED, 'result' => 'Negative', 'sample_tested_datetime' => '2026-02-03 09:00:00',
            'result_printed_datetime' => '2026-02-04 09:00:00',
        ]);
        // Rejected by the flag only.
        self::sample($db, 'S5', ['is_sample_rejected' => 'yes']);
        self::sample($db, 'S6', ['result_status' => TEST_FAILED, 'sample_tested_datetime' => '2026-02-03 09:00:00']);
        self::sample($db, 'S7', ['result_status' => CANCELLED]);
        // Referred to lab 2 after reception, not received there yet.
        $s8 = self::sample($db, 'S8', ['result_status' => REFERRED]);
        $db->insert('tb_referral_history', [
            'tb_id' => $s8, 'from_lab_id' => 1, 'to_lab_id' => 2, 'referred_on_datetime' => '2026-02-05 09:00:00',
        ]);
        // Two microscopy tests on one accepted sample.
        self::sample($db, 'S9', ['result_status' => ACCEPTED, 'result' => 'Positive'], [
            ['Microscopy', 'Positive'], ['Microscopy', 'Positive'],
        ]);
        // Outside the window.
        self::sample($db, 'S10', [
            'sample_collection_date' => '2025-06-01 09:00:00', 'result_status' => ACCEPTED, 'result' => 'Negative',
        ], [['Microscopy', 'Negative']]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function endpoint(array $post): array
    {
        $request = LegacyAppHarness::withPost($post + self::WINDOW, '/tb/management/getTbCascadeReport.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return json_decode((string) $handler->handle($request)->getBody(), true) ?? [];
    }

    #[RunInSeparateProcess]
    public function testFunnelStagesEachStayPartOfThePreviousOne(): void
    {
        $this->boot();
        $s = self::endpoint(['action' => 'summary']);

        self::assertSame(8, $s['total'], 'cancelled and out-of-window samples are left out');
        self::assertSame(8, $s['receivedAtLab']);
        self::assertSame(5, $s['tested'], 'a test row with no result is not a test');
        self::assertSame(4, $s['resultEntered']);
        self::assertSame(3, $s['accepted']);
        self::assertSame(2, $s['dispatchedOrPrinted'], 'dispatched or printed, not the larger count');
    }

    #[RunInSeparateProcess]
    public function testEachSampleIsInOneStage(): void
    {
        $this->boot();
        $s = self::endpoint(['action' => 'summary']);

        self::assertSame(1, $s['atTestingLab']);
        self::assertSame(1, $s['awaitingApproval'], 'Rwanda: status 6 with the flag set');
        self::assertSame(1, $s['referredInTransit']);
        self::assertSame(3, $s['inPipeline'], 'no sample counts twice');
        self::assertSame(1, $s['rejected'], 'the rejected flag counts');
        self::assertSame(1, $s['testFailed']);
        self::assertSame(1, $s['referred']);
        self::assertSame(0, $s['referralReceived'], 'received before the referral is not received there');
    }

    #[RunInSeparateProcess]
    public function testTestTypeFilterCountsASampleOnce(): void
    {
        $this->boot();
        $s = self::endpoint(['action' => 'summary', 'testType' => 'Microscopy']);

        self::assertSame(1, $s['total']);
        self::assertSame(1, $s['accepted'], 'two microscopy tests, one sample');
    }

    #[RunInSeparateProcess]
    public function testTestsPerformedFollowsTheFilters(): void
    {
        $this->boot();
        $rows = self::endpoint(['action' => 'per-lab'])['aaData'] ?? [];

        self::assertCount(2, $rows, 'lab 1 as originator, lab 2 as referred-in');
        $labOne = array_values(array_filter($rows, fn($r) => $r[0] === 'Lab One'))[0];
        self::assertSame(3, $labOne[3], 'tests with a result, in the window only');
    }
}
