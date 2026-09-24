<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AuditArchiveService;
use App\Utilities\MiscUtility;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The nightly drain moving audit_log into per-sample archive files.
 *
 * It filed only rows of the test forms and deleted everything else as orphans.
 * The per-test rows (tb_tests, generic_test_results, covid19_tests) and user
 * accounts have audit rows too, so their history was written every day and
 * thrown away every night.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class AuditLogDrainTest extends TestCase
{
    private const DATABASE = 'intelis_audit_log_drain_test';

    private string $uniqueId = '';

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), ['form_tb', 'tb_tests', 'audit_log']);
        $this->uniqueId = 'drain-' . bin2hex(random_bytes(6));
        $db->insert('form_tb', [
            'tb_id' => 40, 'unique_id' => $this->uniqueId, 'sample_collection_date' => '2026-09-01 10:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
        foreach (['tb', 'tb-test-rows', 'users'] as $folder) {
            foreach (glob(VAR_PATH . "/audit-trail/$folder/{$this->uniqueId}.csv*") ?: [] as $file) {
                MiscUtility::deleteFile($file);
            }
        }
        foreach (glob(VAR_PATH . '/audit-trail/users/9123.csv*') ?: [] as $file) {
            MiscUtility::deleteFile($file);
        }
    }

    private static function log(string $table, int $recordId, int $revision, string $action, array $row): void
    {
        LegacyAppHarness::db()->insert('audit_log', [
            'form_table' => $table,
            'record_id' => (string) $recordId,
            'revision' => $revision,
            'action' => $action,
            'dt_datetime' => '2026-09-24 10:0' . $revision . ':00',
            'row_data' => json_encode($row),
        ]);
    }

    private static function remaining(): array
    {
        return array_column(
            LegacyAppHarness::db()->rawQuery('SELECT form_table FROM audit_log ORDER BY id'),
            'form_table'
        );
    }

    public function testTestRowsAreFiledWithTheirSample(): void
    {
        self::log('tb_tests', 7, 1, 'insert', ['tb_test_id' => 7, 'tb_id' => 40, 'test_result' => 'MTB']);
        self::log('tb_tests', 7, 2, 'delete', ['tb_test_id' => 7, 'tb_id' => 40, 'test_result' => 'MTB']);

        $archive = new AuditArchiveService(LegacyAppHarness::db());
        $archive->runFromAuditLog();

        self::assertSame([], self::remaining());
        $file = $archive->resolveTestRowsFilePath('tb', $this->uniqueId);
        self::assertNotNull($file);
        $rows = $archive->readAuditDataFromCsvFlexible($file);
        self::assertSame(['insert', 'delete'], array_column($rows, 'action'));
        self::assertSame(['MTB', 'MTB'], array_column($rows, 'test_result'));
        // Not mistaken for the sample's own history.
        self::assertNull($archive->resolveAuditFilePath('tb', $this->uniqueId));
    }

    public function testATestRowWhoseSampleCannotBeFoundIsKept(): void
    {
        self::log('tb_tests', 8, 1, 'insert', ['tb_test_id' => 8, 'tb_id' => 999, 'test_result' => 'MTB']);

        (new AuditArchiveService(LegacyAppHarness::db()))->runFromAuditLog();

        self::assertSame(['tb_tests'], self::remaining());
    }

    public function testUserAccountChangesAreFiledByUser(): void
    {
        self::log('user_details', 9123, 1, 'update', ['user_id' => 9123, 'user_name' => 'lab tech']);

        (new AuditArchiveService(LegacyAppHarness::db()))->runFromAuditLog();

        self::assertSame([], self::remaining());
        self::assertNotSame([], glob(VAR_PATH . '/audit-trail/users/9123.csv*') ?: []);
    }

    public function testRowsOfATableNothingFilesAreStillDropped(): void
    {
        self::log('some_retired_table', 1, 1, 'insert', ['id' => 1]);

        (new AuditArchiveService(LegacyAppHarness::db()))->runFromAuditLog();

        self::assertSame([], self::remaining());
    }
}
