<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\InstrumentsService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * The instruments offered on the Import Result page.
 *
 * Only instruments that support the test and have an import script for it are
 * offered: any other one would fail with "Import Script not found". Instruments
 * saved before supported tests existed have none set and still show (#525).
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class InstrumentsForResultImportTest extends TestCase
{
    private const DATABASE = 'intelis_instruments_for_result_import_test';

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

        $db = LegacyAppHarness::boot(TestRun::database(self::DATABASE), [
            'system_config', 'global_config', 'facility_details', 'instruments',
        ]);
        LegacyAppHarness::addMigrationColumns('5.8.6', ['instruments']);

        $db->rawQuery(
            "INSERT INTO instruments
                    (instrument_id, machine_name, import_machine_file_name, supported_tests, status,
                     max_no_of_samples_in_a_batch)
             VALUES ('vl-only', 'A VL only', 'abbott.php', '[\"vl\"]', 'active', 96),
                    ('eid-only', 'B EID only', 'abbott.php', '[\"eid\"]', 'active', 96),
                    ('legacy-null', 'C No supported tests', 'abbott.php', NULL, 'active', 96),
                    ('legacy-empty', 'D Empty supported tests', 'abbott.php', '[]', 'active', 96),
                    ('no-script', 'E Missing script', 'no-such-script.php', '[\"vl\"]', 'active', 96),
                    ('inactive', 'F Inactive', 'abbott.php', '[\"vl\"]', 'inactive', 96)"
        );
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testOnlyInstrumentsThatCanImportVlAreOffered(): void
    {
        $service = ContainerRegistry::get(InstrumentsService::class);

        self::assertSame(
            ['vl-only', 'legacy-null', 'legacy-empty'],
            array_column($service->getForResultImport('vl'), 'instrument_id')
        );
    }

    #[RunInSeparateProcess]
    public function testUnknownTestTypeOffersNothing(): void
    {
        $service = ContainerRegistry::get(InstrumentsService::class);

        self::assertSame([], $service->getForResultImport('not-a-test'));
    }
}
