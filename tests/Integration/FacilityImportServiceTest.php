<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Services\FacilityImportService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * Bulk facility upload: staging plans each row without writing, apply() writes
 * only the ticked rows and refuses any row whose outcome changed since review.
 * The sheet layout is the one the facility export writes, so an unedited export
 * must plan to "no change".
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class FacilityImportServiceTest extends TestCase
{
    private const DATABASE = 'intelis_facility_import_test';

    private DatabaseService $db;
    private FacilityImportService $service;
    private array $files = [];

    protected function setUp(): void
    {
        if ((string) getenv('INTELIS_TEST_DB_HOST') === '' || (string) getenv('INTELIS_TEST_DB_USER') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        $this->db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            'facility_details', 'geographical_divisions', 'facility_type', 'activity_log',
        ]);
        LegacyAppHarness::withSession(['instanceId' => 'test-instance']);
        AppRegistry::set('request', LegacyAppHarness::withPost([]));

        $this->db->rawQuery(
            "INSERT INTO facility_type VALUES (1,'Health Facility'),(2,'Testing Lab'),(3,'Collection Site')"
        );
        $this->db->rawQuery(
            "INSERT INTO geographical_divisions (geo_id, geo_name, geo_parent, geo_status) VALUES
                (1, 'North', 0, 'active'), (2, 'Capital', 1, 'active'),
                (3, 'South', 0, 'active'), (4, 'Coast', 3, 'active')"
        );
        $this->db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_code, other_id, facility_state, facility_state_id,
                 facility_district, facility_district_id, facility_type, address, latitude, longitude,
                 vlsm_instance_id, status)
             VALUES
                (1, 'Riverside Clinic', 'RVC', 'EXT-1', 'North', 1, 'Capital', 2, 1,
                    'River Road', '1.5', '30.5', 'test-instance', 'active'),
                (2, 'Central Reference Lab', 'CRL', NULL, 'North', 1, 'Capital', 2, 2,
                    NULL, NULL, NULL, 'test-instance', 'active'),
                (3, 'Lowoi PHCC', 'L PHCC', NULL, 'South', 3, 'Coast', 4, 1,
                    NULL, NULL, NULL, 'test-instance', 'active'),
                (4, 'Lire PHCC', 'LPHCC', NULL, 'South', 3, 'Coast', 4, 1, NULL, NULL, NULL, 'test-instance', 'active')"
        );

        $this->service = ContainerRegistry::get(FacilityImportService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        LegacyAppHarness::shutdown();
    }

    public function testUneditedExportRowsPlanToNoChange(): void
    {
        $rows = $this->stage(FacilityImportService::OPTION_NAME, [
            ['Riverside Clinic', 'RVC', 'EXT-1', 'North', 'Capital', '1', 'River Road', '', '',
                '1.5', '30.5', 'active'],
            ['Lowoi PHCC', 'L PHCC', '', 'South', 'Coast', '1', '', '', '', '', '', 'active'],
        ])['rows'];

        self::assertSame(['unchanged', 'unchanged'], array_column($rows, 'action'));
        self::assertSame([], $rows[1]['messages'], 'A stored legacy code is kept, not rewritten to collide with LPHCC');
    }

    public function testUpdateShowsOnlyRealChangesAndBlankOptionalCellsKeepValues(): void
    {
        $row = $this->stage(FacilityImportService::OPTION_CODE, [
            ['Riverside Clinic', 'RVC', '', 'North', 'Capital', 'Health Facility', 'New Road', '', '', '', '', ''],
        ])['rows'][0];

        self::assertSame('update', $row['action']);
        self::assertSame(['address' => ['River Road', 'New Road']], $row['changes']);
    }

    public function testExistingFacilitiesAreSkippedOrRefusedPerOption(): void
    {
        $rows = $this->stage(FacilityImportService::OPTION_DEFAULT, [
            ['Riverside Clinic', '', '', 'North', 'Capital', '1', '', '', '', '', '', ''],
        ])['rows'];
        self::assertSame('skip', $rows[0]['action']);

        $rows = $this->stage(FacilityImportService::OPTION_NAME, [
            ['Riverside Clinic', 'CRL', '', 'North', 'Capital', '1', '', '', '', '', '', ''],
            ['New Clinic', '', 'EXT-1', 'North', 'Capital', '1', '', '', '', '', '', ''],
            ['Another Clinic', '', '', 'North', 'Capital', '7', '', '', '', '', '', ''],
            ['Another Clinic', '', '', 'North', 'Capital', '1', '', '', '', '', '', ''],
        ])['rows'];
        self::assertSame(['error', 'error', 'error', 'error'], array_column($rows, 'action'));
        self::assertStringContainsString('Central Reference Lab', $rows[0]['messages'][0]);
        self::assertStringContainsString('Riverside Clinic', $rows[1]['messages'][0]);
        self::assertStringContainsString('row 4', $rows[3]['messages'][0]);
    }

    public function testRiskyChangesAndLikelyDuplicatesAreWarned(): void
    {
        $rows = $this->stage(FacilityImportService::OPTION_CODE, [
            ["Children's Lab International", 'RVC', 'EXT-2', 'South', 'Coast', '2', '', '', '', '', '', 'inactive'],
            ['CENTRAL  REFERENCE-LAB.', '', '', 'North', 'Capital', '2', '', '', '', '', '', ''],
            ['Saint Marys Clinic', '', '', 'North', 'Capital', '1', '', '', '', '', '', ''],
            ["Saint Mary's Clinic", '', '', 'North', 'Capital', '1', '', '', '', '', '', ''],
            ['Hilltop Clinic', '', '', 'North', 'Capital', '1', '', '', '', '', '', ''],
        ])['rows'];

        self::assertSame(['update', 'insert', 'insert', 'insert', 'insert'], array_column($rows, 'action'));
        $warnings = implode("\n", $rows[0]['warnings']);
        self::assertStringContainsString('Facility Name changes a lot', $warnings);
        self::assertStringContainsString('Facility Type changes from Health Facility to Testing Lab', $warnings);
        self::assertStringContainsString('External Facility Code changes', $warnings);
        self::assertStringContainsString('different Province/State', $warnings);
        self::assertStringContainsString('inactive', $warnings);
        self::assertStringContainsString('Central Reference Lab', $rows[1]['warnings'][0]);
        self::assertSame([], $rows[2]['warnings']);
        self::assertStringContainsString('row 4', $rows[3]['warnings'][0]);
        self::assertSame([], $rows[4]['warnings']);
    }

    public function testApplyWritesOnlyTickedRowsAndAddsNewPlaces(): void
    {
        // A fresh name per run: getOrCreateProvince() memoises across requests for 20s.
        $newProvince = 'West ' . bin2hex(random_bytes(3));
        $batch = $this->stage(FacilityImportService::OPTION_NAME, [
            ['Riverside Clinic', '', '', 'North', 'Capital', '1', 'New Road', '', '', '', '', ''],
            ['Hilltop Clinic', '', 'EXT-9', $newProvince, 'Hills', '3', '', '', '', '', '', ''],
            ['Valley Lab', '', '', 'North', 'Capital', '2', '', '', '', '', '', ''],
        ]);

        $result = $this->service->apply($batch, [3, 4]);

        self::assertSame([0, 2, 1], [$result['updated'], $result['inserted'], $result['excluded']]);
        self::assertSame('River Road', $this->address(1));

        $hilltop = $this->db->rawQueryOne("SELECT * FROM facility_details WHERE facility_name = 'Hilltop Clinic'");
        $west = $this->db->rawQueryOne(
            'SELECT geo_id FROM geographical_divisions WHERE geo_name = ? AND geo_parent = 0',
            [$newProvince]
        );
        self::assertSame((int) $west['geo_id'], (int) $hilltop['facility_state_id']);
        self::assertSame(
            ['EXT-9', 'active', 'test-instance'],
            [$hilltop['other_id'], $hilltop['status'], $hilltop['vlsm_instance_id']]
        );

        $lab = $this->db->rawQueryOne("SELECT facility_code FROM facility_details WHERE facility_name = 'Valley Lab'");
        self::assertNotEmpty($lab['facility_code'], 'A new testing lab without a code gets one generated');
    }

    public function testRowChangedSinceReviewIsNotWritten(): void
    {
        $batch = $this->stage(FacilityImportService::OPTION_NAME, [
            ['Riverside Clinic', '', '', 'North', 'Capital', '1', 'New Road', '', '', '', '', ''],
            ['Hilltop Clinic', '', '', 'North', 'Capital', '1', '', '', '', '', '', ''],
        ]);

        $this->db->rawQuery("UPDATE facility_details SET address = 'Changed Elsewhere' WHERE facility_id = 1");
        $this->db->rawQuery(
            "INSERT INTO facility_details
                (facility_name, facility_state_id, facility_district_id, facility_type, vlsm_instance_id, status)
             VALUES ('Hilltop Clinic', 1, 2, 1, 'test-instance', 'active')"
        );

        $result = $this->service->apply($batch, [2, 3]);

        self::assertSame([0, 0], [$result['updated'], $result['inserted']]);
        self::assertCount(2, $result['failed']);
        self::assertSame('Changed Elsewhere', $this->address(1));
    }

    public function testBatchBelongsToTheUserWhoStagedIt(): void
    {
        $batch = $this->stage(FacilityImportService::OPTION_NAME, [
            ['Riverside Clinic', '', '', 'North', 'Capital', '1', '', '', '', '', '', ''],
        ]);

        $_SESSION['userId'] = 999;
        self::assertNull($this->service->loadBatch($batch['id']));
        self::assertNull($this->service->loadBatch('../../etc/passwd'));
    }

    private function address(int $facilityId): ?string
    {
        $row = $this->db->rawQueryOne('SELECT address FROM facility_details WHERE facility_id = ?', [$facilityId]);
        return $row['address'];
    }

    /** @param list<list<string>> $rows */
    private function stage(string $option, array $rows): array
    {
        $path = sys_get_temp_dir() . '/facility-import-test-' . bin2hex(random_bytes(4)) . '.xlsx';
        $this->files[] = $path;

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(FacilitiesService::bulkUploadHeadings()));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        $batchId = $this->service->stage($path, $option, 'test.xlsx');
        $this->files[] = VAR_TEMP_PATH . '/facility-import/' . $batchId . '.json';
        $batch = $this->service->loadBatch($batchId);
        self::assertNotNull($batch);
        return $batch;
    }
}
