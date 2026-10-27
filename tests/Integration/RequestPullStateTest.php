<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\RequestPageCursor;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * Where a lab's request pulls have got to, kept in request_pull_state (5.8.4).
 *
 * The page markers were kept in a file under var/cache, which a cache purge empties,
 * and the last pull was read from track_api_requests: 102 seconds per module on one
 * lab. A lab updated in the middle of a paged window keeps its place: the file's
 * markers move into the table.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class RequestPullStateTest extends TestCase
{
    private bool $booted = false;

    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/request-pages-' . getmypid() . '-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(bool $withTable = true): mixed
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot('intelis_request_pull_state_test_' . getmypid(), [
            'track_api_requests', 'system_config', 'global_config',
        ]);
        $this->booted = true;
        $migration = (string) file_get_contents(ROOT_PATH . '/sys/migrations/5.8.4.sql');
        if ($withTable) {
            preg_match('/^CREATE TABLE IF NOT EXISTS `request_pull_state` \(.*?^\) ENGINE=[^;]*;/ms', $migration, $sql);
            $db->rawQuery($sql[0]);
        }
        preg_match('/^ALTER TABLE `track_api_requests` ADD INDEX[^;]*;/m', $migration, $index);
        $db->rawQuery($index[0]);
        return $db;
    }

    private static function rows($db): array
    {
        $rows = $db->rawQuery('SELECT state_key, state_value FROM request_pull_state ORDER BY state_key');
        return array_column($rows ?: [], 'state_value', 'state_key');
    }

    #[RunInSeparateProcess]
    public function testTheMarkersAndTheLastPullAreThereForTheNextRun(): void
    {
        $db = $this->boot();
        $key = RequestPageCursor::scheduledKey('vl', '2026-10-26');
        $cursor = new RequestPageCursor($db);
        $cursor->begin($key);
        $cursor->advance($key, '2026-10-27 05:00:00|500');
        $cursor->recordPull('vl', '2026-10-27 18:00:00');

        $nextRun = new RequestPageCursor($db);

        self::assertSame('2026-10-26', $nextRun->heldStart('vl'));
        self::assertSame('2026-10-27 05:00:00|500', $nextRun->get($key));
        self::assertSame('2026-10-27 18:00:00', $nextRun->lastPull('vl'));
        self::assertNull($nextRun->lastPull('eid'), 'a module with no pull of its own reads the log');
    }

    #[RunInSeparateProcess]
    public function testAFinishedWindowLeavesNoRow(): void
    {
        $db = $this->boot();
        $key = RequestPageCursor::scheduledKey('vl', '2026-10-26');
        $cursor = new RequestPageCursor($db);
        $cursor->advance($key, '2026-10-27 05:00:00|500');

        $cursor->finish($key);

        self::assertSame([], self::rows($db));
        self::assertNull((new RequestPageCursor($db))->heldStart('vl'));
    }

    /**
     * A lab updated while a window is half paged: its marker was in the file. The
     * window start it holds must survive, or the rows after its last page are skipped.
     */
    #[RunInSeparateProcess]
    public function testTheMarkersInTheOldFileMoveIntoTheTable(): void
    {
        $db = $this->boot();
        $key = RequestPageCursor::scheduledKey('vl', '2026-10-26');
        file_put_contents($this->file, json_encode([$key => '2026-10-27 05:00:00|500']));

        $cursor = new RequestPageCursor($db, $this->file);

        self::assertSame('2026-10-26', $cursor->heldStart('vl'));
        self::assertSame([$key => '2026-10-27 05:00:00|500'], self::rows($db));
        self::assertFileDoesNotExist($this->file, 'moved, so a later purge or edit of it changes nothing');
        self::assertSame('2026-10-27 05:00:00|500', (new RequestPageCursor($db))->get($key));
    }

    #[RunInSeparateProcess]
    public function testTheOldFileStaysUntilItsMarkersAreInTheTable(): void
    {
        $db = $this->boot();
        // The table refuses the marker, as a full disk or a broken table would.
        $db->rawQuery(
            "ALTER TABLE request_pull_state ADD CONSTRAINT no_scheduled CHECK (state_key NOT LIKE 'scheduled|%')"
        );
        $key = RequestPageCursor::scheduledKey('vl', '2026-10-26');
        file_put_contents($this->file, json_encode([$key => '2026-10-27 05:00:00|500']));

        $cursor = new RequestPageCursor($db, $this->file);

        self::assertSame('2026-10-26', $cursor->heldStart('vl'), 'this run still holds the start');
        self::assertFileExists($this->file, 'else the next run loses the start its window holds');
        self::assertSame('2026-10-26', (new RequestPageCursor($db, $this->file))->heldStart('vl'));
    }

    #[RunInSeparateProcess]
    public function testTheTableWinsOverAnOlderFile(): void
    {
        $db = $this->boot();
        $key = RequestPageCursor::scheduledKey('vl', '2026-10-26');
        (new RequestPageCursor($db))->advance($key, '2026-10-27 09:00:00|900');
        file_put_contents($this->file, json_encode([$key => '2026-10-27 05:00:00|500']));

        $cursor = new RequestPageCursor($db, $this->file);

        self::assertSame('2026-10-27 09:00:00|900', $cursor->get($key));
        self::assertSame([$key => '2026-10-27 09:00:00|900'], self::rows($db));
    }

    /** Code updated, migration not run yet: the run in between keeps using the file. */
    #[RunInSeparateProcess]
    public function testWithoutTheTableTheFileIsUsedAsBefore(): void
    {
        $db = $this->boot(withTable: false);
        $key = RequestPageCursor::scheduledKey('vl', '2026-10-26');

        $cursor = new RequestPageCursor($db, $this->file);
        $cursor->advance($key, '2026-10-27 05:00:00|500');

        $kept = json_decode((string) file_get_contents($this->file), true);
        self::assertSame([$key => '2026-10-27 05:00:00|500'], $kept);
        self::assertSame('2026-10-26', (new RequestPageCursor($db, $this->file))->heldStart('vl'));
    }

    #[RunInSeparateProcess]
    public function testADryRunKeepsNothing(): void
    {
        $db = $this->boot();
        $cursor = new RequestPageCursor();

        $cursor->advance(RequestPageCursor::scheduledKey('vl', '2026-10-26'), '2026-10-27 05:00:00|500');
        $cursor->recordPull('vl', '2026-10-27 18:00:00');

        self::assertSame([], self::rows($db));
    }

    /**
     * The fallback for a lab with no pull of its own yet: the newest call that moved
     * data, the same answer MAX() gave, read newest first along the 5.8.4 index.
     */
    #[RunInSeparateProcess]
    public function testTheLoggedLastPullIsTheNewestCallThatMovedData(): void
    {
        $db = $this->boot();
        $calls = [
            ['receive-requests', 'vl', '2026-10-27 10:00:00', null, 5],
            ['receive-requests', 'vl', '2026-10-27 11:00:00', '{}', null],
            // Moved nothing: its bodies were not kept and it counted nothing.
            ['receive-requests', 'vl', '2026-10-27 12:00:00', null, null],
            ['receive-requests', 'eid', '2026-10-27 13:00:00', null, 3],
            ['send-results', 'vl', '2026-10-27 14:00:00', null, 4],
            ['receive-requests', 'vl', null, null, 7],
        ];
        foreach ($calls as [$type, $testType, $on, $response, $records]) {
            $db->insert('track_api_requests', [
                'request_type' => $type, 'test_type' => $testType, 'requested_on' => $on,
                'response_data' => $response, 'number_of_records' => $records,
            ]);
        }
        $general = ContainerRegistry::get(CommonService::class);

        self::assertSame('2026-10-27 11:00:00', $general->getLastApiSyncByTypeAndModule('receive-requests', 'vl'));
        self::assertSame('2026-10-27 13:00:00', $general->getLastApiSyncByTypeAndModule('receive-requests', 'eid'));
        self::assertNull($general->getLastApiSyncByTypeAndModule('receive-requests', 'tb'));
        $old = $db->rawQueryOne(
            "SELECT MAX(requested_on) AS d FROM track_api_requests WHERE request_type = 'receive-requests'
                AND test_type = 'vl' AND (response_data IS NOT NULL OR number_of_records IS NOT NULL)"
        );
        self::assertSame($old['d'], $general->getLastApiSyncByTypeAndModule('receive-requests', 'vl'), 'as before');
    }
}
