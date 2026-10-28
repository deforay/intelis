<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;

/**
 * A manifest's status follows the package: printing it marks it dispatched,
 * and the lab taking it in marks it received.
 *
 * Nothing used to set either. Every manifest stayed pending forever, so the
 * list could not show what had left or arrived.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ManifestStatusTest extends TestCase
{
    private const DATABASE = 'intelis_manifest_status_test';

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

        self::clearFileCache();

        $db = LegacyAppHarness::boot(TestRun::database(self::DATABASE), self::TABLES);
        LegacyAppHarness::withSession();

        $statuses = [RECEIVED_AT_TESTING_LAB => 'Received at Testing Lab', RECEIVED_AT_CLINIC => 'Received at Clinic'];
        foreach ($statuses as $id => $name) {
            $db->insert('r_sample_status', ['status_id' => $id, 'status_name' => $name, 'status' => 'active']);
        }
    }

    protected function tearDown(): void
    {
        $_POST = [];
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    public function testPrintingAPendingManifestMarksItDispatched(): void
    {
        $id = $this->manifest('M-PRINT', TestRequestsService::MANIFEST_PENDING);

        $this->service()->recordManifestPrint([$this->manifestRow($id)]);

        self::assertSame(TestRequestsService::MANIFEST_DISPATCHED, $this->manifestStatus('M-PRINT'));
    }

    public function testReprintingAReceivedManifestLeavesItReceived(): void
    {
        $id = $this->manifest('M-REPRINT', TestRequestsService::MANIFEST_RECEIVED);

        $this->service()->recordManifestPrint([$this->manifestRow($id)]);

        self::assertSame(TestRequestsService::MANIFEST_RECEIVED, $this->manifestStatus('M-REPRINT'));
    }

    public function testAPrintIsAppendedToTheHistoryKeptSoFar(): void
    {
        $id = $this->manifest('M-HISTORY', TestRequestsService::MANIFEST_PENDING);
        // An older row whose history is a single entry rather than a list.
        LegacyAppHarness::db()->rawQuery(
            "UPDATE specimen_manifests SET manifest_print_history = ? WHERE manifest_id = ?",
            ['{"printedBy": "9", "date": "2026-01-01 00:00:00"}', $id]
        );

        $this->service()->recordManifestPrint([$this->manifestRow($id)]);
        $this->service()->recordManifestPrint([$this->manifestRow($id)]);

        $history = json_decode((string) $this->manifestRow($id)['manifest_print_history'], true);
        self::assertTrue(array_is_list($history));
        self::assertCount(3, $history);
        self::assertSame('9', $history[0]['printedBy']);
    }

    public function testARowWithoutAnIdChangesNothing(): void
    {
        $this->manifest('M-ODD', TestRequestsService::MANIFEST_PENDING);

        $this->service()->recordManifestPrint([['manifest_code' => 'M-ODD']]);

        self::assertSame(TestRequestsService::MANIFEST_PENDING, $this->manifestStatus('M-ODD'));
    }

    public function testActivatingAManifestMarksItReceived(): void
    {
        $this->manifest('M-ACTIVATE', TestRequestsService::MANIFEST_DISPATCHED);
        $this->sample('uid-a', 'M-ACTIVATE', RECEIVED_AT_CLINIC, null);

        $_POST = ['sampleReceivedOn' => '2026-09-10 08:00:00'];
        $this->service()->activateSamplesFromManifest('vl', 'M-ACTIVATE');

        self::assertSame(TestRequestsService::MANIFEST_RECEIVED, $this->manifestStatus('M-ACTIVATE'));
    }

    public function testTheStsMarksReceivedOnlyTheManifestsWhoseSamplesArrived(): void
    {
        $this->manifest('M-ARRIVED', TestRequestsService::MANIFEST_DISPATCHED);
        $this->manifest('M-WAITING', TestRequestsService::MANIFEST_PENDING);
        $arrived = $this->sample('uid-arrived', 'M-ARRIVED', RECEIVED_AT_TESTING_LAB, '2026-09-10 08:00:00');
        $waiting = $this->sample('uid-waiting', 'M-WAITING', RECEIVED_AT_CLINIC, null);

        $this->service()->markManifestsReceivedForSamples('vl', [$arrived, $waiting]);

        self::assertSame(TestRequestsService::MANIFEST_RECEIVED, $this->manifestStatus('M-ARRIVED'));
        self::assertSame(TestRequestsService::MANIFEST_PENDING, $this->manifestStatus('M-WAITING'));
    }

    private function service(): TestRequestsService
    {
        /** @var TestRequestsService $service */
        $service = ContainerRegistry::get(TestRequestsService::class);
        return $service;
    }

    private function manifest(string $code, string $status): int
    {
        LegacyAppHarness::db()->insert('specimen_manifests', [
            'manifest_code' => $code,
            'manifest_status' => $status,
            'module' => 'vl',
            'lab_id' => 1,
            'added_by' => '1',
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function sample(string $uniqueId, string $manifestCode, int $status, ?string $receivedAt): int
    {
        LegacyAppHarness::db()->insert('form_vl', [
            'unique_id' => $uniqueId,
            'sample_code' => 'S-' . $uniqueId,
            'sample_package_code' => $manifestCode,
            'result_status' => $status,
            'sample_collection_date' => '2026-09-01 10:00:00',
            'sample_received_at_lab_datetime' => $receivedAt,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function manifestStatus(string $code): ?string
    {
        $row = LegacyAppHarness::db()->rawQueryOne(
            'SELECT manifest_status FROM specimen_manifests WHERE manifest_code = ?',
            [$code]
        );
        return $row['manifest_status'] ?? null;
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

    /** @return array<string, mixed> */
    private function manifestRow(int|string $id): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM specimen_manifests WHERE manifest_id = ?', [$id]);
    }
}
