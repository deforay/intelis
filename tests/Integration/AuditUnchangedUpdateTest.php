<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Utilities\ResultSyncAcknowledgement;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The update trigger writes an audit row only when a column a person can change
 * changed, not for sync bookkeeping or an UPDATE that changes nothing.
 *
 * Each results send flipped data_sync twice and copied the whole row into
 * audit_log both times. A lab that sends often held hundreds of identical
 * snapshots per sample.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class AuditUnchangedUpdateTest extends TestCase
{
    private const DATABASE = 'intelis_audit_unchanged_update_test';

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), ['r_sample_status', 'form_vl', 'audit_log']);
        LegacyAppHarness::seedDefaults('r_sample_status');
        LegacyAppHarness::withAuditTriggers(['form_vl' => 'vl_sample_id']);
        $db->insert('form_vl', [
            'vl_sample_id' => 7,
            'unique_id' => 'audit-unchanged-' . bin2hex(random_bytes(6)),
            'result' => '40',
            'result_status' => 7,
            'data_sync' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @return list<array{action:string, result:mixed, result_type:string, data_sync:mixed}> */
    private function revisions(): array
    {
        $rows = LegacyAppHarness::db()->rawQuery(
            "SELECT action, JSON_UNQUOTE(JSON_EXTRACT(row_data, '$.result')) AS result,
                    JSON_TYPE(JSON_EXTRACT(row_data, '$.result')) AS result_type,
                    JSON_EXTRACT(row_data, '$.data_sync') AS data_sync
               FROM audit_log WHERE form_table = 'form_vl' AND record_id = '7' ORDER BY revision"
        );
        return $rows ?: [];
    }

    public function testResultsSendWritesNoAuditRow(): void
    {
        $db = LegacyAppHarness::db();
        ResultSyncAcknowledgement::markInFlight($db, 'form_vl', 'vl_sample_id', [7]);
        ResultSyncAcknowledgement::markSynced($db, 'form_vl', 'vl_sample_id', [['vl_sample_id' => 7]]);

        $this->assertSame(1, (int) $db->rawQueryValue('SELECT data_sync FROM form_vl WHERE vl_sample_id = 7'));
        $this->assertSame(['insert'], array_column($this->revisions(), 'action'));
    }

    public function testUpdateThatChangesNothingWritesNoAuditRow(): void
    {
        $db = LegacyAppHarness::db();
        $db->rawQuery("UPDATE form_vl SET result = '40', last_modified_datetime = NOW() WHERE vl_sample_id = 7");

        $this->assertSame(['insert'], array_column($this->revisions(), 'action'));
    }

    public function testRealChangeIsAuditedWithTheFlagsOfThatMoment(): void
    {
        $db = LegacyAppHarness::db();
        $db->rawQuery('UPDATE form_vl SET data_sync = 1 WHERE vl_sample_id = 7');
        $db->rawQuery("UPDATE form_vl SET result = '60', data_sync = 0 WHERE vl_sample_id = 7");

        $revisions = $this->revisions();
        $this->assertSame(['insert', 'update'], array_column($revisions, 'action'));
        $this->assertSame('60', $revisions[1]['result']);
        $this->assertSame('0', (string) $revisions[1]['data_sync']);
    }

    public function testClearingAValueIsAudited(): void
    {
        $db = LegacyAppHarness::db();
        $db->rawQuery('UPDATE form_vl SET result = NULL WHERE vl_sample_id = 7');

        $revisions = $this->revisions();
        $this->assertSame(['insert', 'update'], array_column($revisions, 'action'));
        $this->assertSame('NULL', $revisions[1]['result_type']);
    }

    public function testCaseOnlyAndAccentOnlyCorrectionsAreAudited(): void
    {
        $db = LegacyAppHarness::db();
        $db->rawQuery("UPDATE form_vl SET patient_first_name = 'Jose' WHERE vl_sample_id = 7");
        $db->rawQuery("UPDATE form_vl SET patient_first_name = 'jose' WHERE vl_sample_id = 7");
        $db->rawQuery("UPDATE form_vl SET patient_first_name = 'josé' WHERE vl_sample_id = 7");

        $this->assertSame(['insert', 'update', 'update', 'update'], array_column($this->revisions(), 'action'));
    }

    public function testDeleteIsStillAudited(): void
    {
        LegacyAppHarness::db()->rawQuery('DELETE FROM form_vl WHERE vl_sample_id = 7');

        $this->assertSame(['insert', 'delete'], array_column($this->revisions(), 'action'));
    }
}
