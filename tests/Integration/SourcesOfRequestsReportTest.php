<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The Sources of Requests report: its summary by source, its sample list,
 * its source list and its export.
 *
 * LIS and STS rows were stored under two names each ('vlsm'/'lis' and
 * 'vlsts'/'sts'), so a source must match both; "Not Recorded" must find the
 * rows with no source rather than switch the filter off; cancelled requests
 * are neither listed nor counted; and a result counts as returned once it is
 * dispatched or sent back, whichever happened.
 *
 * LegacyRequestHandler requires a page with require_once, so every test runs
 * in its own process. Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run;
 * skipped without them.
 */
final class SourcesOfRequestsReportTest extends TestCase
{
    /** Per process, so two runs sharing a MySQL server never drop each other's fixtures. */
    private static function database(): string
    {
        return 'intelis_sources_of_requests_test_' . getmypid();
    }

    private const LAB_ID = 801;
    private const FACILITY_ID = 802;

    private const CANCELLED = 12;

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
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => self::database()],
                'modules' => ['vl' => true, 'cd4' => true],
            ]);
        }

        $db = LegacyAppHarness::boot(self::database(), [
            'r_sample_status', 'facility_details', 'batch_details', 'roles', 'user_details',
            'form_vl', 'form_cd4', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_type, vlsm_instance_id, status)
             VALUES (" . self::LAB_ID . ", 'Central Lab', 2, 'test', 'active'),
                    (" . self::FACILITY_ID . ", 'Riverside Clinic', 1, 'test', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name, status) VALUES
                (4, 'Rejected', 'active'), (6, 'Sample Registered at Testing Lab', 'active'), (7, 'Accepted', 'active'),
                (12, 'Cancelled', 'active')"
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
        static $sequence = 0;
        $sequence++;

        $row = $columns + [
            'unique_id' => 'uid-' . $sequence,
            'vlsm_instance_id' => 'test',
            'vlsm_country_id' => 1,
            'sample_code' => 'S' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
            'facility_id' => self::FACILITY_ID,
            'lab_id' => self::LAB_ID,
            'result_status' => 6,
            // A second apart, so the list's default order is the order seeded.
            'request_created_datetime' => date('Y-m-d H:i:s', strtotime('-10 days') + $sequence),
            'last_modified_datetime' => date('Y-m-d H:i:s', strtotime('-10 days') + $sequence),
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            $values[] = $value === null ? 'NULL' : "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO $table (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
        );
    }

    private function seedVl(): void
    {
        $tested = date('Y-m-d H:i:s', strtotime('-5 days'));
        $this->seed('form_vl', ['sample_code' => 'LIS-OLD', 'source_of_request' => 'vlsm']);
        $this->seed('form_vl', [
            'sample_code' => 'LIS-NEW', 'source_of_request' => 'lis', 'result_status' => 7, 'result' => '40',
            'sample_received_at_lab_datetime' => $tested, 'sample_tested_datetime' => $tested,
            // Dispatched, never sent back: still returned.
            'result_dispatched_datetime' => $tested,
        ]);
        $this->seed('form_vl', [
            'sample_code' => 'LIS-CANCELLED', 'source_of_request' => 'vlsm', 'result_status' => self::CANCELLED,
            'sample_received_at_lab_datetime' => $tested,
        ]);
        $this->seed('form_vl', ['sample_code' => 'STS-ROW', 'source_of_request' => 'vlsts']);
        $this->seed('form_vl', ['sample_code' => 'API-ROW', 'source_of_request' => 'API']);
        $this->seed('form_vl', ['sample_code' => 'NO-SOURCE', 'source_of_request' => null]);
        $this->seed('form_vl', ['sample_code' => 'BLANK-SOURCE', 'source_of_request' => '']);
    }

    /** @return array<string, mixed> */
    private function drive(array $post): array
    {
        $request = LegacyAppHarness::withPost($post + [
            'testType' => 'vl', 'dateRange' => '', 'labName' => '', 'state' => '', 'district' => '',
            'facilityId' => '', 'originalSourceOfRequest' => '', 'stage' => '', 'sSearch' => '',
            'withSummary' => 'yes', 'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sEcho' => 1,
            'iSortCol_0' => 4, 'sSortDir_0' => 'asc', 'iSortingCols' => 1, 'bSortable_4' => 'true',
        ], '/admin/monitoring/get-samplewise-report.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();
        $json = json_decode($body, true);
        self::assertIsArray($json, "Endpoint did not return JSON: $body");
        return $json;
    }

    /** @return array<string, mixed> */
    private function breakdown(string $view, array $post = []): array
    {
        $request = LegacyAppHarness::withPost($post + [
            'testType' => 'vl', 'dateRange' => '', 'view' => $view,
        ], '/admin/monitoring/get-sources-of-requests-breakdown.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();
        $json = json_decode($body, true);
        self::assertIsArray($json, "Endpoint did not return JSON: $body");
        return $json;
    }

    /** @return list<string> */
    private static function sampleCodes(array $json): array
    {
        return array_map(static fn(array $row): string => (string) $row[0], $json['aaData']);
    }

    /**
     * Source => [requested, received, tested, returned], in the order shown.
     *
     * @return array<string, list<int>>
     */
    private static function counts(array $json): array
    {
        $counts = [];
        foreach ([...$json['summary']['rows'], $json['summary']['total']] as $row) {
            $counts[$row['label']] = [$row['requested'], $row['received'], $row['tested'], $row['returned']];
        }
        return $counts;
    }

    /**
     * The workbook the export hands over, as sheet name => rows.
     *
     * @return array<string, list<list<mixed>>>
     */
    private function export(): array
    {
        $request = LegacyAppHarness::withPost([], '/admin/monitoring/export-samplewise-reports.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $token = (string) $handler->handle($request)->getBody();
        self::assertNotSame('', $token);

        // The token names this run's workbook; a shared-directory search could
        // pick up another run's.
        $file = \App\Utilities\DownloadTokenUtility::resolve(trim($token), $reason);
        self::assertNotNull($file, "the token resolves to the workbook: $reason");
        self::assertFileExists($file);

        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($file);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();
        unlink($file);
        return $sheets;
    }

    #[RunInSeparateProcess]
    public function testLisMatchesBothStoredNamesAndLeavesCancelledOut(): void
    {
        $this->seedVl();

        $json = $this->drive(['originalSourceOfRequest' => 'vlsm']);

        self::assertSame(['LIS-OLD', 'LIS-NEW'], self::sampleCodes($json));
        self::assertCount(9, $json['aaData'][0], 'one cell per column on the page');
        self::assertSame('LIS', $json['aaData'][0][1]);
        self::assertNotSame('', $json['aaData'][1][7], 'a dispatched result shows a return date');
    }

    #[RunInSeparateProcess]
    public function testTheSummaryComparesEverySourceWhateverTheListShows(): void
    {
        $this->seedVl();

        $json = $this->drive(['originalSourceOfRequest' => 'vlsm', 'stage' => 'returned']);

        self::assertSame(['LIS-NEW'], self::sampleCodes($json));
        // The cancelled row is in no count; rows with no source come last.
        $counts = self::counts($json);
        self::assertSame([
            'LIS' => [2, 1, 1, 1],
            'Not Recorded' => [2, 0, 0, 0],
            'Total' => [6, 1, 1, 1],
        ], array_intersect_key($counts, array_flip(['LIS', 'Not Recorded', 'Total'])));
        self::assertSame([1, 0, 0, 0], $counts['STS']);
        self::assertSame([1, 0, 0, 0], $counts['API']);
        self::assertSame(['LIS', 'Not Recorded', 'Total'], [
            array_key_first($counts),
            array_keys($counts)[3],
            array_key_last($counts),
        ]);
    }

    #[RunInSeparateProcess]
    public function testTheSummaryIsLeftOutWhenNotAskedFor(): void
    {
        $this->seedVl();

        $json = $this->drive(['withSummary' => 'no']);

        self::assertArrayNotHasKey('summary', $json);
        self::assertSame(6, $json['iTotalRecords']);
    }

    #[RunInSeparateProcess]
    public function testALabSeesOnlyItsOwnSamplesInTheListAndTheSummary(): void
    {
        $this->seedVl();
        $this->seed('form_vl', ['sample_code' => 'OTHER-LAB', 'source_of_request' => 'vlsts', 'lab_id' => 899]);
        LegacyAppHarness::withSession(['roleId' => 1, 'instance' => ['type' => 'vluser'], 'labId' => self::LAB_ID]);

        $json = $this->drive([]);

        self::assertNotContains('OTHER-LAB', self::sampleCodes($json));
        self::assertSame(6, $json['iTotalRecords']);
        self::assertSame([1, 0, 0, 0], self::counts($json)['STS']);
        self::assertSame(6, self::counts($json)['Total'][0]);
    }

    #[RunInSeparateProcess]
    public function testAllSourcesListsEveryRequestButTheCancelledOne(): void
    {
        $this->seedVl();

        $json = $this->drive([]);

        self::assertSame(6, $json['iTotalRecords']);
        self::assertNotContains('LIS-CANCELLED', self::sampleCodes($json));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function stages(): array
    {
        return [
            'not received' => ['notReceived', ['LIS-OLD', 'STS-ROW', 'API-ROW', 'NO-SOURCE', 'BLANK-SOURCE']],
            'received' => ['received', ['LIS-NEW']],
            'tested' => ['tested', ['LIS-NEW']],
            'returned' => ['returned', ['LIS-NEW']],
            'tested, not returned' => ['notReturned', []],
            // An unknown stage filters nothing.
            'unknown' => ['unknown', ['LIS-OLD', 'LIS-NEW', 'STS-ROW', 'API-ROW', 'NO-SOURCE', 'BLANK-SOURCE']],
        ];
    }

    /** @param list<string> $expected */
    #[RunInSeparateProcess]
    #[DataProvider('stages')]
    public function testEachStageListsTheSamplesItCounts(string $stage, array $expected): void
    {
        $this->seedVl();

        self::assertSame($expected, self::sampleCodes($this->drive(['stage' => $stage])));
    }

    #[RunInSeparateProcess]
    public function testMediansRunFromCollectionAndSkipStepsDatedBeforeIt(): void
    {
        $collected = strtotime('-20 days');
        $at = fn(float $days): string => date('Y-m-d H:i:s', (int) ($collected + $days * 86400));
        foreach ([1, 3] as $days) {
            $this->seed('form_vl', [
                'source_of_request' => 'vlsts', 'sample_collection_date' => $at(0),
                // Entered long after: the request date plays no part.
                'request_created_datetime' => $at(15),
                'sample_received_at_lab_datetime' => $at($days), 'sample_tested_datetime' => $at($days),
                'result_sent_to_source_datetime' => $at($days + 2),
            ]);
        }
        // Received "before" it was collected: a slip, so no receipt time.
        $this->seed('form_vl', [
            'source_of_request' => 'vlsm', 'sample_collection_date' => $at(0),
            'sample_received_at_lab_datetime' => $at(-1),
            'sample_tested_datetime' => $at(4), 'result_dispatched_datetime' => $at(4),
        ]);

        // Returned "before" it was collected: no return time either, here or in the total.
        $this->seed('form_vl', [
            'source_of_request' => 'API', 'sample_collection_date' => $at(0),
            'sample_tested_datetime' => $at(-3), 'result_dispatched_datetime' => $at(-2),
        ]);

        $summary = $this->drive([])['summary'];
        $days = [];
        foreach ([...$summary['rows'], $summary['total']] as $row) {
            $days[$row['label']] = [$row['receiptDays'], $row['returnDays']];
        }

        // STS: receipt after 1 and 3 days, return after 3 and 5; even counts average the middle two.
        // JSON carries 2.0 as 2, hence assertEquals.
        self::assertEquals([2.0, 4.0], $days['STS']);
        self::assertNull($days['LIS'][0], 'received before collection: no time');
        self::assertEquals(4.0, $days['LIS'][1]);
        self::assertSame([null, null], $days['API']);
        self::assertEquals([2.0, 4.0], $days['Total']);
    }

    private function seedOverdue(): void
    {
        $daysAgo = fn(int $days): string => date('Y-m-d H:i:s', strtotime("-$days days"));
        $this->seed('form_vl', [
            'sample_code' => 'LATE', 'source_of_request' => 'vlsts', 'sample_received_at_lab_datetime' => $daysAgo(12),
            'sample_tested_datetime' => $daysAgo(10),
        ]);
        $this->seed('form_vl', [
            'sample_code' => 'RECENT', 'source_of_request' => 'vlsts', 'sample_received_at_lab_datetime' => $daysAgo(3),
            'sample_tested_datetime' => $daysAgo(2),
        ]);
        $this->seed('form_vl', [
            'sample_code' => 'SENT', 'source_of_request' => 'vlsts', 'sample_received_at_lab_datetime' => $daysAgo(12),
            'sample_tested_datetime' => $daysAgo(10), 'result_sent_to_source_datetime' => $daysAgo(9),
        ]);
    }

    #[RunInSeparateProcess]
    public function testAResultNotReturnedAWeekAfterTestingIsOverdue(): void
    {
        $this->seedOverdue();

        $json = $this->drive(['stage' => 'overdue']);

        self::assertSame(['LATE'], self::sampleCodes($json));
        self::assertSame(1, $json['summary']['rows'][0]['overdue']);
        self::assertSame(1, $json['summary']['total']['overdue']);
    }

    private function seedNotArrived(): void
    {
        $daysAgo = fn(int $days): string => date('Y-m-d H:i:s', strtotime("-$days days"));
        $this->seed('form_vl', [
            'sample_code' => 'LOST-STS', 'source_of_request' => 'vlsts', 'request_created_datetime' => $daysAgo(20),
        ]);
        // The API stores a missing flag as NULL: not a rejection.
        $this->seed('form_vl', [
            'sample_code' => 'LOST-API', 'source_of_request' => 'api', 'request_created_datetime' => $daysAgo(15),
            'is_sample_rejected' => null,
        ]);
        // Asked for a few days ago: still on its way.
        $this->seed('form_vl', [
            'sample_code' => 'ON-ITS-WAY', 'source_of_request' => 'vlsts', 'request_created_datetime' => $daysAgo(5),
        ]);
        // Entered by the lab, with the sample in hand.
        $this->seed('form_vl', [
            'sample_code' => 'LAB-ENTERED', 'source_of_request' => 'vlsm', 'request_created_datetime' => $daysAgo(20),
        ]);
        // Tested or rejected: it arrived, receipt recorded or not.
        $this->seed('form_vl', [
            'sample_code' => 'TESTED', 'source_of_request' => 'vlsts', 'request_created_datetime' => $daysAgo(20),
            'sample_tested_datetime' => $daysAgo(10),
        ]);
        $this->seed('form_vl', [
            'sample_code' => 'REJECTED-FLAG', 'source_of_request' => 'vlsts',
            'request_created_datetime' => $daysAgo(20), 'is_sample_rejected' => 'yes',
        ]);
        $this->seed('form_vl', [
            'sample_code' => 'REJECTED-STATUS', 'source_of_request' => 'api',
            'request_created_datetime' => $daysAgo(19), 'result_status' => 4,
        ]);
        $this->seed('form_vl', [
            'sample_code' => 'RECEIVED', 'source_of_request' => 'api', 'request_created_datetime' => $daysAgo(20),
            'sample_received_at_lab_datetime' => $daysAgo(18),
        ]);
    }

    #[RunInSeparateProcess]
    public function testAnElectronicRequestWithNoSampleAfterTwoWeeksHasNotArrived(): void
    {
        $this->seedNotArrived();

        $json = $this->drive(['stage' => 'notArrived']);

        self::assertSame(['LOST-STS', 'LOST-API'], self::sampleCodes($json));
        $rows = array_column($json['summary']['rows'], null, 'label');
        self::assertSame([1, true], [$rows['STS']['notArrived'], $rows['STS']['electronic']]);
        self::assertSame(1, $rows['API']['notArrived']);
        self::assertSame([0, false], [$rows['LIS']['notArrived'], $rows['LIS']['electronic']]);
        self::assertSame(2, $json['summary']['total']['notArrived']);

        $clinic = $this->breakdown('clinic')['rows'][0];
        self::assertSame(2, $clinic['notArrived']);
    }

    #[RunInSeparateProcess]
    public function testARejectionCountsByItsFlagOrItsStatus(): void
    {
        $this->seedNotArrived();

        $json = $this->drive(['stage' => 'rejected']);

        self::assertSame(['REJECTED-FLAG', 'REJECTED-STATUS'], self::sampleCodes($json));
        $rows = array_column($json['summary']['rows'], null, 'label');
        self::assertSame([1, 1, 0], [$rows['STS']['rejected'], $rows['API']['rejected'], $rows['LIS']['rejected']]);
        self::assertSame(2, $json['summary']['total']['rejected']);

        $clinics = $this->breakdown('clinic');
        self::assertSame([2, 2], [$clinics['rows'][0]['rejected'], $clinics['total']['rejected']]);
    }

    #[RunInSeparateProcess]
    public function testTheClinicSummarySplitsEachClinicBySource(): void
    {
        $this->seedVl();
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO facility_details (facility_id, facility_name, facility_type, vlsm_instance_id, status)
             VALUES (803, 'Hilltop Clinic', 1, 'test', 'active')"
        );
        $this->seed('form_vl', ['sample_code' => 'HILL-1', 'source_of_request' => 'vlsts', 'facility_id' => 803]);
        $this->seed('form_vl', ['sample_code' => 'NOWHERE', 'source_of_request' => 'api', 'facility_id' => null]);

        $clinics = $this->breakdown('clinic');

        self::assertSame(['vlsm', 'vlsts', 'api', 'unrecorded'], array_column($clinics['sources'], 'source'));
        $rows = array_column($clinics['rows'], null, 'label');
        self::assertSame(['Riverside Clinic', 'Hilltop Clinic', 'Not Recorded'], array_keys($rows));
        // Riverside: 2 LIS, 1 STS, 1 API and 2 with no source; STS and API are electronic.
        self::assertSame(
            ['vlsm' => 2, 'vlsts' => 1, 'api' => 1, 'unrecorded' => 2],
            $rows['Riverside Clinic']['bySource'] + []
        );
        self::assertSame([6, 2, 1], [
            $rows['Riverside Clinic']['requested'], $rows['Riverside Clinic']['electronic'],
            $rows['Riverside Clinic']['returned'],
        ]);
        self::assertSame(['0', 1, 1], [
            $rows['Not Recorded']['clinicId'], $rows['Not Recorded']['requested'], $rows['Not Recorded']['electronic'],
        ]);
        self::assertSame([8, 4], [$clinics['total']['requested'], $clinics['total']['electronic']]);
    }

    #[RunInSeparateProcess]
    public function testAClinicFromTheClinicSummaryNarrowsOnlyTheList(): void
    {
        $this->seedVl();
        $this->seed('form_vl', ['sample_code' => 'NOWHERE', 'source_of_request' => 'api', 'facility_id' => null]);

        $json = $this->drive(['clinicId' => '0']);

        self::assertSame(['NOWHERE'], self::sampleCodes($json));
        self::assertSame(7, $json['summary']['total']['requested'], 'the summary still covers every clinic');
    }

    #[RunInSeparateProcess]
    public function testTheTrendCountsEachWeekAndKeepsEmptyWeeks(): void
    {
        $monday = strtotime('monday this week', strtotime('-35 days'));
        $at = fn(int $days): string => date('Y-m-d 10:00:00', $monday + $days * 86400);
        $this->seed('form_vl', ['source_of_request' => 'vlsts', 'request_created_datetime' => $at(0)]);
        $this->seed('form_vl', ['source_of_request' => 'vlsts', 'request_created_datetime' => $at(6)]);
        $this->seed('form_vl', ['source_of_request' => 'vlsm', 'request_created_datetime' => $at(15)]);

        $trend = $this->breakdown('trend');

        self::assertSame('week', $trend['unit']);
        self::assertCount(3, $trend['periods'], 'the empty week between stays');
        self::assertSame(
            ['STS' => [2, 0, 0], 'LIS' => [0, 0, 1]],
            array_column($trend['series'], 'data', 'label')
        );
    }

    #[RunInSeparateProcess]
    public function testTheTrendCountsByMonthOverMoreThanHalfAYear(): void
    {
        $this->seed('form_vl', ['source_of_request' => 'vlsts', 'request_created_datetime' => '2025-01-15 10:00:00']);
        $this->seed('form_vl', ['source_of_request' => 'vlsts', 'request_created_datetime' => '2025-08-02 10:00:00']);

        $trend = $this->breakdown('trend');

        self::assertSame('month', $trend['unit']);
        self::assertSame(
            ['2025-01', '2025-02', '2025-03', '2025-04', '2025-05', '2025-06', '2025-07', '2025-08'],
            $trend['periods']
        );
        self::assertSame([1, 0, 0, 0, 0, 0, 0, 1], $trend['series'][0]['data']);
    }

    #[RunInSeparateProcess]
    public function testNotRecordedFindsOnlyRowsWithNoSource(): void
    {
        $this->seedVl();

        $json = $this->drive(['originalSourceOfRequest' => 'unrecorded']);

        self::assertSame(['NO-SOURCE', 'BLANK-SOURCE'], self::sampleCodes($json));
        self::assertSame('Not Recorded', $json['aaData'][0][1]);
    }

    #[RunInSeparateProcess]
    public function testCd4ExportsItsOwnResultColumn(): void
    {
        $this->seed('form_cd4', [
            'sample_code' => 'CD4-ROW', 'source_of_request' => 'vlsm', 'result_status' => 7, 'cd4_result' => '350',
        ]);

        self::assertSame(['CD4-ROW'], self::sampleCodes($this->drive(['testType' => 'cd4'])));
        self::assertSame('350', (string) $this->export()['Samples'][1][13]);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function endpoints(): array
    {
        return [
            'sample list' => ['/admin/monitoring/get-samplewise-report.php', ['sEcho' => 1]],
            'clinic summary' => ['/admin/monitoring/get-sources-of-requests-breakdown.php', ['view' => 'clinic']],
            'trend' => ['/admin/monitoring/get-sources-of-requests-breakdown.php', ['view' => 'trend']],
            'source list' => ['/admin/monitoring/get-source-request-list.php', []],
            'export' => ['/admin/monitoring/export-samplewise-reports.php', []],
        ];
    }

    /** @param array<string, mixed> $post */
    #[RunInSeparateProcess]
    #[DataProvider('endpoints')]
    public function testAUserWithoutTheReportIsRefused(string $endpoint, array $post): void
    {
        LegacyAppHarness::withSession(['roleId' => 2, 'userId' => 'u2', 'privileges' => ['/dashboard/index.php']]);

        $this->expectException(\App\Exceptions\SystemException::class);
        $this->expectExceptionCode(403);
        $request = LegacyAppHarness::withPost($post + ['testType' => 'vl'], $endpoint);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $handler->handle($request);
    }

    #[RunInSeparateProcess]
    public function testAnInactiveTestTypeIsRefused(): void
    {
        $this->expectException(\App\Exceptions\SystemException::class);
        $this->expectExceptionMessage('Inactive or unknown test type: eid');
        $this->drive(['testType' => 'eid']);
    }

    #[RunInSeparateProcess]
    public function testTheSourceListShowsEachSourceOnce(): void
    {
        $this->seedVl();
        $this->seed('form_vl', ['source_of_request' => 'sts']);

        $request = LegacyAppHarness::withPost(['testType' => 'vl'], '/admin/monitoring/get-source-request-list.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $html = (string) $handler->handle($request)->getBody();

        preg_match_all("/<option value='([^']*)'>([^<]*)<\/option>/", $html, $matches, PREG_SET_ORDER);
        $options = array_column($matches, 2, 1);
        self::assertSame(['', 'vlsm', 'vlsts', 'api', 'unrecorded'], array_keys($options));
        self::assertSame(['LIS', 'STS', 'API'], [$options['vlsm'], $options['vlsts'], $options['api']]);
    }

    #[RunInSeparateProcess]
    public function testALabsSourceListLeavesOtherLabsSourcesOut(): void
    {
        $this->seed('form_vl', ['source_of_request' => 'vlsm']);
        $this->seed('form_vl', ['source_of_request' => 'dhis2', 'lab_id' => 899]);
        LegacyAppHarness::withSession(['roleId' => 1, 'instance' => ['type' => 'vluser'], 'labId' => self::LAB_ID]);

        $request = LegacyAppHarness::withPost(['testType' => 'vl'], '/admin/monitoring/get-source-request-list.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $html = (string) $handler->handle($request)->getBody();

        preg_match_all("/<option value='([^']*)'>/", $html, $matches);
        self::assertSame(['', 'vlsm', 'unrecorded'], $matches[1]);
    }

    #[RunInSeparateProcess]
    public function testTheExportMatchesThePage(): void
    {
        $this->seedVl();
        $this->drive(['originalSourceOfRequest' => 'vlsm']);

        $sheets = $this->export();
        self::assertSame(['Summary by Source', 'By Clinic', 'Requests by Week', 'Samples'], array_keys($sheets));

        // The summary covers every source, as on the page.
        $summary = $sheets['Summary by Source'];
        self::assertSame('Source of Request', $summary[0][0]);
        // Requested, received and %, not arrived (blank: the lab entered these),
        // rejected and %, tested and %, returned and %.
        self::assertSame(['LIS', 2, 1, 50, '', 0, 0, 1, 50, 1, 50], array_map(
            static fn($cell) => is_numeric($cell) ? (int) $cell : $cell,
            array_slice($summary[1], 0, 11)
        ));
        self::assertSame(['Total', 6], [end($summary)[0], (int) end($summary)[1]]);

        // The samples follow the list's filters.
        $samples = $sheets['Samples'];
        self::assertCount(3, $samples, 'headings and the two LIS rows');
        self::assertSame(
            ['Source of Request', 'Name of the Clinic', 'Name of the Testing Lab'],
            array_slice($samples[0], 3, 3)
        );
        self::assertSame(['LIS-OLD', 'LIS', 'Riverside Clinic', 'Central Lab'], [
            $samples[1][0], $samples[1][3], $samples[1][4], $samples[1][5],
        ]);
        self::assertSame('40', (string) $samples[2][13]);
    }
}
