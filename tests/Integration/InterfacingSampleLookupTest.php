<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\InterfacingService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Support\LegacyAppHarness;

/**
 * How an instrument result finds the sample it belongs to.
 *
 * The lookup used to bind order_id and test_id into the IN lists as they came, so a
 * result with no test_id searched for '' and matched any sample whose code was
 * blank. An instrument that uploads results LIS never issued (a GeneXpert sending
 * its whole history) could then write onto an unrelated sample.
 *
 * bin/interface.php turns off the second lookup that tells a locked sample from an
 * unknown one, because it marks both the same way and the pass is expensive.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class InterfacingSampleLookupTest extends TestCase
{
    private const DATABASE = 'intelis_interfacing_lookup_test';

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    protected function setUp(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), ['r_sample_status', 'form_vl']);
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name) VALUES (6, 'Received at lab')"
        );

        $this->seed(['sample_code' => 'KP23']);
        // Blank codes are what a hand-edited or imported row can carry.
        $this->seed(['sample_code' => 'BLANK-CODES', 'remote_sample_code' => '', 'lab_assigned_code' => '']);
        $this->seed(['sample_code' => 'LOCKED-1', 'locked' => 'yes']);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $columns */
    private function seed(array $columns): void
    {
        $row = $columns + [
            'vlsm_instance_id' => 'test',
            'facility_id' => 1,
            'lab_id' => 1,
            'result_status' => 6,
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            $values[] = "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_vl (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
        );
    }

    private function service(): InterfacingService
    {
        /** @var InterfacingService $service */
        $service = ContainerRegistry::get(InterfacingService::class);

        // Active modules come from the instance config; pin them to the one table seeded.
        new ReflectionClass(InterfacingService::class)
            ->getProperty('activeModules')
            ->setValue($service, ['vl_sample_id' => 'form_vl']);

        return $service;
    }

    private function findSampleCode(string $orderId, string $testId): ?string
    {
        $found = new ReflectionClass(InterfacingService::class)
            ->getMethod('findSample')
            ->invoke($this->service(), $orderId, $testId, false, null);

        return $found['row']['sample_code'] ?? null;
    }

    #[RunInSeparateProcess]
    public function testAResultWithNoTestIdDoesNotMatchASampleWithBlankCodes(): void
    {
        self::assertNull($this->findSampleCode('993', ''), 'order 993 was never issued by LIS');
        self::assertNull($this->findSampleCode('', '563'), 'nor was test 563');
    }

    #[RunInSeparateProcess]
    public function testAKnownCodeStillMatchesWithEitherIdEmpty(): void
    {
        self::assertSame('KP23', $this->findSampleCode('KP23', ''));
        self::assertSame('KP23', $this->findSampleCode(' KP23 ', ''), 'padding from the instrument is ignored');
    }

    #[RunInSeparateProcess]
    public function testAnUnmatchedResultIsReportedWithoutTheSecondLookupWhenAskedTo(): void
    {
        $row = ['id' => 1, 'order_id' => 'LOCKED-1', 'test_id' => ''];

        self::assertSame('sample_locked', $this->service()->importResult($row, 1)['reason']);

        $outcome = $this->service()->importResult($row, 1, explainMisses: false);
        self::assertFalse($outcome['synced']);
        self::assertSame('no_matching_sample', $outcome['reason']);
    }
}
