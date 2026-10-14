<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\SampleTestingReportUtility;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The Sample Testing export replays the query its own module's tab last ran.
 *
 * All modules once shared one session entry, so searching EID in one browser
 * tab and exporting from the VL tab in another gave a file named VL holding
 * EID counts. Here both tabs search, then each exports, and each file must
 * hold only its own module's facility.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ClinicReportSampleTestingExportTest extends TestCase
{
    private const LAB_ID = 901;
    private const VL_FACILITY = 902;
    private const EID_FACILITY = 903;

    /** Per process, so two runs sharing a MySQL server never drop each other's fixtures. */
    private static function database(): string
    {
        return 'intelis_clinic_sample_testing_test_' . getmypid();
    }

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
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => self::database()],
                'modules' => ['vl' => true, 'eid' => true],
            ]);
        }

        $db = LegacyAppHarness::boot(self::database(), [
            'r_sample_status', 'facility_details', 'roles', 'user_details',
            'form_vl', 'form_eid', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_type, vlsm_instance_id, status)
             VALUES (" . self::LAB_ID . ", 'Central Lab', 2, 'test', 'active'),
                    (" . self::VL_FACILITY . ", 'VL Only Clinic', 1, 'test', 'active'),
                    (" . self::EID_FACILITY . ", 'EID Only Clinic', 1, 'test', 'active')"
        );
        $db->rawQuery("INSERT INTO r_sample_status (status_id, status_name, status) VALUES (7, 'Accepted', 'active')");

        $collected = date('Y-m-d H:i:s', strtotime('-2 days'));
        foreach (['form_vl' => self::VL_FACILITY, 'form_eid' => self::EID_FACILITY] as $table => $facility) {
            $db->insert($table, [
                'unique_id' => bin2hex(random_bytes(8)),
                'vlsm_instance_id' => 'test',
                'sample_code' => strtoupper(substr($table, 5)) . '-1',
                'facility_id' => $facility,
                'lab_id' => self::LAB_ID,
                'result_status' => 7,
                'sample_collection_date' => $collected,
                'last_modified_datetime' => $collected,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @return list<string> facility names in the exported file, header row skipped */
    private function exportedFacilities(string $testType): array
    {
        $file = SampleTestingReportUtility::export($testType, LegacyAppHarness::db());
        self::assertNotNull($file, "$testType export found no stored query");

        $reader = new Reader();
        $reader->open($file);
        $facilities = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $i => $row) {
                if ($i > 1) {
                    $facilities[] = (string) $row->toArray()[0];
                }
            }
            break;
        }
        $reader->close();
        unlink($file);

        return $facilities;
    }

    #[RunInSeparateProcess]
    public function testEachModuleExportsOnlyWhatItsOwnTabSearched(): void
    {
        $db = LegacyAppHarness::db();
        $general = ContainerRegistry::get(CommonService::class);

        // VL searched first, then EID, as two browser tabs would.
        SampleTestingReportUtility::fetch('vl', [], $db, $general);
        SampleTestingReportUtility::fetch('eid', [], $db, $general);

        self::assertSame(['VL Only Clinic'], $this->exportedFacilities('vl'));
        self::assertSame(['EID Only Clinic'], $this->exportedFacilities('eid'));
    }
}
