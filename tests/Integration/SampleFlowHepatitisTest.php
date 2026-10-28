<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\SampleFlowService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * Hepatitis samples placed by the Sample Ageing Report.
 *
 * The analyzer import writes only the HBV or HCV count and leaves `result`
 * empty. Read off `result` alone, every such sample stayed at "awaiting
 * approval" after it was approved and released.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SampleFlowHepatitisTest extends TestCase
{
    private const DATABASE = 'intelis_sample_flow_hepatitis_test';

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        LegacyAppHarness::boot(TestRun::database(self::DATABASE), [
            'form_hepatitis', 'facility_details', 'r_sample_status', 'instruments', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $columns */
    private function seed(string $sampleCode, array $columns): void
    {
        LegacyAppHarness::db()->insert('form_hepatitis', $columns + [
            'vlsm_instance_id' => 'test',
            'sample_code' => $sampleCode,
            'sample_collection_date' => date('Y-m-d H:i:s', strtotime('-20 days')),
            'sample_received_at_lab_datetime' => date('Y-m-d H:i:s', strtotime('-18 days')),
            'sample_tested_datetime' => date('Y-m-d H:i:s', strtotime('-15 days')),
            'result_status' => 7,
        ]);
    }

    public function testACountWithNoResultIsPlacedPastApproval(): void
    {
        $approved = date('Y-m-d H:i:s', strtotime('-14 days'));
        $this->seed('HCV1', [
            'hcv_vl_count' => '1200', 'result_approved_datetime' => $approved,
            'result_printed_datetime' => date('Y-m-d H:i:s', strtotime('-13 days')),
        ]);
        $this->seed('HBV1', ['hbv_vl_count' => '450', 'result_approved_datetime' => $approved]);
        $this->seed('NONE', ['result_status' => 6]);

        $service = ContainerRegistry::get(SampleFlowService::class);
        $flow = $service->getFlow($service->resolveFilters(['testType' => 'hepatitis', 'dateRange' => '']));

        self::assertSame(1, (int) $flow['released']['total'], 'An HCV count, approved and printed, is released.');
        self::assertSame(1, (int) $flow['awaitingRelease']['total'], 'An approved HBV count waits for release.');
        self::assertSame(
            1,
            (int) $flow['awaitingApproval']['total'],
            'Only the tested sample with no count waits for approval.'
        );
    }
}
