<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\LabRequestSyncService;
use App\Services\RequestPageCursor;
use App\Services\STS\RequestsService;
use Generator;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * Where a page of a paged pull has got to, recorded while the lab saves it.
 *
 * The marker moved only at the end of a page. A page of 500 requests took 12
 * minutes on a slow lab, and an interruption made the next run start the page
 * again. The receiver now records the last saved request every 50.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class RequestPageProgressTest extends TestCase
{
    private const KEY = 'scheduled|vl|2026-10-01';

    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(): mixed
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $database = TestRun::database('intelis_request_page_progress_test');
        // getTableFieldsAsArray() reads columns from SYSTEM_CONFIG's database.
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['vl' => true]]);
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 's_vlsm_instance', 'r_sample_status', 'form_vl',
        ]);
        $this->booted = true;
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Registered')");
        LegacyAppHarness::addMigrationColumns('5.7.82', ['form_vl']);
        LegacyAppHarness::addMigrationColumns('5.7.85', ['form_vl']);
        $migration = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.8.4.sql');
        preg_match('/^CREATE TABLE IF NOT EXISTS `request_pull_state` \(.*?^\) ENGINE=[^;]*;/ms', $migration, $sql);
        $db->rawQuery($sql[0]);
        return $db;
    }

    /**
     * A page of VL requests as the STS sends it: in (last modified, id) order,
     * with the STS's own ids, which the lab does not keep.
     *
     * @return list<array<string, mixed>>
     */
    private static function page(int $count): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'vl_sample_id' => 9000 + $i,
                'unique_id' => "page-progress-$i",
                'remote_sample_code' => "R-$i",
                'remote_sample' => 'yes',
                'lab_id' => 7,
                'facility_id' => 1,
                'vlsm_instance_id' => 'sts-instance',
                'sample_collection_date' => '2026-10-01 09:00:00',
                'result_status' => 6,
                'last_modified_datetime' => sprintf('2026-10-02 %02d:%02d:00', intdiv($i, 60), $i % 60),
                'data_sync' => 0,
            ];
        }
        return $rows;
    }

    private static function body(array $rows): string
    {
        $last = end($rows);
        return (string) json_encode([
            'requests' => $rows,
            'paged' => true,
            'nextAfter' => RequestsService::pageMarker($last['last_modified_datetime'], $last['vl_sample_id']),
        ]);
    }

    /** The rows, then the link drops. */
    private static function breakingOffAfter(array $rows, int $count): Generator
    {
        foreach (array_slice($rows, 0, $count) as $row) {
            yield $row;
        }
        throw new RuntimeException('link dropped');
    }

    #[RunInSeparateProcess]
    public function testAnInterruptedPageResumesAfterTheLastFiftySaved(): void
    {
        $db = $this->boot();
        $rows = self::page(130);
        $cursor = new RequestPageCursor($db);
        $record = $cursor->saveRecorder(self::KEY, self::body($rows), 'vl');
        self::assertNotNull($record);

        try {
            ContainerRegistry::get(LabRequestSyncService::class)->saveModule(
                'vl',
                self::breakingOffAfter($rows, 120),
                'tx-1',
                progress: static fn(int $index, int $saved, array $row) => $record($index, $row)
            );
            self::fail('the pull breaks off');
        } catch (RuntimeException $e) {
            self::assertSame('link dropped', $e->getMessage());
        }

        self::assertSame(120, (int) $db->rawQueryValue('SELECT COUNT(*) FROM form_vl LIMIT 1'));
        // The next run asks for the rows after the 100th, the last marker before the break.
        $expected = RequestsService::pageMarker($rows[99]['last_modified_datetime'], $rows[99]['vl_sample_id']);
        self::assertSame($expected, (new RequestPageCursor($db))->get(self::KEY));
    }

    #[RunInSeparateProcess]
    public function testAPageSavedToItsEndStillMovesToTheNextPage(): void
    {
        $db = $this->boot();
        $rows = self::page(100);
        $body = self::body($rows);
        $cursor = new RequestPageCursor($db);
        $record = $cursor->saveRecorder(self::KEY, $body, 'vl');

        ContainerRegistry::get(LabRequestSyncService::class)->saveModule(
            'vl',
            $rows,
            'tx-1',
            progress: static fn(int $index, int $saved, array $row) => $record($index, $row)
        );

        // The 100th row's marker is also the STS's nextAfter. Compared with the marker
        // the cursor holds, the page would read as stuck and the pull would stop here.
        $nextAfter = json_decode($body, true)['nextAfter'];
        self::assertSame($nextAfter, $cursor->get(self::KEY));
        self::assertFalse($cursor->advance(self::KEY, $nextAfter));
        self::assertTrue($cursor->advanceFrom(self::KEY, '', $nextAfter));
        self::assertFalse($cursor->advanceFrom(self::KEY, $nextAfter, $nextAfter), 'a page that does not move');
    }

    #[RunInSeparateProcess]
    public function testAWholeWindowRecordsNothingInside(): void
    {
        $db = $this->boot();
        $rows = self::page(60);
        $cursor = new RequestPageCursor($db);

        // An older STS: the whole window, by id, with no "paged" next to it.
        self::assertNull($cursor->saveRecorder(self::KEY, (string) json_encode(['requests' => $rows]), 'vl'));
        self::assertNull($cursor->saveRecorder(null, self::body($rows), 'vl'), 'no page key');
        self::assertNull($cursor->get(self::KEY));
    }
}
