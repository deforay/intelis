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
 * The Hepatitis request, result-entry, print, failed and result-status listings, driven
 * with filter values that try to rewrite the query.
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
final class HepatitisListingSqlEncodingTest extends TestCase
{
    private const DATABASE = 'intelis_hepatitis_listing_sql_test';

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
            'r_countries', 'r_hepatitis_test_reasons', 'r_hepatitis_sample_type',
            'r_hepatitis_sample_rejection_reasons', 'r_funding_sources', 'r_implementation_partners',
            'r_hepatitis_results', 'form_hepatitis', 'activity_log', 'system_config', 'global_config',
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
        $db->rawQuery("INSERT INTO batch_details (batch_id, batch_code, test_type) VALUES (1, 'B-ONE', 'hepatitis')");

        // An accepted result with nothing printed yet, a sample at the lab still
        // waiting for its counts, and one failed test.
        $this->seed([
            'sample_code' => 'HEP001', 'patient_id' => 'P-7', 'patient_gender' => 'female',
            'sample_batch_id' => 1, 'sample_package_code' => 'MF-1',
        ]);
        $this->seed([
            'sample_code' => 'HEP002', 'patient_id' => 'P-8', 'patient_gender' => 'male',
            'hcv_vl_count' => null, 'hbv_vl_count' => null, 'result_status' => 6,
        ]);
        $this->seed([
            'sample_code' => 'HEP003', 'patient_id' => 'P-9', 'patient_gender' => 'male',
            'facility_id' => self::OTHER_FACILITY_ID, 'result_status' => 5,
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
            'sample_collection_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'hcv_vl_count' => '1200',
            'hbv_vl_count' => '300',
            'result_status' => 7,
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            $values[] = $value === null ? 'NULL' : "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_hepatitis (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
        );
    }

    /**
     * @param array<string, mixed> $post
     * @return list<string> the sample codes listed, in order
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
                if (preg_match('/HEP00\d/', strip_tags((string) $cell), $m) === 1) {
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
        self::assertSame([], $this->listed('/hepatitis/requests/get-request-list.php', [
            'batchCode' => 'B-ONE" OR "1"="1', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListStillFindsAPatientId(): void
    {
        self::assertSame(['HEP001'], $this->listed('/hepatitis/requests/get-request-list.php', [
            'patientId' => 'P-7', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListTreatsAQuoteInTheSexAsPartOfTheValue(): void
    {
        self::assertSame([], $this->listed('/hepatitis/requests/get-request-list.php', [
            'gender' => 'female") OR ("1"="1', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListTreatsAQuoteInADecodedImplementingPartnerAsPartOfTheValue(): void
    {
        self::assertSame([], $this->listed('/hepatitis/requests/get-request-list.php', [
            'implementingPartner' => base64_encode('1") OR ("1"="1'), 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListKeepsOnlyTheNumbersOfAFacilityList(): void
    {
        self::assertSame(['HEP003'], $this->listed('/hepatitis/requests/get-request-list.php', [
            'facilityName' => self::OTHER_FACILITY_ID . ',0) OR (1=1', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsKeepOnlyTheNumbersOfAStatusList(): void
    {
        // "5,0) OR (1=1" used to widen the status filter to every row; as an id
        // list it is 5, the one failed test, and a piece that is not a number.
        self::assertSame(['HEP003'], $this->listed('/hepatitis/results/get-failed-results.php', [
            'status' => '5,0) OR (1=1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsSearchBoxCannotCloseItsQuote(): void
    {
        self::assertSame([], $this->listed('/hepatitis/results/get-failed-results.php', [
            'sSearch' => "zz' OR '1'='1",
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsDiscardAnythingButAscOrDescAsTheSortDirection(): void
    {
        self::assertSame(['HEP003'], $this->listed('/hepatitis/results/get-failed-results.php', [
            'iSortCol_0' => 0, 'iSortingCols' => 1, 'bSortable_0' => 'true',
            'sSortDir_0' => 'asc, (SELECT 1 FROM form_hepatitis WHERE 1=1 UNION SELECT 2)',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListTreatsAQuoteInThePatientIdAsPartOfTheId(): void
    {
        self::assertSame([], $this->listed('/hepatitis/results/get-results-for-print.php', [
            'patientId' => 'P" OR 1=1 OR "', 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListStillFindsAManifest(): void
    {
        self::assertSame(['HEP001'], $this->listed('/hepatitis/results/get-results-for-print.php', [
            'manifestCode' => 'MF-1', 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultEntryTreatsAQuoteInTheBatchCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed('/hepatitis/results/hepatitis-samples-for-manual-result-entry.php', [
            'batchCode' => 'B-ONE" OR "1"="1', 'status' => 'no_result',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultEntryStillFindsAFacility(): void
    {
        self::assertSame(['HEP002'], $this->listed('/hepatitis/results/hepatitis-samples-for-manual-result-entry.php', [
            'facilityName' => (string) self::FACILITY_ID, 'status' => 'no_result',
        ]));
    }

    #[RunInSeparateProcess]
    public function testResultStatusKeepsOnlyTheNumbersOfAFacilityList(): void
    {
        self::assertSame(['HEP003'], $this->listed('/hepatitis/results/get-hepatitis-result-status.php', [
            'facilityName' => self::OTHER_FACILITY_ID . ',0) OR (1=1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testResultStatusTreatsAQuoteInTheManifestCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed('/hepatitis/results/get-hepatitis-result-status.php', [
            'manifestCode' => 'MF-1" OR "1"="1',
        ]));
    }
}
