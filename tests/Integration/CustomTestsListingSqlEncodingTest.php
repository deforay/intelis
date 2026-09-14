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
 * The Custom Tests request, result-entry, print, failed and approval listings,
 * driven with filter values that try to rewrite the query.
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
final class CustomTestsListingSqlEncodingTest extends TestCase
{
    private const DATABASE = 'intelis_custom_tests_listing_sql_test';

    private const FACILITY_ID = 11;
    private const OTHER_FACILITY_ID = 12;

    private const TEST_TYPE_ID = 1;
    private const OTHER_TEST_TYPE_ID = 2;

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
            'r_generic_sample_types', 'r_generic_sample_rejection_reasons', 'r_generic_test_reasons',
            'r_test_types', 'r_funding_sources', 'r_implementation_partners',
            'form_generic', 'activity_log', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1, 'instance' => ['type' => 'vluser']]);

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
        $db->rawQuery(
            "INSERT INTO r_test_types
                (test_type_id, test_standard_name, test_generic_name, test_short_code, test_status)
             VALUES (" . self::TEST_TYPE_ID . ", 'Hepatitis E', 'HEV', 'HEV', 'active'),
                    (" . self::OTHER_TEST_TYPE_ID . ", 'Dengue', 'DENV', 'DENV', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO r_generic_sample_types (sample_type_id, sample_type_name, sample_type_status)
             VALUES (1, 'Serum', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO batch_details (batch_id, batch_code, test_type) VALUES (1, 'B-ONE', 'generic-tests')"
        );

        // Two accepted results with nothing printed yet, and one failed test.
        $this->seed([
            'sample_code' => 'GEN001', 'patient_id' => 'PT-7', 'patient_gender' => 'female',
            'sample_batch_id' => 1, 'sample_package_code' => 'MF-1',
        ]);
        $this->seed(['sample_code' => 'GEN002', 'patient_id' => 'PT-8', 'patient_gender' => 'male']);
        $this->seed([
            'sample_code' => 'GEN003', 'patient_id' => 'PT-9', 'patient_gender' => 'male',
            'test_type' => self::OTHER_TEST_TYPE_ID,
            'facility_id' => self::OTHER_FACILITY_ID, 'result' => 'invalid', 'result_status' => 5,
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
            'test_type' => self::TEST_TYPE_ID,
            'specimen_type' => 1,
            'request_created_by' => '1',
            'sample_collection_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'result' => 'negative',
            'result_status' => 7,
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            $values[] = $value === null ? 'NULL' : "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_generic (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
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
                if (preg_match('/GEN00\d/', strip_tags((string) $cell), $m) === 1) {
                    $codes[] = $m[0];
                    break;
                }
            }
        }
        sort($codes);
        return $codes;
    }

    #[RunInSeparateProcess]
    public function testRequestListCastsTheTestTypeToAnId(): void
    {
        // "2 OR 1=1" used to follow `vl.test_type like` unquoted and list every sample.
        self::assertSame(['GEN003'], $this->listed('/generic-tests/requests/get-request-list.php', [
            'testType' => self::OTHER_TEST_TYPE_ID . ' OR 1=1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListStillFiltersByTestType(): void
    {
        self::assertSame(['GEN001', 'GEN002'], $this->listed('/generic-tests/requests/get-request-list.php', [
            'testType' => (string) self::TEST_TYPE_ID,
        ]));
    }

    #[RunInSeparateProcess]
    public function testApprovalListTreatsAQuoteInTheBatchCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed('/generic-tests/results/get-generic-results-for-approval.php', [
            'batchCode' => 'B-ONE" OR "1"="1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testApprovalListStillFindsABatch(): void
    {
        self::assertSame(['GEN001'], $this->listed('/generic-tests/results/get-generic-results-for-approval.php', [
            'batchCode' => 'B-ONE',
        ]));
    }

    #[RunInSeparateProcess]
    public function testApprovalListKeepsOnlyTheNumbersOfAFacilityList(): void
    {
        self::assertSame(['GEN003'], $this->listed('/generic-tests/results/get-generic-results-for-approval.php', [
            'facilityName' => self::OTHER_FACILITY_ID . ',0) OR (1=1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testApprovalListDiscardsAnythingButAscOrDescAsTheSortDirection(): void
    {
        $approval = '/generic-tests/results/get-generic-results-for-approval.php';
        self::assertSame(['GEN001', 'GEN002', 'GEN003'], $this->listed($approval, [
            'iSortCol_0' => 0, 'iSortingCols' => 1, 'bSortable_0' => 'true',
            'sSortDir_0' => 'asc, (SELECT 1 FROM form_generic WHERE 1=1 UNION SELECT 2)',
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsKeepOnlyTheNumbersOfAStatusList(): void
    {
        // "7,0) OR (1=1" used to widen the status filter to every row; as an id
        // list it is 7, the two accepted samples.
        $failed = '/generic-tests/results/get-generic-failed-results-details.php';
        self::assertSame(['GEN001', 'GEN002'], $this->listed($failed, [
            'status' => '7,0) OR (1=1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsTreatAQuoteInTheManifestCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed('/generic-tests/results/get-generic-failed-results-details.php', [
            'status' => '7', 'manifestCode' => 'MF-1" OR "1"="1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultEntryTreatsAQuoteInThePatientNameAsPartOfTheName(): void
    {
        self::assertSame([], $this->listed('/generic-tests/results/get-manual-results.php', [
            'patientName' => "x' OR 1=1 OR '", 'from' => 'enterresult',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultEntryStillFindsAPatientId(): void
    {
        self::assertSame(['GEN002'], $this->listed('/generic-tests/results/get-manual-results.php', [
            'patientId' => 'PT-8', 'from' => 'enterresult',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultEntryTreatsAQuoteInTheSexAsPartOfTheValue(): void
    {
        self::assertSame([], $this->listed('/generic-tests/results/get-manual-results.php', [
            'gender' => 'female" OR "1"="1', 'from' => 'enterresult',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListTreatsAQuoteInThePatientIdAsPartOfTheId(): void
    {
        self::assertSame([], $this->listed('/generic-tests/results/get-generic-test-result-details.php', [
            'artNo' => "PT' OR 1=1 OR '", 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListStillFindsABatch(): void
    {
        self::assertSame(['GEN001'], $this->listed('/generic-tests/results/get-generic-test-result-details.php', [
            'batchCode' => 'B-ONE', 'vlPrint' => 'not-print',
        ]));
    }
}
