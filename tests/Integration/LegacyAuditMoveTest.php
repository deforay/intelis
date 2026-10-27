<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AuditArchiveService;
use App\Utilities\MiscUtility;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * A legacy audit_form_* table moved into audit_log, then filed by the drain.
 *
 * A lab's audit_form_vl crashed, and the cron task read it before draining
 * audit_log, so nothing drained for five months. The legacy tables now move into
 * audit_log in batches and are dropped. Rows up to the legacy archive's
 * high-water mark are already in the files and go with the table. The drain files
 * moved rows in time order, before the live history that came after them, and
 * skips one whose second the file already holds.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class LegacyAuditMoveTest extends TestCase
{
    private const DATABASE = 'intelis_legacy_audit_move_test';
    private const MARK = '2025-10-31 08:15:44';

    private string $uniqueId = '';

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), ['r_sample_status', 'form_vl', 'audit_log']);
        LegacyAppHarness::seedDefaults('r_sample_status');
        $this->uniqueId = 'legacy-move-' . bin2hex(random_bytes(6));
        $db->insert('form_vl', [
            'vl_sample_id' => 50, 'unique_id' => $this->uniqueId, 'result' => 'live', 'result_status' => 6,
        ]);

        // The legacy shape: action, revision and time ahead of every form column.
        $db->rawQuery(
            'CREATE TABLE audit_form_vl (action VARCHAR(8), revision INT NOT NULL, dt_datetime DATETIME)'
                . ' ENGINE=MyISAM SELECT * FROM form_vl WHERE 0'
        );
        $db->rawQuery('ALTER TABLE audit_form_vl ADD PRIMARY KEY (vl_sample_id, revision)');
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
        foreach (glob(VAR_PATH . "/audit-trail/vl/{$this->uniqueId}.csv*") ?: [] as $file) {
            MiscUtility::deleteFile($file);
        }
    }

    private function legacy(int $revision, ?string $dt, string $result, ?string $uniqueId = 'own'): void
    {
        LegacyAppHarness::db()->insert('audit_form_vl', [
            'action' => 'update',
            'revision' => $revision,
            'dt_datetime' => $dt,
            'vl_sample_id' => 50,
            'unique_id' => $uniqueId === 'own' ? $this->uniqueId : $uniqueId,
            'result' => $result,
        ]);
    }

    private function live(int $revision, string $dt, string $result): void
    {
        LegacyAppHarness::db()->insert('audit_log', [
            'form_table' => 'form_vl',
            'record_id' => '50',
            'revision' => $revision,
            'action' => 'update',
            'dt_datetime' => $dt,
            'row_data' => json_encode(['vl_sample_id' => 50, 'unique_id' => $this->uniqueId, 'result' => $result]),
        ]);
    }

    private function move(AuditArchiveService $archive): int
    {
        return $archive->moveLegacyTableToAuditLog('audit_form_vl', 'form_vl', 'vl_sample_id', self::MARK);
    }

    /** @return list<array<string, mixed>> */
    private function fileRows(AuditArchiveService $archive): array
    {
        $file = $archive->resolveAuditFilePath('vl', $this->uniqueId);
        self::assertNotNull($file, 'The sample must have an archive file.');
        return $archive->readAuditDataFromCsvFlexible($file);
    }

    private static function tableExists(string $table): bool
    {
        return LegacyAppHarness::db()->rawQueryOne(
            'SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        )['n'] > 0;
    }

    public function testRowsPastTheMarkMoveAndTheTableIsDropped(): void
    {
        $this->legacy(1, '2025-10-01 09:00:00', 'archived already');
        $this->legacy(2, self::MARK, 'at the mark');
        $this->legacy(3, '2025-12-05 10:00:00', 'no unique id', null);
        $this->legacy(4, null, 'no time');

        $moved = $this->move(new AuditArchiveService(LegacyAppHarness::db()));

        self::assertSame(3, $moved, 'Everything from the mark on moves, rows without a time included.');
        self::assertFalse(self::tableExists('audit_form_vl'));
        $rows = LegacyAppHarness::db()->rawQuery(
            "SELECT revision, JSON_UNQUOTE(row_data->'$.unique_id') AS uid, JSON_UNQUOTE(row_data->'$.result') AS result
               FROM audit_log ORDER BY revision DESC"
        );
        self::assertSame([-2, -3, -4], array_map('intval', array_column($rows, 'revision')));
        self::assertSame(['at the mark', 'no unique id', 'no time'], array_column($rows, 'result'));
        self::assertSame(
            array_fill(0, 3, $this->uniqueId),
            array_column($rows, 'uid'),
            'A row without a unique_id takes the sample\'s, or the drain could not file it.'
        );
    }

    public function testMovedRowsAreFiledBeforeTheLiveHistoryThatCameAfterThem(): void
    {
        $archive = new AuditArchiveService(LegacyAppHarness::db());
        // Live history already filed, as on a lab that kept draining.
        $this->live(1, '2026-06-10 12:00:00', 'june');
        $archive->runFromAuditLog();

        $this->legacy(2, '2025-11-01 09:00:00', 'november');
        $this->legacy(3, '2025-12-01 09:00:00', 'december');
        $this->move($archive);
        $archive->runFromAuditLog();

        $rows = $this->fileRows($archive);
        self::assertSame(['november', 'december', 'june'], array_column($rows, 'result'));
        self::assertSame(['1', '2', '3'], array_map('strval', array_column($rows, 'revision')));
        self::assertSame(0, (int) LegacyAppHarness::db()->rawQueryOne('SELECT COUNT(*) AS n FROM audit_log')['n']);
    }

    /** A lab whose drain had stopped: live history still queued when the legacy rows arrive. */
    public function testMovedRowsSharingABatchWithQueuedLiveHistoryAreFiledInTimeOrder(): void
    {
        $archive = new AuditArchiveService(LegacyAppHarness::db());
        $this->live(1, '2026-06-10 12:00:00', 'june');
        $this->legacy(2, '2025-11-01 09:00:00', 'november');
        $this->legacy(3, '2025-12-01 09:00:00', 'december');
        $this->move($archive);
        $archive->runFromAuditLog();

        self::assertSame(['november', 'december', 'june'], array_column($this->fileRows($archive), 'result'));
    }

    public function testAMovedRowTheFileAlreadyHoldsIsNotFiledTwice(): void
    {
        $archive = new AuditArchiveService(LegacyAppHarness::db());
        // Opening a sample's audit trail archived its legacy rows straight to the file.
        $this->live(1, '2025-11-01 09:00:00', 'november');
        $archive->runFromAuditLog();

        $this->legacy(2, '2025-11-01 09:00:00', 'november');
        $this->legacy(3, '2025-12-01 09:00:00', 'december');
        $this->move($archive);
        $archive->runFromAuditLog();

        self::assertSame(['november', 'december'], array_column($this->fileRows($archive), 'result'));
        self::assertSame(0, (int) LegacyAppHarness::db()->rawQueryOne('SELECT COUNT(*) AS n FROM audit_log')['n']);
    }

    public function testARowAnInterruptedRunCopiedIsNotCopiedTwice(): void
    {
        $this->legacy(2, '2025-11-01 09:00:00', 'november');
        // Copied, then the run stopped before deleting it from the legacy table.
        LegacyAppHarness::db()->insert('audit_log', [
            'form_table' => 'form_vl', 'record_id' => '50', 'revision' => -2, 'action' => 'update',
            'dt_datetime' => '2025-11-01 09:00:00',
            'row_data' => json_encode(['vl_sample_id' => 50, 'unique_id' => $this->uniqueId, 'result' => 'november']),
        ]);

        $this->move(new AuditArchiveService(LegacyAppHarness::db()));

        self::assertSame(1, (int) LegacyAppHarness::db()->rawQueryOne('SELECT COUNT(*) AS n FROM audit_log')['n']);
        self::assertFalse(self::tableExists('audit_form_vl'));
    }

    /**
     * The live triggers number a record's next revision from audit_log. Holding only
     * moved rows, at negative revisions, it must still start at 1: below that it can
     * land on a moved row's key and fail the save, or be skipped by the drain.
     */
    public function testLiveEditsAfterAMoveKeepPositiveRevisions(): void
    {
        $this->legacy(4, '2025-11-01 09:00:00', 'november');
        $this->legacy(5, '2025-12-01 09:00:00', 'december');
        $this->move(new AuditArchiveService(LegacyAppHarness::db()));

        LegacyAppHarness::withAuditTriggers(['form_vl' => 'vl_sample_id']);
        LegacyAppHarness::db()->rawQuery("UPDATE form_vl SET result = 'first' WHERE vl_sample_id = 50");
        LegacyAppHarness::db()->rawQuery("UPDATE form_vl SET result = 'second' WHERE vl_sample_id = 50");

        $revisions = array_map('intval', array_column(LegacyAppHarness::db()->rawQuery(
            'SELECT revision FROM audit_log WHERE revision > -4 ORDER BY id'
        ), 'revision'));
        self::assertSame([1, 2], $revisions);
    }
}
