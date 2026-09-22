<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\STS\RequestReceiptsService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The STS sync monitor for one lab (admin/monitoring/lab-sync-details.php): the
 * per-facility grid (get-sync-status-details.php) and the requests the lab could
 * not save (get-lab-sync-failures.php).
 *
 * A lab on a release with receipts leaves requests at data_sync = 2 until it says
 * it saved them, and reports the ones it could not in request_sync_failures. A lab
 * on an older release never does either, so both read as nothing to report.
 *
 * One drive per test: the handler uses require_once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class LabSyncMonitorTest extends TestCase
{
    private const LAB = 7;

    private const OTHER_LAB = 8;

    private const FACILITY = 20;

    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(bool $withFailuresTable = true): mixed
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $database = 'intelis_lab_sync_monitor_test_' . getmypid();
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => $database], 'modules' => ['vl' => true, 'generic-tests' => true],
            ]);
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 'r_sample_status', 'roles', 'user_details', 'facility_details',
            'geographical_divisions', 'form_vl', 'form_generic',
        ]);
        $this->booted = true;
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Registered'), (12, 'Cancelled')"
        );
        LegacyAppHarness::withSession(['roleId' => 1]);
        $db->insert('system_config', [
            'display_name' => 'Instance type', 'name' => 'sc_user_type', 'value' => 'remoteuser',
        ]);
        foreach ([self::LAB => 'Lab Seven', self::OTHER_LAB => 'Lab Eight'] as $id => $name) {
            $db->insert('facility_details', [
                'facility_id' => $id, 'facility_name' => $name, 'facility_type' => 2, 'status' => 'active',
            ]);
        }
        $db->insert('facility_details', [
            'facility_id' => self::FACILITY, 'facility_name' => 'Riverside Clinic', 'facility_type' => 1,
            'status' => 'active',
            'facility_attributes' => json_encode([
                'vlRemoteRequestsSync' => '2026-09-01 08:00:00',
                'genericTestsRemoteRequestsSync' => '2026-09-20 10:00:00',
            ]),
        ]);
        if ($withFailuresTable) {
            $migration = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.7.75.sql');
            $pattern = '/^CREATE TABLE IF NOT EXISTS `request_sync_failures` \(.*?^\) ENGINE=[^;]*;/ms';
            preg_match($pattern, $migration, $sql);
            $db->rawQuery($sql[0]);
        }
        return $db;
    }

    private static function request(string $table, string $uniqueId, int $dataSync, array $columns = []): void
    {
        LegacyAppHarness::db()->insert($table, $columns + [
            'unique_id' => $uniqueId, 'remote_sample_code' => "R-$uniqueId", 'remote_sample' => 'yes',
            'lab_id' => self::LAB, 'facility_id' => self::FACILITY, 'vlsm_instance_id' => 'sts-instance',
            'result_status' => 6, 'data_sync' => $dataSync,
            'sample_collection_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'last_modified_datetime' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
    }

    private static function failure(string $uniqueId, string $reason, int $daysAgo = 1, array $columns = []): void
    {
        LegacyAppHarness::db()->insert('request_sync_failures', $columns + [
            'lab_id' => self::LAB, 'test_type' => 'vl', 'unique_id' => $uniqueId, 'reason' => $reason,
            'attempts' => 3,
            'first_failed_datetime' => date('Y-m-d H:i:s', strtotime("-$daysAgo days")),
            'last_failed_datetime' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);
    }

    private static function drive(string $page, array $post): string
    {
        $request = LegacyAppHarness::withPost($post + [
            'labId' => base64_encode((string) self::LAB), 'dateRange' => '',
        ], "/admin/monitoring/$page");
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return (string) $handler->handle($request)->getBody();
    }

    /** @return list<list<string>> the text of each cell, row by row */
    private static function cells(string $html): array
    {
        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/s', $html, $rows);
        return array_map(static function (string $row): array {
            preg_match_all('/<td\b[^>]*>(.*?)<\/td>/s', $row, $cells);
            return array_map(
                static fn(string $cell): string => trim((string) preg_replace('/\s+/', ' ', strip_tags($cell))),
                $cells[1]
            );
        }, $rows[1]);
    }

    #[RunInSeparateProcess]
    public function testAwaitingConfirmationCountsOnlyRequestsStillOutWithTheLab(): void
    {
        $this->boot();
        self::request('form_vl', 'saved-1', 1);
        self::request('form_vl', 'saved-2', 1);
        self::request('form_vl', 'out-1', 2);
        self::request('form_vl', 'out-2', 2);
        self::request('form_vl', 'out-3', 2);
        self::request('form_vl', 'waiting', 0);
        self::request('form_vl', 'out-cancelled', 2, ['result_status' => 12]);
        self::request('form_vl', 'out-other-lab', 2, ['lab_id' => self::OTHER_LAB]);
        self::request('form_vl', 'out-entered-at-lab', 2, ['remote_sample' => 'no']);

        $rows = self::cells(self::drive('get-sync-status-details.php', ['testType' => 'vl']));

        self::assertCount(1, $rows);
        [$facility, , , , $sent, $awaiting] = $rows[0];
        self::assertSame('Riverside Clinic', $facility);
        self::assertSame('2', $sent, 'confirmed requests only');
        self::assertSame('3', $awaiting);
    }

    #[RunInSeparateProcess]
    public function testALabWithoutReceiptsHasNothingAwaiting(): void
    {
        $this->boot();
        // An older lab release: requests go straight to 1 when pulled.
        self::request('form_vl', 'saved-1', 1);
        self::request('form_vl', 'waiting', 0);

        $rows = self::cells(self::drive('get-sync-status-details.php', ['testType' => 'vl']));

        self::assertSame(['1', '0'], [$rows[0][4], $rows[0][5]]);
    }

    #[RunInSeparateProcess]
    public function testCustomTestsReadTheirOwnTableSyncTimesAndRequestsPage(): void
    {
        $this->boot();
        self::request('form_vl', 'vl-1', 1);
        self::request('form_generic', 'ct-1', 1);
        self::request('form_generic', 'ct-2', 2);

        $html = self::drive('get-sync-status-details.php', ['testType' => 'generic-tests']);
        $rows = self::cells($html);

        self::assertCount(1, $rows, $html);
        self::assertSame(['1', '1'], [$rows[0][4], $rows[0][5]]);
        self::assertStringContainsString('20-Sep-2026', $rows[0][7], 'genericTestsRemoteRequestsSync');
        self::assertStringContainsString('data-url="/generic-tests/requests/view-requests.php"', $html);
    }

    #[RunInSeparateProcess]
    public function testATestTypeThatIsNotAModuleReadsViralLoad(): void
    {
        $this->boot();
        self::request('form_vl', 'vl-1', 1);

        $html = self::drive('get-sync-status-details.php', ['testType' => "vl'RemoteResultsSync') OR 1=1 -- "]);
        $rows = self::cells($html);

        self::assertCount(1, $rows, $html);
        self::assertSame('1', $rows[0][4]);
        self::assertStringContainsString('data-url="/vl/requests/vl-requests.php"', $html);
    }

    #[RunInSeparateProcess]
    public function testAFailureShowsWithTheLabsReasonAndIsRetried(): void
    {
        $this->boot();
        self::request('form_vl', 'bad-1', 0);
        self::failure('bad-1', 'Unknown facility code 99');

        $rows = self::cells(self::drive('get-lab-sync-failures.php', ['testType' => 'vl']));

        self::assertCount(1, $rows);
        self::assertSame('R-bad-1', $rows[0][0]);
        self::assertSame('Riverside Clinic', $rows[0][1]);
        self::assertSame('Unknown facility code 99', $rows[0][2]);
        self::assertSame('3', $rows[0][3]);
        self::assertSame('Sent again on the next pull', $rows[0][5]);
    }

    #[RunInSeparateProcess]
    public function testAFailureTheStsGaveUpOnIsListedFirst(): void
    {
        $this->boot();
        self::request('form_vl', 'recent', 0);
        self::request('form_vl', 'stuck', 0);
        self::failure('recent', 'Busy', 1);
        self::failure('stuck', 'Invalid sample type', 10, [
            'last_failed_datetime' => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);

        $rows = self::cells(self::drive('get-lab-sync-failures.php', ['testType' => 'vl']));

        self::assertSame(['R-stuck', 'R-recent'], array_column($rows, 0));
        self::assertStringStartsWith('No longer sent', $rows[0][5]);
        self::assertSame('Sent again on the next pull', $rows[1][5]);
    }

    #[RunInSeparateProcess]
    public function testFailuresTheRequestHasMovedPastAreLeftOut(): void
    {
        $db = $this->boot();
        self::request('form_vl', 'still-failing', 0);
        self::failure('still-failing', 'Still failing');
        // Saved since through a lab release without receipts, which leaves the row behind.
        self::request('form_vl', 'saved-since', 1);
        self::failure('saved-since', 'Old reason');
        self::request('form_vl', 'cancelled', 0, ['result_status' => 12]);
        self::failure('cancelled', 'Old reason');
        self::failure('deleted-on-sts', 'Old reason');
        self::request('form_vl', 'other-lab', 0, ['lab_id' => self::OTHER_LAB]);
        self::failure('other-lab', 'Not this lab', 1, ['lab_id' => self::OTHER_LAB]);
        self::request('form_generic', 'custom', 0);
        self::failure('custom', 'Another module', 1, ['test_type' => 'generic-tests']);
        self::assertSame(6, (int) $db->rawQueryOne('SELECT COUNT(*) AS n FROM request_sync_failures')['n']);

        $rows = self::cells(self::drive('get-lab-sync-failures.php', ['testType' => 'vl']));

        self::assertSame(['R-still-failing'], array_column($rows, 0));
    }

    #[RunInSeparateProcess]
    public function testCustomTestsFailuresAreListedUnderCustomTests(): void
    {
        $this->boot();
        self::request('form_vl', 'vl-bad', 0);
        self::failure('vl-bad', 'A VL failure');
        self::request('form_generic', 'ct-bad', 0);
        self::failure('ct-bad', 'No such test', 1, ['test_type' => 'generic-tests']);

        $rows = self::cells(self::drive('get-lab-sync-failures.php', ['testType' => 'generic-tests']));

        self::assertSame([['R-ct-bad', 'No such test']], array_map(static fn($r) => [$r[0], $r[2]], $rows));
    }

    #[RunInSeparateProcess]
    public function testTheReasonFromTheLabIsNotRunAsHtml(): void
    {
        $this->boot();
        self::request('form_vl', 'bad-1', 0);
        self::failure('bad-1', '<script>alert(1)</script>');

        $html = self::drive('get-lab-sync-failures.php', ['testType' => 'vl']);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    #[RunInSeparateProcess]
    public function testNoFailuresSaysSo(): void
    {
        $this->boot();
        self::request('form_vl', 'saved', 1);

        $rows = self::cells(self::drive('get-lab-sync-failures.php', ['testType' => 'vl']));

        self::assertSame([['The lab has saved every request sent to it']], $rows);
    }

    #[RunInSeparateProcess]
    public function testAnStsWithoutTheFailuresTableShowsNone(): void
    {
        $this->boot(withFailuresTable: false);
        self::request('form_vl', 'bad-1', 0);

        $rows = self::cells(self::drive('get-lab-sync-failures.php', ['testType' => 'vl']));

        self::assertSame([['The lab has saved every request sent to it']], $rows);
    }

    #[RunInSeparateProcess]
    public function testALabIdThatIsNotOneShowsNone(): void
    {
        $this->boot();
        self::request('form_vl', 'bad-1', 0);
        self::failure('bad-1', 'Unknown facility code 99');

        $rows = self::cells(self::drive('get-lab-sync-failures.php', ['testType' => 'vl', 'labId' => 'not-base64!']));

        self::assertSame([['The lab has saved every request sent to it']], $rows);
    }

    #[RunInSeparateProcess]
    public function testTheLabScopeLimitsTheFailures(): void
    {
        $this->boot();
        self::request('form_vl', 'bad-1', 0);
        self::failure('bad-1', 'Unknown facility code 99');
        $receipts = ContainerRegistry::get(RequestReceiptsService::class);

        // What labAdminScopeWhere('lab_id', 'f') gives a cloud-LIS user of another lab.
        self::assertSame([], $receipts->unsaved(self::LAB, 'vl', ' f.lab_id = ' . self::OTHER_LAB . ' '));
        self::assertCount(1, $receipts->unsaved(self::LAB, 'vl', ' f.lab_id = ' . self::LAB . ' '));
        self::assertSame([], $receipts->unsaved(self::LAB, 'not-a-module'));
    }
}
