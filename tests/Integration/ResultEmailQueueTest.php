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
 * What the VL "email results" form may queue for bin/send-email.php to attach.
 *
 * The form posts back the result PDF reference it was given, and the helper
 * stored that value as the attachment. The mail sender attached whatever path
 * the value decoded to, so a posted path to a file outside the temporary folder
 * (the database configuration, say) was mailed to the address in the form.
 *
 * One drive per test: the handler uses require_once. Set INTELIS_TEST_DB_HOST/
 * _PORT/_USER/_PASS to run; skipped without them.
 */
final class ResultEmailQueueTest extends TestCase
{
    private string $pdf;

    protected function setUp(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') === '' || (getenv('INTELIS_TEST_DB_USER') ?: '') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        // Named per process: two runs against one MySQL would drop each other's fixtures.
        $db = LegacyAppHarness::boot('intelis_result_email_queue_' . getmypid(), [
            'roles', 'facility_details', 'r_sample_status', 'batch_details', 'user_details',
            'r_vl_sample_type', 'r_vl_sample_rejection_reasons', 'r_vl_test_reasons', 'r_vl_art_regimen',
            'r_funding_sources', 'r_implementation_partners', 'form_vl', 's_vlsm_instance',
            'other_config', 'temp_mail', 'activity_log', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1, 'userId' => 'user-1']);

        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name) VALUES (7, 'Accepted')");
        $db->insert('form_vl', [
            'vl_sample_id' => 1, 'unique_id' => 'u1', 'vlsm_instance_id' => 'test',
            'sample_code' => 'VL001', 'result_status' => 7,
        ]);

        $this->pdf = TEMP_PATH . DIRECTORY_SEPARATOR . 'intelis-result-queue-test.pdf';
        file_put_contents($this->pdf, '%PDF-1.4 test');
    }

    protected function tearDown(): void
    {
        @unlink($this->pdf);
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') !== '') {
            LegacyAppHarness::shutdown();
        }
    }

    private function submit(string $pdfReference): void
    {
        $request = LegacyAppHarness::withPost([
            'toEmail' => 'someone@example.org',
            'subject' => 'Results',
            'message' => 'Attached',
            'reportEmail' => '',
            'sample' => '1',
            'pdfFile1' => $pdfReference,
        ], '/vl/results/email-results-helper.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $handler->handle($request);
    }

    /** @return list<array<string, mixed>> */
    private static function queued(): array
    {
        return LegacyAppHarness::db()->rawQuery('SELECT attachment, status FROM temp_mail');
    }

    #[RunInSeparateProcess]
    public function testAFileOutsideTheTemporaryFolderIsNeverQueued(): void
    {
        $this->submit(base64_encode(ROOT_PATH . '/composer.json'));

        self::assertSame([], self::queued());
    }

    #[RunInSeparateProcess]
    public function testTheGeneratedResultPdfIsQueuedAsItsPath(): void
    {
        $this->submit(_downloadToken($this->pdf));

        $queued = self::queued();
        self::assertCount(1, $queued);
        self::assertSame(realpath($this->pdf), base64_decode((string) $queued[0]['attachment']));
        self::assertSame('pending', $queued[0]['status']);
    }
}
