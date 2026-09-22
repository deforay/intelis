<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\STS\RequestsService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;
use Throwable;

/**
 * requests.php for labs that ask for receipts: the usual window plus the requests
 * still waiting for the lab, marked in flight. Labs that do not ask get exactly
 * the window they always have.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class RequestSyncPendingTest extends TestCase
{
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
        require_once ROOT_PATH . '/app/system/version.php';
        $db = LegacyAppHarness::boot('intelis_request_pending_test_' . getmypid(), [
            'r_sample_status', 'form_vl', 'facility_details', 'testing_lab_health_facilities_map',
            'track_api_requests', 'global_config', 'system_config', 's_vlsm_instance',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Registered')");
        $db->insert('global_config', [
            'display_name' => 'Sync interval', 'name' => 'data_sync_interval', 'value' => '30',
        ]);
        $db->insert('facility_details', [
            'facility_id' => 7, 'facility_name' => 'Lab Seven', 'facility_type' => 2, 'status' => 'active',
            'sts_token' => 'lab-seven-token', 'sts_token_expiry' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        if ($withFailuresTable) {
            $migration = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.7.75.sql');
            $pattern = '/^CREATE TABLE IF NOT EXISTS `request_sync_failures` \(.*?^\) ENGINE=[^;]*;/ms';
            preg_match($pattern, $migration, $sql);
            $db->rawQuery($sql[0]);
        }

        $recent = date('Y-m-d H:i:s', strtotime('-1 day'));
        $late = date('Y-m-d H:i:s', strtotime('-45 days'));
        $rows = [
            // In the window: changed yesterday.
            1 => ['u-new', $recent, 0],
            // Behind the window but still waiting: a late API upload with an old updatedOn.
            2 => ['u-late', $late, 0],
            // Behind the window and already confirmed: not sent again.
            3 => ['u-done', $late, 1],
            // Behind the window, waiting, but it failed on the lab ten minutes ago.
            4 => ['u-failed-now', $late, 0],
            // Behind the window, waiting, first failed ten days ago: given up on.
            5 => ['u-failed-long', $late, 0],
        ];
        foreach ($rows as $id => [$uniqueId, $modified, $sync]) {
            $db->insert('form_vl', [
                'vl_sample_id' => $id, 'unique_id' => $uniqueId, 'sample_code' => "S-$id", 'lab_id' => 7,
                'facility_id' => 10, 'data_sync' => $sync, 'last_modified_datetime' => $modified,
                'vlsm_instance_id' => 'sts', 'result_status' => 6,
            ]);
        }
        if ($withFailuresTable) {
            $db->insert('request_sync_failures', [
                'lab_id' => 7, 'test_type' => 'vl', 'unique_id' => 'u-failed-now', 'reason' => 'x',
                'first_failed_datetime' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'last_failed_datetime' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
            ]);
            $db->insert('request_sync_failures', [
                'lab_id' => 7, 'test_type' => 'vl', 'unique_id' => 'u-failed-long', 'reason' => 'x',
                'first_failed_datetime' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'last_failed_datetime' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            ]);
        }
        return $db;
    }

    /** @return list<string> */
    private static function sent(array $requestsData): array
    {
        $ids = array_column($requestsData['requests'], 'unique_id');
        sort($ids);
        return $ids;
    }

    /** @return array<string, int> */
    private static function syncState($db): array
    {
        return array_map(
            'intval',
            array_column(
                $db->rawQuery('SELECT unique_id, data_sync FROM form_vl ORDER BY vl_sample_id'),
                'data_sync',
                'unique_id'
            )
        );
    }

    private static function pull(array $body): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/remote/v2/requests.php')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer lab-seven-token')
            ->withHeader('User-Agent', 'intelis-tests')
            ->withBody((new StreamFactory())->createStream((string) json_encode($body)));
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        try {
            $handler->handle($request);
        } catch (Throwable $e) {
            self::fail('requests.php failed: ' . $e->getMessage());
        }
    }

    #[RunInSeparateProcess]
    public function testALabWithoutReceiptsGetsOnlyTheWindow(): void
    {
        $this->boot();
        $data = ContainerRegistry::get(RequestsService::class)->getRequests('vl', 7, null);

        self::assertSame(['u-new'], self::sent($data));
        self::assertArrayNotHasKey('receiptsEnabled', $data);
    }

    #[RunInSeparateProcess]
    public function testALabWithReceiptsAlsoGetsWhatIsStillWaitingForIt(): void
    {
        $this->boot();
        $data = ContainerRegistry::get(RequestsService::class)->getRequests('vl', 7, null, null, null, true);

        self::assertSame(['u-late', 'u-new'], self::sent($data), 'not the confirmed, the just-failed or the given-up');
        self::assertTrue($data['receiptsEnabled']);
        self::assertSame(0, $data['pendingRemaining']);
    }

    #[RunInSeparateProcess]
    public function testWithoutTheMigrationTheLabGetsThePlainWindow(): void
    {
        $this->boot(withFailuresTable: false);
        $data = ContainerRegistry::get(RequestsService::class)->getRequests('vl', 7, null, null, null, true);

        self::assertSame(['u-new'], self::sent($data));
        self::assertFalse($data['receiptsEnabled'], 'no receipt asked for when pending rows cannot be read');
    }

    #[RunInSeparateProcess]
    public function testPendingRowsAreCappedAndTheRestCounted(): void
    {
        $db = $this->boot();
        $late = date('Y-m-d H:i:s', strtotime('-45 days'));
        $values = [];
        for ($id = 100; $id < 100 + RequestsService::PENDING_LIMIT + 5; $id++) {
            $values[] = "($id, 'u-$id', 'S-$id', 7, 10, 0, '$late', 'sts', 6)";
        }
        $db->rawQuery(
            'INSERT INTO form_vl (vl_sample_id, unique_id, sample_code, lab_id, facility_id, data_sync,
                last_modified_datetime, vlsm_instance_id, result_status) VALUES ' . implode(', ', $values)
        );

        $data = ContainerRegistry::get(RequestsService::class)->getRequests('vl', 7, null, null, null, true);

        // 507 waiting: u-late, the 505, and u-new. The oldest 500 go, and u-new goes
        // with the window. It still counts as remaining, which costs at most one
        // extra pull.
        self::assertCount(RequestsService::PENDING_LIMIT + 1, $data['requests']);
        self::assertSame(7, $data['pendingRemaining']);
    }

    #[RunInSeparateProcess]
    public function testRequestsPhpMarksSentRowsInFlightOnlyForReceiptLabs(): void
    {
        $db = $this->boot();

        self::pull(['labId' => 7, 'testType' => 'vl', 'receipts' => 1]);

        self::assertSame(
            ['u-new' => 2, 'u-late' => 2, 'u-done' => 1, 'u-failed-now' => 0, 'u-failed-long' => 0],
            self::syncState($db)
        );
    }

    #[RunInSeparateProcess]
    public function testRequestsPhpMarksSentRowsSyncedForOtherLabs(): void
    {
        $db = $this->boot();

        self::pull(['labId' => 7, 'testType' => 'vl']);

        self::assertSame(
            ['u-new' => 1, 'u-late' => 0, 'u-done' => 1, 'u-failed-now' => 0, 'u-failed-long' => 0],
            self::syncState($db),
            'exactly as before: the window only, marked 1'
        );
    }
}
