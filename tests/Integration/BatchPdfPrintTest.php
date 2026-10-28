<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\SystemException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

use const SAMPLE_STATUS\ACCEPTED;

/**
 * The batch worksheet prints return the PDF as the page's response.
 *
 * generate-batch-pdf.php used to end with exit, which skipped the response
 * path and ended any process that drove it, so no test could print one. A
 * print that exits again fails here, because the test never reaches its
 * assertions.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can drive
 * it once: every test that drives a page runs in its own process.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class BatchPdfPrintTest extends TestCase
{
    private const DATABASE = 'intelis_batch_pdf_print_test';

    private const BATCH_ID = 3;
    private const BATCH_CODE = 'BATCH-TEST-0003';

    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config', 'activity_log',
        'roles', 'user_details', 'facility_details', 'geographical_divisions', 'instruments',
        'batch_details', 'r_sample_status', 'r_funding_sources', 'r_implementation_partners',
        'form_vl', 'r_vl_sample_rejection_reasons', 'r_vl_sample_type', 'r_vl_test_reasons',
    ];

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    public static function printProvider(): array
    {
        return [
            'the worksheet' => ['/batch/generate-batch-pdf.php'],
            'the compact worksheet' => ['/batch/generate-compact-batch-pdf.php'],
        ];
    }

    #[RunInSeparateProcess]
    #[DataProvider('printProvider')]
    public function testPrintReturnsThePdfAndRecordsThePrint(string $path): void
    {
        $this->boot();

        $body = $this->print($path, MiscUtility::sqid(self::BATCH_ID));

        self::assertStringStartsWith('%PDF-', $body);
        self::assertStringContainsString('%%EOF', substr($body, -32));
        $text = self::pdfText($body);
        if ($text !== null) {
            self::assertStringContainsString(self::BATCH_CODE, $text);
            self::assertStringContainsString('VL-TEST-0001', $text);
        }
        $printed = LegacyAppHarness::db()->rawQueryOne(
            'SELECT printed_datetime FROM batch_details WHERE batch_id = ?',
            [self::BATCH_ID]
        );
        self::assertNotEmpty($printed['printed_datetime']);
    }

    #[RunInSeparateProcess]
    public function testUnknownBatchIsAnInvalidBatch(): void
    {
        $this->boot();

        $this->expectException(SystemException::class);
        $this->expectExceptionMessage('Invalid Batch');

        $this->print('/batch/generate-batch-pdf.php', MiscUtility::sqid(self::BATCH_ID + 1));
    }

    private function boot(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        $db = LegacyAppHarness::boot(TestRun::database(self::DATABASE), self::TABLES);
        foreach (['logo' => '', 'header' => 'Test Lab', 'barcode_format' => 'C39', 'key' => ''] as $name => $value) {
            $db->insert('global_config', ['display_name' => $name, 'name' => $name, 'value' => $value]);
        }
        $db->insert('facility_details', [
            'facility_id' => 5, 'facility_name' => 'Lab 5', 'facility_type' => 2, 'status' => 'active',
        ]);
        $db->insert('r_sample_status', ['status_id' => ACCEPTED, 'status_name' => 'Accepted', 'status' => 'active']);
        $db->insert('form_vl', [
            'vl_sample_id' => 1, 'unique_id' => 'batch-pdf-test-1', 'sample_code' => 'VL-TEST-0001',
            'lab_id' => 5, 'sample_batch_id' => self::BATCH_ID, 'result' => 'Target Not Detected',
            'sample_tested_datetime' => '2026-10-01 10:00:00', 'result_status' => ACCEPTED,
        ]);
        $db->insert('batch_details', [
            'batch_id' => self::BATCH_ID, 'machine' => '1', 'batch_code' => self::BATCH_CODE,
            'test_type' => 'vl', 'position_type' => 'numeric', 'label_order' => json_encode(['s_1']),
            'request_created_datetime' => '2026-10-01 09:00:00',
        ]);

        LegacyAppHarness::withSession(['labId' => 5, 'instance' => ['type' => 'vluser']]);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** The PDF's text, or null where pdftotext is not installed. */
    private static function pdfText(string $pdf): ?string
    {
        exec('command -v pdftotext', $found, $status);
        if ($status !== 0) {
            return null;
        }
        $file = tempnam(sys_get_temp_dir(), 'batch-pdf-');
        file_put_contents($file, $pdf);
        $text = shell_exec('pdftotext ' . escapeshellarg($file) . ' -');
        unlink($file);
        return (string) $text;
    }

    private function print(string $path, string $id): string
    {
        // The prints read the query string, not a posted body
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', $path)
            ->withQueryParams(['type' => 'vl', 'id' => $id])
            ->withHeader('User-Agent', 'intelis-tests')
            ->withHeader('X-Forwarded-For', '127.0.0.1');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return (string) $handler->handle($request)->getBody();
    }
}
