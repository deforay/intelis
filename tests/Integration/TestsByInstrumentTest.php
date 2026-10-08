<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Services\LabPerformanceIndicatorsService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * Tests by lab, instrument and assay on the Instrument Activity page.
 *
 * Programs use these counts to account for reagents by platform, so they must count
 * what the Lab Performance Indicators failure rate counts -- a failed run replaced by a
 * re-test is still a test used -- and must never count one test twice.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class TestsByInstrumentTest extends TestCase
{
    private const DATABASE = 'intelis_tests_by_instrument_test';

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

        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            'r_sample_status', 'system_config', 'global_config', 'facility_details', 'instruments',
            'form_vl', 'test_result_attempts', 'batch_details',
        ]);
        LegacyAppHarness::addMigrationColumns('5.7.82', ['form_vl']);
        LegacyAppHarness::addMigrationColumns('5.7.85', ['form_vl']);

        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name)
             VALUES (5, 'Failed'), (6, 'Received at lab'), (7, 'Accepted'), (8, 'Awaiting approval'), (12, 'Cancelled')"
        );
        $db->rawQuery(
            "INSERT INTO facility_details (facility_id, facility_name, facility_type) VALUES (1, 'Lab One', 2)"
        );
        $db->rawQuery(
            "INSERT INTO instruments
                    (instrument_id, machine_name, import_machine_file_name, max_no_of_samples_in_a_batch)
             VALUES ('inst-a', 'Abbott m2000', 'abbott.php', 96),
                    ('inst-g1', 'GeneXpert', 'genexpert.php', 16),
                    ('inst-g2', 'GeneXpert', '', 16)"
        );
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    private static int $key = 0;

    /** @param array<string, mixed> $columns */
    private function seed(array $columns): int
    {
        self::$key++;
        LegacyAppHarness::db()->insert('form_vl', $columns + [
            'vlsm_instance_id' => 'test',
            'sample_code' => 'VL-' . self::$key,
            'facility_id' => 1,
            'lab_id' => 1,
            'result_status' => 7,
            'sample_tested_datetime' => '2026-09-10 10:00:00',
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /** @param array<string, mixed> $columns */
    private function attempt(int $recordId, array $columns): void
    {
        LegacyAppHarness::db()->insert('test_result_attempts', $columns + [
            'test_type' => 'vl',
            'form_table' => 'form_vl',
            'record_id' => $recordId,
            'lab_id' => 1,
            'facility_id' => 1,
            'result' => 'Failed',
            'result_status' => 5,
            'result_failed' => 1,
            'sample_tested_datetime' => '2026-09-05 10:00:00',
            'test_platform' => 'Abbott m2000',
            'instrument_id' => 'inst-a',
            'attempt_data' => json_encode(['row' => ['assay_name' => 'HIV1.0mlDBS']]),
        ]);
    }

    /** @return array<string, array<string, mixed>> keyed by "instrument|assay" */
    private function rows(LabPerformanceIndicatorsService $service, array $filters): array
    {
        $rows = [];
        foreach ($service->getByInstrument($filters) as $row) {
            $rows[$row['instrument'] . '|' . $row['assay']] = $row;
        }
        return $rows;
    }

    #[RunInSeparateProcess]
    public function testTestsAreCountedOncePerInstrumentAndAssay(): void
    {
        $retested = $this->seed(['instrument_id' => 'inst-a', 'assay_name' => 'HIV1.0mlDBS', 'result' => '40']);
        $this->attempt($retested, ['superseded_by' => 'retest']);
        // A corrected typo replaced the value of the same run: not a second test.
        $this->attempt($retested, ['superseded_by' => 'result-edit', 'attempt_number' => 2]);
        $this->seed([
            'instrument_id' => 'inst-a', 'assay_name' => 'HIV1.0mlDBS', 'result' => 'Failed', 'result_status' => 5,
        ]);
        $this->seed([
            'instrument_id' => 'inst-a', 'assay_name' => 'HIV1.0mlPlasma', 'result' => '50', 'result_status' => 8,
        ]);
        // An older file import stored the machine configuration id; two instruments share
        // the platform's name, and the test must still count once.
        $this->seed(['instrument_id' => '12', 'vl_test_platform' => 'GeneXpert', 'result' => 'Target Not Detected']);
        $this->seed(['result' => '100']);
        // Not counted: cancelled, never tested, tested outside the range.
        $this->seed(['instrument_id' => 'inst-a', 'result' => '60', 'result_status' => 12]);
        $this->seed([
            'instrument_id' => 'inst-a', 'result' => null, 'sample_tested_datetime' => null, 'result_status' => 6,
        ]);
        $this->seed(['instrument_id' => 'inst-a', 'result' => '70', 'sample_tested_datetime' => '2026-07-01 10:00:00']);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $filters = $service->resolveFilters(['testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026']);
        $rows = $this->rows($service, $filters);

        self::assertSame(
            ['Abbott m2000|HIV1.0mlDBS', 'Abbott m2000|HIV1.0mlPlasma', 'GeneXpert|', '|'],
            array_keys($rows)
        );

        $dbs = $rows['Abbott m2000|HIV1.0mlDBS'];
        self::assertSame('Lab One', $dbs['lab']);
        self::assertSame('Abbott', $dbs['instrumentType']);
        self::assertSame(3, $dbs['tested'], 'the failed run a re-test replaced is a test used');
        self::assertSame(2, $dbs['failed']);
        self::assertSame(1, $dbs['valid']);
        self::assertSame(1, $dbs['retested']);

        self::assertSame(1, $rows['Abbott m2000|HIV1.0mlPlasma']['tested'], 'a result awaiting approval was still run');
        self::assertSame(1, $rows['GeneXpert|']['tested'], 'two instruments named alike do not double it');
        self::assertSame('GeneXpert', $rows['GeneXpert|']['instrumentType']);
        self::assertSame('Not recorded', $rows['|']['instrumentType']);

        $failureTotal = array_sum(array_column($service->getFailure($filters), 'tested'));
        self::assertSame(6, array_sum(array_column($rows, 'tested')));
        self::assertSame($failureTotal, array_sum(array_column($rows, 'tested')), 'reconciles with the failure rate');
    }

    #[RunInSeparateProcess]
    public function testColumnsLeftWithAnOlderCollationStillCount(): void
    {
        // Installs upgraded through 5.2.0 keep utf8mb4_general_ci on some form columns,
        // while test_result_attempts and instruments were created as utf8mb4_0900_ai_ci.
        // Combining the two failed with "Illegal mix of collations".
        LegacyAppHarness::db()->rawQuery(
            "ALTER TABLE form_vl
                MODIFY vl_test_platform TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
                MODIFY instrument_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
                MODIFY assay_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
        );
        $retested = $this->seed(['instrument_id' => 'inst-a', 'assay_name' => 'HIV1.0mlDBS', 'result' => '40']);
        $this->attempt($retested, ['superseded_by' => 'retest']);
        $this->seed(['instrument_id' => '12', 'vl_test_platform' => 'GeneXpert', 'result' => '40']);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $rows = $this->rows($service, $service->resolveFilters(['testType' => 'vl']));

        self::assertSame(2, $rows['Abbott m2000|HIV1.0mlDBS']['tested']);
        self::assertSame(1, $rows['GeneXpert|']['tested']);
    }

    #[RunInSeparateProcess]
    public function testAnInstrumentNamedLikeAnotherLabsTakesItsOwnLabsFormat(): void
    {
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO facility_details (facility_id, facility_name, facility_type) VALUES (2, 'Lab Two', 2)"
        );
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO instruments
                    (instrument_id, machine_name, import_machine_file_name, lab_id, max_no_of_samples_in_a_batch)
             VALUES ('lab2-m', 'Main Analyzer', 'roche-rwanda.php', 2, 96),
                    ('lab1-m', 'Main Analyzer', 'abbott.php', 1, 96)"
        );
        // Legacy results: the platform name only.
        $this->seed(['vl_test_platform' => 'Main Analyzer', 'result' => '40', 'lab_id' => 1]);
        $this->seed(['vl_test_platform' => 'Main Analyzer', 'result' => '40', 'lab_id' => 2]);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $types = [];
        foreach ($service->getByInstrument($service->resolveFilters(['testType' => 'vl'])) as $row) {
            $types[$row['lab']] = [$row['instrumentType'], $row['tested']];
        }

        self::assertSame(['Lab One' => ['Abbott', 1], 'Lab Two' => ['Roche', 1]], $types);
    }

    #[RunInSeparateProcess]
    public function testALabSeesOnlyItsOwnTests(): void
    {
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO facility_details (facility_id, facility_name, facility_type) VALUES (2, 'Lab Two', 2)"
        );
        $this->seed(['instrument_id' => 'inst-a', 'assay_name' => 'HIV1.0mlDBS', 'result' => '40']);
        $other = $this->seed([
            'instrument_id' => 'inst-a', 'assay_name' => 'HIV1.0mlDBS', 'result' => '40', 'lab_id' => 2,
        ]);
        $this->attempt($other, ['superseded_by' => 'retest', 'lab_id' => 2]);
        // A LIS session operating as Lab One.
        $_SESSION['instance']['type'] = 'vluser';
        $_SESSION['labId'] = 1;

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $rows = $service->getByInstrument($service->resolveFilters(['testType' => 'vl']));
        self::assertSame(['Lab One'], array_values(array_unique(array_column($rows, 'lab'))));
        self::assertSame(1, (int) array_sum(array_column($rows, 'tested')), 'nor its archived re-test');

        // Asking for the other lab by its id does not reach it either.
        $asked = $service->getByInstrument($service->resolveFilters(['testType' => 'vl', 'labId' => 2]));
        self::assertSame([], $asked);
    }

    #[RunInSeparateProcess]
    public function testOnlyModulesThatRecordTheAssayAreOffered(): void
    {
        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);

        $this->expectException(\App\Exceptions\SystemException::class);
        $service->getByInstrument($service->resolveFilters(['testType' => 'tb']));
    }

    #[RunInSeparateProcess]
    public function testEachSampleCountsOnceWithWhatItCameTo(): void
    {
        LegacyAppHarness::db()->rawQuery("INSERT INTO batch_details (batch_id, batch_code) VALUES (1, 'B-0910')");
        $run = ['instrument_id' => 'inst-a', 'assay_name' => 'HIV1.0mlDBS'];
        $failed = ['result' => 'Failed', 'result_status' => 5];

        $this->seed($run + ['sample_code' => 'S-FIRST', 'result' => '40']);
        $afterRetest = $this->seed($run + ['sample_code' => 'S-AFTER', 'result' => '50']);
        $this->attempt($afterRetest, ['superseded_by' => 'retest', 'sample_code' => 'S-AFTER']);
        $this->seed($run + $failed + ['sample_code' => 'S-FAILED']);
        $twice = $this->seed($run + $failed + ['sample_code' => 'S-TWICE', 'sample_batch_id' => 1]);
        $this->attempt($twice, ['superseded_by' => 'retest', 'sample_code' => 'S-TWICE']);
        // Failed before the range, valid in it.
        $earlier = $this->seed($run + ['sample_code' => 'S-EARLIER', 'result' => '60']);
        $this->attempt($earlier, [
            'superseded_by' => 'retest', 'sample_code' => 'S-EARLIER',
            'sample_tested_datetime' => '2026-08-20 10:00:00',
        ]);
        // Failed in the range, re-tested after it: still failed as far as the range goes.
        $later = $this->seed($run + [
            'sample_code' => 'S-LATER', 'result' => '70', 'sample_tested_datetime' => '2026-10-02 10:00:00',
        ]);
        $this->attempt($later, [
            'superseded_by' => 'retest', 'sample_code' => 'S-LATER', 'batch_id' => 1,
            'sample_tested_datetime' => '2026-09-20 10:00:00',
        ]);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $filters = $service->resolveFilters(['testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026']);
        $row = $this->rows($service, $filters)['Abbott m2000|HIV1.0mlDBS'];

        self::assertSame(8, $row['tested'], 'every run');
        self::assertSame(6, $row['samples'], 'every sample once');
        self::assertSame(1, $row['validFirstTime']);
        self::assertSame(2, $row['validAfterRetest']);
        self::assertSame(3, $row['stillFailed']);

        $samples = $service->getInstrumentSamples($filters, $row, 'still_failed');
        self::assertSame(['S-LATER', 'S-FAILED', 'S-TWICE'], array_column($samples, 'sampleCode'));
        $byCode = array_column($samples, null, 'sampleCode');
        self::assertSame('B-0910', $byCode['S-LATER']['batchCode'], 'the batch of the run shown');
        self::assertSame('B-0910', $byCode['S-TWICE']['batchCode']);
        self::assertSame(2, $byCode['S-TWICE']['runs']);
        self::assertSame(1, $byCode['S-FAILED']['runs']);
        self::assertSame(
            ['S-AFTER', 'S-EARLIER'],
            array_column($service->getInstrumentSamples($filters, $row, 'valid_after_retest'), 'sampleCode')
        );
        self::assertCount(6, $service->getInstrumentSamples($filters, $row, null));
    }

    #[RunInSeparateProcess]
    public function testASampleRetestedOnAnotherInstrumentCountsUnderItsLatestRun(): void
    {
        $sample = $this->seed(['instrument_id' => 'inst-g1', 'result' => '40', 'sample_code' => 'S-MOVED']);
        $this->attempt($sample, ['superseded_by' => 'retest', 'sample_code' => 'S-MOVED']);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $filters = $service->resolveFilters(['testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026']);
        $rows = $this->rows($service, $filters);

        self::assertSame(1, $rows['Abbott m2000|HIV1.0mlDBS']['tested']);
        self::assertSame(0, $rows['Abbott m2000|HIV1.0mlDBS']['samples']);
        self::assertSame(1, $rows['GeneXpert|']['samples']);
        self::assertSame(1, $rows['GeneXpert|']['validAfterRetest']);
        self::assertSame(1, array_sum(array_column($rows, 'samples')), 'one sample in the total');
    }

    /**
     * One request per test: the handler loads the page with require_once, so a second
     * request in the same process runs nothing and answers with an empty body.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    private function sampleList(array $post, array $session): array
    {
        LegacyAppHarness::withSession($session + ['roleId' => 4, 'instance' => ['type' => 'vluser']]);
        $request = LegacyAppHarness::withPost($post + [
            'testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026',
            'instrument' => 'Abbott m2000', 'assay' => 'HIV1.0mlDBS', 'outcome' => 'still_failed',
        ], '/reports/get-instrument-samples.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $reply = json_decode((string) $handler->handle($request)->getBody(), true);
        self::assertIsArray($reply, 'the page answered');
        return $reply;
    }

    private const CAN_OPEN_PAGE = ['privileges' => ['/reports/interface-machine-activity.php' => true]];

    #[RunInSeparateProcess]
    public function testTheSampleListEndpointReturnsARowsSamples(): void
    {
        $assay = ['instrument_id' => 'inst-a', 'assay_name' => 'HIV-1 & HIV-2'];
        $this->seed($assay + ['sample_code' => 'S-OK', 'result' => '40']);
        $this->seed($assay + ['sample_code' => 'S-BAD', 'result' => 'Failed', 'result_status' => 5]);

        $reply = $this->sampleList(['rowLabId' => '1', 'assay' => 'HIV-1 & HIV-2'], self::CAN_OPEN_PAGE);

        self::assertSame(['S-BAD'], array_column($reply['samples'] ?? [], 'sampleCode'), json_encode($reply));
        self::assertFalse($reply['limited']);
        self::assertNotSame('', $reply['samples'][0]['testedOnDisplay']);
    }

    #[RunInSeparateProcess]
    public function testTheSampleListIsOnlyForThoseWhoCanOpenThePage(): void
    {
        $this->seed([
            'instrument_id' => 'inst-a', 'assay_name' => 'HIV1.0mlDBS', 'result' => 'Failed', 'result_status' => 5,
        ]);

        $reply = $this->sampleList(['rowLabId' => '1'], ['privileges' => ['/vl/requests/vl-requests.php' => true]]);

        self::assertArrayNotHasKey('samples', $reply);
        self::assertArrayHasKey('error', $reply);
    }

    /**
     * Failed at Lab One, re-tested valid at Lab Two, plus a failed sample at each.
     * Under the report's All labs the moved sample is a Lab Two sample.
     */
    private function twoLabs(): void
    {
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO facility_details (facility_id, facility_name, facility_type) VALUES (2, 'Lab Two', 2)"
        );
        $run = ['instrument_id' => 'inst-a', 'assay_name' => 'HIV1.0mlDBS'];
        $moved = $this->seed($run + ['sample_code' => 'S-MOVED', 'result' => '40', 'lab_id' => 2]);
        $this->attempt($moved, ['superseded_by' => 'retest', 'sample_code' => 'S-MOVED']);
        $this->seed($run + ['sample_code' => 'S-ONE', 'result' => 'Failed', 'result_status' => 5]);
        $this->seed($run + ['sample_code' => 'S-TWO', 'result' => 'Failed', 'result_status' => 5, 'lab_id' => 2]);
    }

    #[RunInSeparateProcess]
    public function testARowsSampleListMatchesItsCountUnderTheReportFilter(): void
    {
        $this->twoLabs();

        $reply = $this->sampleList(['rowLabId' => '1'], self::CAN_OPEN_PAGE);

        self::assertSame(['S-ONE'], array_column($reply['samples'] ?? [], 'sampleCode'), 'not the moved sample');
    }

    #[RunInSeparateProcess]
    public function testALabCannotListAnotherLabsSamples(): void
    {
        $this->twoLabs();

        $reply = $this->sampleList(['rowLabId' => '2'], self::CAN_OPEN_PAGE + ['labId' => 1]);

        self::assertSame([], $reply['samples'] ?? null, json_encode($reply));
    }

    #[RunInSeparateProcess]
    public function testALabCannotListAnotherLabsSamplesThroughTheLabFilter(): void
    {
        $this->twoLabs();

        $reply = $this->sampleList(['rowLabId' => '2', 'labId' => '2'], self::CAN_OPEN_PAGE + ['labId' => 1]);

        self::assertNotContains('S-TWO', array_column($reply['samples'] ?? [], 'sampleCode'), json_encode($reply));
    }

    #[RunInSeparateProcess]
    public function testOfTwoRunsArchivedAtTheSameTimeTheLaterOneCounts(): void
    {
        // Re-tested in October, after the range, so its latest run in September is
        // the later of two runs archived with the same test time.
        $sample = $this->seed([
            'instrument_id' => 'inst-a', 'result' => '40', 'sample_code' => 'S-TIE',
            'sample_tested_datetime' => '2026-10-02 10:00:00',
        ]);
        $this->attempt($sample, ['superseded_by' => 'retest', 'attempt_number' => 1, 'instrument_id' => 'inst-a']);
        $this->attempt($sample, [
            'superseded_by' => 'retest', 'attempt_number' => 2,
            'instrument_id' => 'inst-g1', 'test_platform' => 'GeneXpert',
        ]);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $rows = $this->rows($service, $service->resolveFilters([
            'testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026',
        ]));

        self::assertSame(1, $rows['GeneXpert|HIV1.0mlDBS']['samples'], json_encode($rows));
        self::assertSame(0, $rows['Abbott m2000|HIV1.0mlDBS']['samples']);
    }

    #[RunInSeparateProcess]
    public function testAFailureAfterTheRunCountedIsNoReTestOfIt(): void
    {
        // Valid in September, then failed in October and archived for a re-test.
        $sample = $this->seed([
            'instrument_id' => 'inst-a', 'result' => 'Failed', 'result_status' => 5, 'sample_code' => 'S-LATE-FAIL',
            'sample_tested_datetime' => '2026-10-20 10:00:00',
        ]);
        $this->attempt($sample, [
            'superseded_by' => 'retest', 'attempt_number' => 1, 'result' => '40', 'result_status' => 7,
            'result_failed' => 0, 'sample_tested_datetime' => '2026-09-10 10:00:00',
        ]);
        $this->attempt($sample, [
            'superseded_by' => 'retest', 'attempt_number' => 2, 'sample_tested_datetime' => '2026-10-05 10:00:00',
        ]);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $rows = $this->rows($service, $service->resolveFilters([
            'testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026',
        ]));

        self::assertSame(1, $rows['Abbott m2000|HIV1.0mlDBS']['validFirstTime'], json_encode($rows));
        self::assertSame(0, $rows['Abbott m2000|HIV1.0mlDBS']['validAfterRetest']);
    }

    #[RunInSeparateProcess]
    public function testTwoMachinesOfOneNameAreToldApartByTheirSerial(): void
    {
        // Set up as one instrument in InteLIS, but two analyzers report.
        $this->seed(['instrument_id' => 'inst-a', 'instrument_model' => 'm2000', 'instrument_serial' => '275020144',
            'result' => '40']);
        $this->seed(['instrument_id' => 'inst-a', 'instrument_model' => 'm2000', 'instrument_serial' => '275020145',
            'result' => '50']);
        // No instrument set up at all: the model the analyzer reported names it, and its make.
        $this->seed(['instrument_model' => 'c5800', 'instrument_serial' => 'c5800.2709', 'result' => '60']);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $rows = $service->getByInstrument($service->resolveFilters([
            'testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026',
        ]));
        $labels = array_column($rows, 'instrumentType', 'instrumentLabel');

        self::assertSame([
            'Abbott m2000 · 275020144' => 'Abbott',
            'Abbott m2000 · 275020145' => 'Abbott',
            'c5800 (Roche) · c5800.2709' => 'Roche',
        ], $labels);
    }

    #[RunInSeparateProcess]
    public function testFailedRunsAreCountedByWhatTheAnalyzerSaid(): void
    {
        $failed = ['instrument_id' => 'inst-a', 'instrument_serial' => '275020144', 'result' => 'Failed',
            'result_status' => 5];
        $message = '4442 : Internal control cycle number is too high.';
        $this->seed($failed + ['analyzer_message' => $message, 'analyzer_run_id' => 'HIV0910A',
            'sample_code' => 'S-IC']);
        $this->seed($failed + ['analyzer_message' => $message]);
        $this->seed($failed);
        // An archived failed run keeps its message in the snapshot of its row.
        $retested = $this->seed(['instrument_id' => 'inst-a', 'instrument_serial' => '275020144', 'result' => '40']);
        $this->attempt($retested, [
            'superseded_by' => 'retest',
            'attempt_data' => json_encode(['row' => ['instrument_serial' => '275020144',
                'analyzer_message' => '3110 : A drive no load error was encountered by the Liquid Handler.']]),
        ]);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $filters = $service->resolveFilters(['testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026']);
        $messages = $service->getFailureMessages($filters);
        $where = ['lab' => 'Lab One', 'instrumentLabel' => 'Abbott m2000 · 275020144'];

        self::assertSame([
            [...$where, 'assay' => '', 'message' => $message, 'failed' => 2],
            [...$where, 'assay' => '', 'message' => '', 'failed' => 1],
            [
                ...$where, 'assay' => '',
                'message' => '3110 : A drive no load error was encountered by the Liquid Handler.', 'failed' => 1,
            ],
        ], $messages);
        $row = $service->getByInstrument($filters)[0];
        self::assertSame(
            array_sum(array_column($messages, 'failed')),
            $row['failed'],
            'they add up to its failed runs'
        );

        $samples = $service->getInstrumentSamples($filters, $row, 'still_failed');
        $sample = array_column($samples, null, 'sampleCode')['S-IC'];
        self::assertSame('HIV0910A', $sample['runId']);
        self::assertSame($message, $sample['message']);
    }

    #[RunInSeparateProcess]
    public function testTheExportCarriesEachMachineAndItsFailureMessages(): void
    {
        $failed = ['instrument_id' => 'inst-a', 'instrument_model' => 'm2000', 'instrument_serial' => '275020144',
            'result' => 'Failed', 'result_status' => 5,
            'analyzer_message' => '4450 : Normalized fluorescence too low.'];
        $this->seed($failed);
        $this->seed(['instrument_id' => 'inst-a', 'instrument_model' => 'm2000', 'instrument_serial' => '275020144',
            'result' => '40']);
        LegacyAppHarness::withSession(self::CAN_OPEN_PAGE + ['roleId' => 4, 'instance' => ['type' => 'vluser']]);

        $request = LegacyAppHarness::withPost([
            'section' => 'tests', 'format' => 'xlsx', 'testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026',
        ], '/reports/export-instrument-activity.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $token = trim((string) $handler->handle($request)->getBody());
        $file = \App\Utilities\DownloadTokenUtility::resolve($token, $reason);
        self::assertNotNull($file, "the token resolves to the workbook: $reason");

        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($file);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[] = $row->toArray();
            }
            $sheets[] = '--';
        }
        $reader->close();
        unlink($file);

        [$heading, $row] = [$sheets[0], $sheets[1]];
        $byHeading = array_combine($heading, $row);
        self::assertSame('m2000', $byHeading['Instrument Model']);
        self::assertSame('275020144', (string) $byHeading['Instrument Serial Number']);
        self::assertSame(2, (int) $byHeading['Samples']);
        self::assertSame(1, (int) $byHeading['Still Failed']);
        $failureSheet = array_slice($sheets, array_search('--', $sheets, true) + 1);
        self::assertSame(
            ['Testing Lab', 'Instrument', 'Assay', 'Analyzer Message', 'Failed or Invalid'],
            $failureSheet[0]
        );
        self::assertSame(
            ['Lab One', 'Abbott m2000 · 275020144', 'Not recorded', '4450 : Normalized fluorescence too low.', 1],
            $failureSheet[1]
        );
    }

    #[RunInSeparateProcess]
    public function testAMachineHasOneLabelInBothTables(): void
    {
        // Set up under a name that says no make; only its valid run reported a model.
        $this->seed(['vl_test_platform' => 'Machine 1', 'instrument_serial' => 'SN1', 'instrument_model' => 'm2000',
            'assay_name' => 'HIV1.0mlDBS', 'result' => '40']);
        $this->seed(['vl_test_platform' => 'Machine 1', 'instrument_serial' => 'SN1', 'assay_name' => 'HIV0.6ml',
            'result' => 'Failed', 'result_status' => 5]);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $filters = $service->resolveFilters(['testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026']);

        self::assertSame(
            ['Machine 1 (Abbott) · SN1'],
            array_values(array_unique(array_column($service->getByInstrument($filters), 'instrumentLabel')))
        );
        self::assertSame(
            ['Machine 1 (Abbott) · SN1'],
            array_column($service->getFailureMessages($filters), 'instrumentLabel')
        );
    }

    #[RunInSeparateProcess]
    public function testASampleListHoldsOnlyItsOwnMachinesSamples(): void
    {
        $run = ['instrument_id' => 'inst-a', 'assay_name' => 'HIV1.0mlDBS', 'result' => 'Failed', 'result_status' => 5];
        $this->seed($run + ['instrument_serial' => 'SN1', 'sample_code' => 'S-ON-ONE']);
        $this->seed($run + ['instrument_serial' => 'SN2', 'sample_code' => 'S-ON-TWO']);

        $service = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $filters = $service->resolveFilters(['testType' => 'vl', 'dateRange' => '01-Sep-2026 to 30-Sep-2026']);
        $rows = array_column($service->getByInstrument($filters), null, 'serial');

        self::assertSame(
            ['S-ON-ONE'],
            array_column($service->getInstrumentSamples($filters, $rows['SN1'], null), 'sampleCode')
        );
        self::assertSame(
            ['S-ON-TWO'],
            array_column($service->getInstrumentSamples($filters, $rows['SN2'], null), 'sampleCode')
        );
    }
}
