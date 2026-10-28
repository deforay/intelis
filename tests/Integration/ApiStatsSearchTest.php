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

/**
 * The API Stats grid searched.
 *
 * The grid appended its search conditions straight after the FROM clause with
 * no WHERE, so any search turned the query into invalid SQL and the grid failed.
 *
 * One drive per test: the handler uses require_once. Set INTELIS_TEST_DB_HOST/
 * _PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiStatsSearchTest extends TestCase
{
    protected function setUp(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') === '' || (getenv('INTELIS_TEST_DB_USER') ?: '') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        $db = LegacyAppHarness::boot(TestRun::database('intelis_api_stats_search'), [
            'track_api_requests', 'activity_log', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO track_api_requests
                (requested_on, number_of_records, request_type, test_type, api_url, data_format)
             VALUES ('2026-10-01 09:00:00', '3', 'requests', 'vl', '/api/v1.1/vl/requests', 'json'),
                    ('2026-10-02 09:00:00', '5', 'results', 'eid', '/api/v1.1/eid/results', 'json')"
        );
    }

    protected function tearDown(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') !== '') {
            LegacyAppHarness::shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testASearchListsOnlyTheMatchingRequests(): void
    {
        $request = LegacyAppHarness::withPost(
            ['sEcho' => 1, 'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sSearch' => 'eid'],
            '/system-admin/api-stats/getApiStatsDetails.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();

        $json = json_decode($body, true);
        self::assertIsArray($json, "Grid did not return JSON: $body");
        self::assertSame(1, (int) $json['iTotalDisplayRecords']);
        self::assertCount(1, $json['aaData']);
        self::assertSame('eid', $json['aaData'][0][3]);
    }

    #[RunInSeparateProcess]
    public function testSortingByTheRequestDateOrdersTheRows(): void
    {
        $request = LegacyAppHarness::withPost(
            [
                'sEcho' => 1, 'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sSearch' => '',
                'iSortCol_0' => 0, 'sSortDir_0' => 'desc', 'iSortingCols' => 1, 'bSortable_0' => 'true',
            ],
            '/system-admin/api-stats/getApiStatsDetails.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $json = json_decode((string) $handler->handle($request)->getBody(), true);

        self::assertSame(['eid', 'vl'], array_column($json['aaData'], 3), 'Newest first');
    }
}
