<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The API sync history list.
 *
 * Bodies are deleted after 30 days but the rows are kept for a year, so "Show
 * Params" on an older row opened an empty popup. Such a row now shows a disabled
 * "Body expired" button instead. The list can also be filtered by transaction ID,
 * matched whole so the index on transaction_id is used.
 *
 * One drive per test: the handler uses require_once. Set INTELIS_TEST_DB_HOST/
 * _PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiSyncHistoryListTest extends TestCase
{
    protected function setUp(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') === '' || (getenv('INTELIS_TEST_DB_USER') ?: '') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        // Named per process: two runs against one MySQL would drop each other's fixtures.
        $db = LegacyAppHarness::boot('intelis_api_sync_history_list_' . getmypid(), [
            'track_api_requests', 'activity_log', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO track_api_requests
                (transaction_id, requested_on, number_of_records, request_type, test_type, api_url)
             VALUES ('recent', NOW() - INTERVAL 1 DAY, '3', 'requests', 'vl', '/api/v1.1/vl/requests'),
                    ('old', NOW() - INTERVAL 60 DAY, '5', 'results', 'eid', '/api/v1.1/eid/results')"
        );
    }

    protected function tearDown(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') !== '') {
            LegacyAppHarness::shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testATransactionIdListsOnlyThatCall(): void
    {
        $request = LegacyAppHarness::withPost(
            ['sEcho' => 1, 'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sSearch' => '', 'transactionId' => ' old '],
            '/admin/monitoring/get-api-sync-history-list.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();

        $json = json_decode($body, true);
        self::assertIsArray($json, "List did not return JSON: $body");
        self::assertSame(1, (int) $json['iTotalDisplayRecords']);
        self::assertSame(['old'], array_column($json['aaData'], 0));
    }

    #[RunInSeparateProcess]
    public function testAPartialTransactionIdListsNothing(): void
    {
        $request = LegacyAppHarness::withPost(
            ['sEcho' => 1, 'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sSearch' => '', 'transactionId' => 'ol'],
            '/admin/monitoring/get-api-sync-history-list.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();

        $json = json_decode($body, true);
        self::assertIsArray($json, "List did not return JSON: $body");
        self::assertSame(0, (int) $json['iTotalDisplayRecords']);
        self::assertSame([], $json['aaData']);
    }

    #[RunInSeparateProcess]
    public function testAnOldRowShowsBodyExpiredAndARecentRowKeepsShowParams(): void
    {
        $request = LegacyAppHarness::withPost(
            ['sEcho' => 1, 'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sSearch' => ''],
            '/admin/monitoring/get-api-sync-history-list.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();

        $json = json_decode($body, true);
        self::assertIsArray($json, "List did not return JSON: $body");
        $actions = array_column($json['aaData'], 6, 0);

        self::assertStringContainsString('Show Params', $actions['recent']);
        self::assertStringContainsString('show-params.php', $actions['recent']);

        self::assertStringContainsString('Body expired', $actions['old']);
        self::assertStringContainsString('disabled', $actions['old']);
        self::assertStringNotContainsString('show-params.php', $actions['old']);
    }
}
