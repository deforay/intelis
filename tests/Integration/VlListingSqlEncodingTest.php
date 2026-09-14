<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The VL request, result-entry, print, failed and approval listings, driven with
 * filter values that try to rewrite the query.
 *
 * These endpoints assemble WHERE clauses as strings. Each filter used to be
 * concatenated straight from $_POST, so a value that closes its quote and adds
 * `OR 1=1` listed every sample instead of none. Three samples are seeded; an
 * injected filter must list none of them (or only the one a cast leaves), and a
 * legitimate filter on the same field must still find its sample.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can drive
 * it once: every test runs in its own process and drives exactly once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class VlListingSqlEncodingTest extends TestCase
{
    private const DATABASE = 'intelis_vl_listing_sql_test';

    private const FACILITY_ID = 11;
    private const OTHER_FACILITY_ID = 12;

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

        // Named per process: two runs against one MySQL would otherwise drop each
        // other's fixtures mid-test.
        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            'roles', 'facility_details', 'r_sample_status', 'batch_details', 'user_details',
            'r_vl_sample_type', 'r_vl_sample_rejection_reasons', 'r_vl_test_reasons', 'r_vl_art_regimen',
            'r_funding_sources', 'r_implementation_partners', 'form_vl', 'activity_log',
            'system_config', 'global_config', 'queue_sample_code_generation',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_state, facility_state_id, facility_district,
                 facility_district_id, facility_type, vlsm_instance_id, status)
             VALUES (" . self::FACILITY_ID . ", 'Riverside Clinic', 'North', 21, 'Capital', 31, 1, 'test', 'active'),
                    (" . self::OTHER_FACILITY_ID . ", 'Hilltop Clinic', 'South', 22, 'Coast', 32, 1, 'test', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name) VALUES
                (1, 'On Hold'), (4, 'Rejected'), (5, 'Test Failed'), (6, 'Received at Testing Lab'),
                (7, 'Accepted'), (9, 'Received at Clinic')"
        );
        $db->rawQuery("INSERT INTO r_vl_sample_type (sample_id, sample_name, status) VALUES (1, 'Plasma', 'active')");
        $db->rawQuery("INSERT INTO batch_details (batch_id, batch_code, test_type) VALUES (1, 'B-ONE', 'vl')");

        // Two accepted results with nothing printed yet, and one failed test.
        $this->seed([
            'sample_code' => 'VL001', 'patient_art_no' => 'ART-7', 'patient_first_name' => 'Ada',
            'sample_batch_id' => 1, 'sample_package_code' => 'MF-1',
        ]);
        $this->seed(['sample_code' => 'VL002', 'patient_art_no' => 'ART-8', 'patient_first_name' => 'Ben']);
        $this->seed([
            'sample_code' => 'VL003', 'patient_art_no' => 'ART-9', 'patient_first_name' => 'Cy',
            'facility_id' => self::OTHER_FACILITY_ID, 'result' => 'Failed', 'result_status' => 5,
        ]);
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
            'unique_id' => bin2hex(random_bytes(8)),
            'facility_id' => self::FACILITY_ID,
            'specimen_type' => 1,
            'sample_collection_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'result' => '1200',
            'result_status' => 7,
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            $values[] = $value === null ? 'NULL' : "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_vl (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
        );
    }

    /**
     * @param array<string, mixed> $post
     * @return list<string> the sample codes listed, sorted
     */
    private function listed(string $endpoint, array $post): array
    {
        $request = LegacyAppHarness::withPost($post + [
            'sEcho' => 1, 'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sSearch' => '',
        ], $endpoint);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();
        $json = json_decode($body, true);
        self::assertIsArray($json, "Endpoint did not return JSON: $body");
        self::assertArrayHasKey('aaData', $json, $body);

        $codes = [];
        foreach ($json['aaData'] as $row) {
            foreach ($row as $cell) {
                if (preg_match('/VL00\d/', strip_tags((string) $cell), $m) === 1) {
                    $codes[] = $m[0];
                    break;
                }
            }
        }
        sort($codes);
        return $codes;
    }

    #[RunInSeparateProcess]
    public function testRequestListTreatsAQuoteInTheBatchCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed('/vl/requests/get-request-list.php', [
            'batchCode' => 'B-ONE" OR "1"="1', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListStillFindsAPatientId(): void
    {
        self::assertSame(['VL001'], $this->listed('/vl/requests/get-request-list.php', [
            'patientId' => 'ART-7', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListKeepsOnlyTheNumbersOfAFacilityList(): void
    {
        self::assertSame(['VL003'], $this->listed('/vl/requests/get-request-list.php', [
            'facilityName' => self::OTHER_FACILITY_ID . ',0) OR (1=1', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultEntryTreatsAQuoteInTheManifestCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed('/vl/results/get-manual-results.php', [
            'manifestCode' => 'MF-1" OR "1"="1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultEntryStillFindsAManifest(): void
    {
        self::assertSame(['VL001'], $this->listed('/vl/results/get-manual-results.php', [
            'manifestCode' => 'MF-1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultEntryDiscardsAnythingButAscOrDescAsTheSortDirection(): void
    {
        self::assertSame(['VL001', 'VL002'], $this->listed('/vl/results/get-manual-results.php', [
            'iSortCol_0' => 0, 'iSortingCols' => 1, 'bSortable_0' => 'true',
            'sSortDir_0' => 'asc, (SELECT 1 FROM form_vl WHERE 1=1 UNION SELECT 2)',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListTreatsAQuoteInThePatientNameAsPartOfTheName(): void
    {
        self::assertSame([], $this->listed('/vl/results/get-results-for-print.php', [
            'patientName' => "zz%' OR 1=1 OR '", 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListStillFindsAPartOfAPatientId(): void
    {
        self::assertSame(['VL002'], $this->listed('/vl/results/get-results-for-print.php', [
            'patientId' => 'RT-8', 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsKeepOnlyTheNumbersOfAStatusList(): void
    {
        // "5,0) OR (1=1" used to widen the status filter to every row; as an id
        // list it is 5, the one failed test, and a piece that is not a number.
        self::assertSame(['VL003'], $this->listed('/vl/results/getVlFailedResultsDetails.php', [
            'status' => '5,0) OR (1=1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsTreatAQuoteInThePatientNameAsPartOfTheName(): void
    {
        self::assertSame([], $this->listed('/vl/results/getVlFailedResultsDetails.php', [
            'patientName' => "zz%' OR 1=1 OR '", 'status' => '5,7',
        ]));
    }

    #[RunInSeparateProcess]
    public function testApprovalListTreatsAQuoteInTheBatchCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed('/vl/results/getVlResultsForApproval.php', [
            'batchCode' => 'B-ONE%" OR 1=1 OR "',
        ]));
    }

    #[RunInSeparateProcess]
    public function testApprovalListStillFindsAPartOfABatchCode(): void
    {
        self::assertSame(['VL001'], $this->listed('/vl/results/getVlResultsForApproval.php', [
            'batchCode' => 'B-ON',
        ]));
    }
}
