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
 * A reference grid asked for its rows without a sort column.
 *
 * The grids counted their filtered rows with "... ORDER BY $sOrder", and $sOrder
 * is empty when DataTables sends no sortable column, so the count query ended in
 * a bare ORDER BY and the grid failed with an SQL error. Twenty-one grids carried
 * the copy; the funding sources grid stands in for them.
 *
 * One drive per test: the handler uses require_once. Set INTELIS_TEST_DB_HOST/
 * _PORT/_USER/_PASS to run; skipped without them.
 */
final class ReferenceGridWithoutSortTest extends TestCase
{
    protected function setUp(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') === '' || (getenv('INTELIS_TEST_DB_USER') ?: '') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        // Named per process: two runs against one MySQL would drop each other's fixtures.
        $db = LegacyAppHarness::boot('intelis_reference_grid_without_sort_' . getmypid(), [
            'r_funding_sources', 'activity_log', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO r_funding_sources (funding_source_id, funding_source_name, funding_source_status)
             VALUES (1, 'Global Fund', 'active'), (2, 'PEPFAR', 'active')"
        );
    }

    protected function tearDown(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') !== '') {
            LegacyAppHarness::shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testTheGridListsItsRowsWhenNoColumnIsSorted(): void
    {
        $request = LegacyAppHarness::withPost(
            ['sEcho' => 1, 'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sSearch' => ''],
            '/common/reference/get-funding-sources-helper.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();

        $json = json_decode($body, true);
        self::assertIsArray($json, "Grid did not return JSON: $body");
        self::assertSame(2, (int) $json['iTotalDisplayRecords']);
        self::assertCount(2, $json['aaData']);
    }
}
