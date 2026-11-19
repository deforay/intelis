<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\TestRequestsService;
use App\Services\VlService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * A sample registered from the VL request form gets its code while the form
 * saves. The code used to wait in the queue for the next cron run, so the next
 * form (Save and Next) previewed the same code again, and the sample was then
 * stored under another one. A lab reported this as sample codes changing.
 *
 * Driven as the form posts: insert-sample.php when the user saves, and
 * generateSampleCode.php for the preview the next form shows.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run. Without them the test skips.
 */
final class SampleCodeOnSaveTest extends TestCase
{
    private const COLLECTED = '2026-11-18 09:00';

    protected function setUp(): void
    {
        if (!LegacyAppHarness::configured()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        LegacyAppHarness::boot(TestRun::database('intelis_sample_code_on_save'), [
            'r_sample_status', 'form_vl', 'sequence_counter', 'queue_sample_code_generation', 'facility_details',
            'activity_log', 'system_config', 'global_config', 's_vlsm_instance',
        ]);
        LegacyAppHarness::seedDefaults('global_config');
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name)
                VALUES (6, 'Received at lab'), (9, 'Received at clinic')"
        );
        // A LIS whose lab registers its own samples
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO system_config (name, display_name, value) VALUES ('sc_user_type', 'Instance Type', 'vluser')
                ON DUPLICATE KEY UPDATE value = 'vluser'"
        );
        LegacyAppHarness::withSession(['instanceId' => 'test', 'userId' => '1', 'accessType' => 'testing-lab']);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        if (LegacyAppHarness::configured()) {
            LegacyAppHarness::shutdown();
        }
    }

    private function post(string $path, array $post): string
    {
        $request = LegacyAppHarness::withPost($post, $path);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return trim((string) $handler->handle($request)->getBody());
    }

    private function preview(): string
    {
        $code = json_decode($this->post('/vl/requests/generateSampleCode.php', [
            'sampleCollectionDate' => self::COLLECTED,
        ]), true);
        return (string) ($code['sampleCode'] ?? '');
    }

    private function save(): ?string
    {
        $id = (int) $this->post('/vl/requests/insert-sample.php', [
            'sampleCollectionDate' => self::COLLECTED,
            'sampleCodeFormat' => 'MMYY',
        ]);
        self::assertGreaterThan(0, $id, 'insert-sample.php did not create the sample');
        return LegacyAppHarness::db()->rawQueryOne(
            'SELECT sample_code FROM form_vl WHERE vl_sample_id = ?',
            [$id]
        )['sample_code'] ?? null;
    }

    #[RunInSeparateProcess]
    public function testTheSavedSampleHasItsCodeWhenTheSaveReturns(): void
    {
        $preview = $this->preview();

        self::assertSame($preview, $this->save(), 'The sample was not stored under the code the form showed');
    }

    #[RunInSeparateProcess]
    public function testSaveAndNextPreviewsTheCodeTheNextSampleGets(): void
    {
        $first = $this->save();
        self::assertNotNull($first, 'The saved sample has no code until the cron runs');
        $next = $this->preview();
        self::assertNotSame($first, $next, 'The next form previewed the code the last sample already has');

        // The harness drives a page once per process, so the next sample is
        // registered and given its code through the services the page calls.
        $inserted = ContainerRegistry::get(VlService::class)->insertSample([
            'sampleCollectionDate' => self::COLLECTED, 'sampleCodeFormat' => 'MMYY', 'insertOperation' => true,
        ], true);
        ContainerRegistry::get(TestRequestsService::class)->processSampleCodeQueue(uniqueIds: [$inserted['uniqueId']]);
        $stored = LegacyAppHarness::db()->rawQueryOne(
            'SELECT sample_code FROM form_vl WHERE vl_sample_id = ?',
            [$inserted['id']]
        )['sample_code'] ?? null;

        self::assertSame($next, $stored, 'The next sample was not stored under the code its form showed');
    }
}
