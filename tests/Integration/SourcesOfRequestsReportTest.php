<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The Sources of Requests report: its grid, its totals, its source list and
 * its export.
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
                (6, 'Sample Registered at Testing Lab', 'active'), (7, 'Accepted', 'active'),
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
            'request_created_datetime' => date('Y-m-d H:i:s', strtotime('-10 days')),
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
            'facilityId' => '', 'originalSourceOfRequest' => '', 'sSearch' => '',
            'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sEcho' => 1,
            'iSortCol_0' => 13, 'sSortDir_0' => 'asc', 'iSortingCols' => 1, 'bSortable_13' => 'true',
        ], '/admin/monitoring/get-samplewise-report.php');
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

    #[RunInSeparateProcess]
    public function testLisMatchesBothStoredNamesAndLeavesCancelledOut(): void
    {
        $this->seedVl();

        $json = $this->drive(['originalSourceOfRequest' => 'vlsm']);

        self::assertSame(['LIS-OLD', 'LIS-NEW'], self::sampleCodes($json));
        // Requested, received, tested, returned: the cancelled row is in none.
        self::assertSame([[2, 1, 1, 1]], $json['calculation']);
        self::assertCount(14, $json['aaData'][0], 'one cell per column on the page');
        self::assertNotSame('', $json['aaData'][1][12], 'a dispatched result shows a return date');
    }

    #[RunInSeparateProcess]
    public function testAllSourcesListsEveryRequestButTheCancelledOne(): void
    {
        $this->seedVl();

        $json = $this->drive([]);

        self::assertSame(6, $json['iTotalRecords']);
        self::assertNotContains('LIS-CANCELLED', self::sampleCodes($json));
        self::assertSame(6, $json['calculation'][0][0]);
    }

    #[RunInSeparateProcess]
    public function testNotRecordedFindsOnlyRowsWithNoSource(): void
    {
        $this->seedVl();

        $json = $this->drive(['originalSourceOfRequest' => 'unrecorded']);

        self::assertSame(['NO-SOURCE', 'BLANK-SOURCE'], self::sampleCodes($json));
    }

    #[RunInSeparateProcess]
    public function testCd4ShowsItsOwnResultColumn(): void
    {
        $this->seed('form_cd4', [
            'sample_code' => 'CD4-ROW', 'source_of_request' => 'vlsm', 'result_status' => 7, 'cd4_result' => '350',
        ]);

        $json = $this->drive(['testType' => 'cd4']);

        self::assertSame(['CD4-ROW'], self::sampleCodes($json));
        self::assertSame('350', $json['aaData'][0][9]);
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
        self::assertSame(['LIS', 'STS'], [$options['vlsm'], $options['vlsts']]);
    }

    #[RunInSeparateProcess]
    public function testTheExportMatchesThePage(): void
    {
        $this->seedVl();
        $this->drive(['originalSourceOfRequest' => 'vlsm']);

        $request = LegacyAppHarness::withPost([], '/admin/monitoring/export-samplewise-reports.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $token = (string) $handler->handle($request)->getBody();
        self::assertNotSame('', $token);

        // The token names this run's workbook; a shared-directory search could
        // pick up another run's.
        $file = \App\Utilities\DownloadTokenUtility::resolve(trim($token), $reason);
        self::assertNotNull($file, "the token resolves to the workbook: $reason");
        self::assertFileExists($file);
        $files = [$file];
        // The reader skips the blank row between the totals and the listing.
        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($files[0]);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        array_map('unlink', $files);

        self::assertSame([2, 1, 1, 1], array_map('intval', array_slice($rows[1], 0, 4)), 'the totals the page shows');
        self::assertSame('Name of the Clinic', $rows[2][3]);
        self::assertSame('Name of the Testing Lab', $rows[2][4]);
        self::assertSame(['LIS-OLD', 'Riverside Clinic', 'Central Lab'], [$rows[3][0], $rows[3][3], $rows[3][4]]);
        self::assertSame('40', (string) $rows[4][9]);
        self::assertCount(5, $rows, 'totals heading and values, headings and the two LIS rows');
    }
}
