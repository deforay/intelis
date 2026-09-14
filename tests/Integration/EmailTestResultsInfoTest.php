<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\TestResultsService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * Marking results as emailed touches only the samples that were emailed.
 *
 * get() clears the where() it was given, so the update that followed it ran with
 * no WHERE: emailing one sample flagged every sample in the table as sent and
 * copied that sample's form attributes over every other row.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class EmailTestResultsInfoTest extends TestCase
{
    protected function setUp(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') === '' || (getenv('INTELIS_TEST_DB_USER') ?: '') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot('intelis_email_results_info_' . getmypid(), [
            'roles', 'facility_details', 'r_sample_status', 'batch_details', 'user_details',
            'r_vl_sample_type', 'r_vl_sample_rejection_reasons', 'r_vl_test_reasons', 'r_vl_art_regimen',
            'r_funding_sources', 'r_implementation_partners', 'form_vl',
        ]);
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (7, 'Accepted')");
        foreach ([1 => '{"storage":"A1"}', 2 => '{"storage":"B2"}', 3 => null] as $id => $attributes) {
            $db->insert('form_vl', [
                'vl_sample_id' => $id,
                'unique_id' => "u$id",
                'vlsm_instance_id' => 'test',
                'sample_code' => "VL00$id",
                'result_status' => 7,
                'is_result_mail_sent' => 'no',
                'form_attributes' => $attributes,
                'result_dispatched_datetime' => $id === 2 ? '2026-01-01 10:00:00' : null,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') !== '') {
            LegacyAppHarness::shutdown();
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        $rows = LegacyAppHarness::db()->rawQuery(
            'SELECT vl_sample_id, is_result_mail_sent, form_attributes, result_dispatched_datetime
             FROM form_vl ORDER BY vl_sample_id'
        );
        return array_column($rows, null, 'vl_sample_id');
    }

    public function testOnlyTheEmailedSamplesAreMarked(): void
    {
        (new TestResultsService(LegacyAppHarness::db()))
            ->updateEmailTestResultsInfo('vl', ['samples' => '1,3', 'to_mail' => 'lab@example.org']);

        $rows = $this->rows();

        $this->assertSame('yes', $rows[1]['is_result_mail_sent']);
        $this->assertSame('A1', json_decode((string) $rows[1]['form_attributes'])->storage);
        $this->assertSame('lab@example.org', json_decode((string) $rows[1]['form_attributes'])->email_sent_to);
        $this->assertNotNull($rows[1]['result_dispatched_datetime']);

        $this->assertSame('yes', $rows[3]['is_result_mail_sent']);
        $this->assertSame('lab@example.org', json_decode((string) $rows[3]['form_attributes'])->email_sent_to);

        $this->assertSame('no', $rows[2]['is_result_mail_sent']);
        $this->assertEquals((object) ['storage' => 'B2'], json_decode((string) $rows[2]['form_attributes']));
        $this->assertSame('2026-01-01 10:00:00', $rows[2]['result_dispatched_datetime']);
    }

    public function testASampleListCarryingSqlMarksNothingElse(): void
    {
        (new TestResultsService(LegacyAppHarness::db()))
            ->updateEmailTestResultsInfo('vl', ['samples' => '1) OR (1=1', 'to_mail' => 'lab@example.org']);

        $rows = $this->rows();
        $this->assertSame('no', $rows[1]['is_result_mail_sent']);
        $this->assertSame('no', $rows[2]['is_result_mail_sent']);
        $this->assertSame('no', $rows[3]['is_result_mail_sent']);
    }
}
