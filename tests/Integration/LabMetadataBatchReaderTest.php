<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\LabMetadataBatchReader;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * What the lab sends the STS of its sample storage history on each metadata sync.
 *
 * The history used to go whole on a lab's first sync and on every forced one, all of
 * it held in memory at once. It now goes past a watermark on the lab's own history_id,
 * oldest first, a capped batch per run; 5.7.87 starts each lab where its last sync
 * left it.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class LabMetadataBatchReaderTest extends TestCase
{
    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        LegacyAppHarness::boot(TestRun::database('intelis_lab_metadata_batch_test'), [
            'system_config', 'global_config', 's_vlsm_instance', 'lab_storage_history',
        ]);
        $this->booted = true;
        LegacyAppHarness::addMigrationColumns('5.7.87', ['s_vlsm_instance']);
    }

    /** History rows with the given ids, each stored at the given time. */
    private static function history(array $storedAtById): void
    {
        foreach ($storedAtById as $id => $storedAt) {
            LegacyAppHarness::db()->insert('lab_storage_history', [
                'history_id' => $id, 'test_type' => 'vl', 'sample_unique_id' => "sample-$id",
                'volume' => 1, 'freezer_id' => 'F1', 'rack' => 1, 'box' => 1, 'position' => $id,
                'sample_status' => 'Stored', 'updated_datetime' => $storedAt, 'updated_by' => 'u1',
            ]);
        }
    }

    private static function next(mixed $sentUpTo, int $limit = 100, bool $fromStart = false): array
    {
        $rows = ContainerRegistry::get(LabMetadataBatchReader::class)
            ->next('lab_storage_history', 'history_id', $sentUpTo, $limit, $fromStart);
        return array_map(static fn(array $row): int => (int) $row['history_id'], $rows);
    }

    /** The watermark 5.7.87 sets, read back on its own. */
    private static function seedWatermark(?string $lastMetadataSync): ?int
    {
        $db = LegacyAppHarness::db();
        $db->insert('s_vlsm_instance', [
            'vlsm_instance_id' => 'lab-instance', 'last_lab_metadata_sync' => $lastMetadataSync,
        ]);
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . '/sys/migrations/5.7.87.sql');
        if (preg_match('/^UPDATE `s_vlsm_instance` i\b.*?;$/ms', $migration, $match) !== 1) {
            throw new RuntimeException('sys/migrations/5.7.87.sql no longer seeds the watermark');
        }
        $db->rawQuery($match[0]);
        $value = $db->rawQueryOne('SELECT last_lab_storage_history_id AS v FROM s_vlsm_instance')['v'] ?? null;
        return $value === null ? null : (int) $value;
    }

    #[RunInSeparateProcess]
    public function testABacklogGoesACappedBatchAtATimeAndTheNextRunCarriesOn(): void
    {
        $this->boot();
        self::history([1 => '2026-01-01', 2 => '2026-01-02', 3 => '2026-01-03', 4 => '2026-01-04', 5 => '2026-01-05']);

        $this->assertSame([1, 2], self::next(null, 2));
        $this->assertSame([3, 4], self::next(2, 2));
        $this->assertSame([5], self::next(4, 2));
        $this->assertSame([], self::next(5, 2));
    }

    #[RunInSeparateProcess]
    public function testRowsGoInTheOrderTheLabWroteThemWhateverTheirTimes(): void
    {
        $this->boot();
        // A clock set back between two storings: the id still orders them.
        self::history([7 => '2026-03-01', 8 => '2025-12-31', 9 => '2026-03-02']);

        $this->assertSame([7, 8, 9], self::next(null));
        $this->assertSame([8, 9], self::next(7));
    }

    #[RunInSeparateProcess]
    public function testAForcedSyncStartsOverFromTheFirstRowButStaysCapped(): void
    {
        $this->boot();
        self::history([1 => '2026-01-01', 2 => '2026-01-02', 3 => '2026-01-03']);

        $this->assertSame([1, 2], self::next(3, 2, fromStart: true));
    }

    #[RunInSeparateProcess]
    public function testTheUpgradeStartsALabWhereItsLastSyncLeftIt(): void
    {
        $this->boot();
        self::history([1 => '2026-01-01 08:00:00', 2 => '2026-01-02 09:00:00', 3 => '2026-01-03 10:00:00']);

        $watermark = self::seedWatermark('2026-01-02 09:00:00');

        $this->assertSame(2, $watermark);
        $this->assertSame([3], self::next($watermark));
    }

    #[RunInSeparateProcess]
    public function testTheUpgradeNeverSkipsARowTheLastSyncDidNotSend(): void
    {
        $this->boot();
        // A clock set back: row 7 came after the last sync, row 8 carries an earlier
        // time. The newest row sent is 8, but 7 was never sent.
        self::history([6 => '2026-01-10', 7 => '2026-03-01', 8 => '2025-12-31', 9 => '2026-03-02']);

        $watermark = self::seedWatermark('2026-01-15 00:00:00');

        $this->assertSame(6, $watermark);
        $this->assertSame([7, 8, 9], self::next($watermark));
    }

    #[RunInSeparateProcess]
    public function testALabWithNothingUnsentStartsAfterItsNewestRow(): void
    {
        $this->boot();
        self::history([1 => '2026-01-01', 2 => '2026-01-02']);

        $this->assertSame(2, self::seedWatermark('2026-01-15 00:00:00'));
    }

    #[RunInSeparateProcess]
    public function testALabThatNeverSyncedStartsFromTheFirstRow(): void
    {
        $this->boot();
        self::history([1 => '2026-01-01', 2 => '2026-01-02']);

        $watermark = self::seedWatermark(null);

        $this->assertNull($watermark);
        $this->assertSame([1, 2], self::next($watermark));
    }

    #[RunInSeparateProcess]
    public function testALabWhoseHistoryAllCameAfterItsLastSyncSendsAllOfIt(): void
    {
        $this->boot();
        self::history([1 => '2026-02-01', 2 => '2026-02-02']);

        $watermark = self::seedWatermark('2026-01-15 00:00:00');

        $this->assertSame(0, $watermark);
        $this->assertSame([1, 2], self::next($watermark));
    }
}
