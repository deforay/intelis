<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;

/**
 * Activating a manifest a second time keeps the test dates of samples already
 * tested.
 *
 * Activation with a reception date re-dates every sample on the manifest, and it
 * used to clear sample_tested_datetime on all of them. Samples arriving now start
 * untested, so clearing theirs is right. A sample already accepted is not
 * arriving: activating its manifest again erased the date its result was tested.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ManifestActivationTestDateTest extends TestCase
{
    private const DATABASE = 'intelis_manifest_activation_test';

    private const MANIFEST = 'MANIFEST-ACT-1';

    private const TESTED = '2026-09-05 11:00:00';

    private const FIRST_RECEIPT = '2026-09-04 09:00:00';

    private const NEW_RECEIPT = '2026-09-10 08:00:00';

    // form_vl's foreign keys need the tables they point at.
    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config',
        'roles', 'user_details', 'facility_details', 'geographical_divisions',
        'batch_details', 'r_sample_status', 'r_funding_sources', 'r_implementation_partners',
        'form_vl', 'r_vl_sample_rejection_reasons', 'r_vl_sample_type', 'r_vl_test_reasons',
        'specimen_manifests',
    ];

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

        // Config lookups are memoised on disk; a stale entry from an earlier run
        // would answer for this database.
        self::clearFileCache();

        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), self::TABLES);
        LegacyAppHarness::withSession();

        $statuses = [
            RECEIVED_AT_TESTING_LAB => 'Received at Testing Lab',
            ACCEPTED => 'Accepted',
            RECEIVED_AT_CLINIC => 'Received at Clinic',
        ];
        foreach ($statuses as $id => $name) {
            $db->insert('r_sample_status', ['status_id' => $id, 'status_name' => $name, 'status' => 'active']);
        }

        $db->insert('specimen_manifests', [
            'manifest_code' => self::MANIFEST,
            'module' => 'vl',
            'lab_id' => 1,
        ]);

        // Already tested and accepted at the lab: its test date must survive.
        $db->insert('form_vl', [
            'unique_id' => 'uid-tested',
            'sample_code' => 'S-TESTED',
            'sample_package_code' => self::MANIFEST,
            'result_status' => ACCEPTED,
            'result' => '40',
            'sample_collection_date' => '2026-09-01 10:00:00',
            'sample_received_at_lab_datetime' => self::FIRST_RECEIPT,
            'sample_tested_datetime' => self::TESTED,
        ]);

        // Still at the clinic: this one is arriving now, so it starts untested.
        $db->insert('form_vl', [
            'unique_id' => 'uid-arriving',
            'sample_code' => 'S-ARRIVING',
            'sample_package_code' => self::MANIFEST,
            'result_status' => RECEIVED_AT_CLINIC,
            'sample_collection_date' => '2026-09-01 10:00:00',
            'sample_tested_datetime' => self::TESTED,
        ]);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    public function testReactivationKeepsTheTestDateOfATestedSample(): void
    {
        $this->activate();

        $tested = $this->row('uid-tested');
        self::assertSame(self::TESTED, $tested['sample_tested_datetime']);
        self::assertSame(ACCEPTED, (int) $tested['result_status']);
        // The bulk re-dating of reception is deliberate and still applies.
        self::assertSame(self::NEW_RECEIPT, $tested['sample_received_at_lab_datetime']);
    }

    public function testASampleArrivingNowIsReceivedWithNoTestDate(): void
    {
        $this->activate();

        $arriving = $this->row('uid-arriving');
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $arriving['result_status']);
        self::assertNull($arriving['sample_tested_datetime']);
        self::assertSame(self::NEW_RECEIPT, $arriving['sample_received_at_lab_datetime']);
    }

    private function activate(): void
    {
        $_POST = ['sampleReceivedOn' => self::NEW_RECEIPT];

        /** @var TestRequestsService $service */
        $service = ContainerRegistry::get(TestRequestsService::class);
        $service->activateSamplesFromManifest('vl', self::MANIFEST);
    }

    /** @return array<string, mixed> */
    private function row(string $uniqueId): array
    {
        return LegacyAppHarness::db()->rawQueryOne(
            'SELECT result_status, sample_tested_datetime, sample_received_at_lab_datetime
               FROM form_vl WHERE unique_id = ?',
            [$uniqueId]
        ) ?? [];
    }

    private static function clearFileCache(): void
    {
        $dir = CACHE_PATH . DIRECTORY_SEPARATOR . 'file_cache';
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }
}
