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
 * The COVID-19 request, result-entry, print, failed and result-status listings,
 * driven with filter values that try to rewrite the query.
 *
 * These endpoints assemble WHERE clauses as strings. Each filter used to be
 * concatenated straight from $_POST, so a value that closes its quote and adds
 * `OR 1=1` listed every sample instead of none. Three samples are seeded; an
 * injected filter must list none of them (or only the ones a cast leaves), and a
 * legitimate filter on the same field must still find its sample.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can drive
 * it once: every test runs in its own process and drives exactly once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class Covid19ListingSqlEncodingTest extends TestCase
{
    private const DATABASE = 'intelis_covid19_listing_sql_test';

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
            'r_countries', 'r_covid19_results', 'r_covid19_test_reasons', 'r_covid19_sample_type',
            'r_covid19_sample_rejection_reasons', 'r_funding_sources', 'r_implementation_partners',
            'form_covid19', 'covid19_tests', 'activity_log', 'system_config', 'global_config',
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
        $db->rawQuery("INSERT INTO batch_details (batch_id, batch_code, test_type) VALUES (1, 'B-ONE', 'covid19')");

        // Two accepted results with nothing printed yet, and one failed test.
        $this->seed([
            'sample_code' => 'C19001', 'patient_id' => 'PT-7', 'patient_name' => 'Ada', 'patient_gender' => 'female',
            'sample_batch_id' => 1, 'sample_package_code' => 'MF-1',
        ]);
        $this->seed([
            'sample_code' => 'C19002', 'patient_id' => 'PT-8', 'patient_name' => 'Ben', 'patient_gender' => 'male',
        ]);
        $this->seed([
            'sample_code' => 'C19003', 'patient_id' => 'PT-9', 'patient_name' => 'Cy', 'patient_gender' => 'male',
            'facility_id' => self::OTHER_FACILITY_ID, 'lab_id' => self::OTHER_FACILITY_ID,
            'result' => 'invalid', 'result_status' => 5,
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
            'lab_id' => self::FACILITY_ID,
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
            "INSERT INTO form_covid19 (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
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
                if (preg_match('/C1900\d/', strip_tags((string) $cell), $m) === 1) {
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
        self::assertSame([], $this->listed('/covid-19/requests/get-request-list.php', [
            'batchCode' => 'B-ONE" OR "1"="1', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListStillFindsAPatientId(): void
    {
        self::assertSame(['C19001'], $this->listed('/covid-19/requests/get-request-list.php', [
            'patientId' => 'PT-7', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListTreatsAQuoteInTheDistrictAsPartOfTheValue(): void
    {
        self::assertSame([], $this->listed('/covid-19/requests/get-request-list.php', [
            'district' => "0' OR '1'='1", 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListKeepsOnlyTheNumbersOfAFacilityList(): void
    {
        self::assertSame(['C19001', 'C19002'], $this->listed('/covid-19/requests/get-request-list.php', [
            'facilityName' => self::FACILITY_ID . ',0) OR (1=1', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListSearchBoxCannotCloseItsQuote(): void
    {
        self::assertSame([], $this->listed('/covid-19/requests/get-request-list.php', [
            'sSearch' => "zz' OR '1'='1", 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListTreatsAQuoteInTheSexAsPartOfTheValue(): void
    {
        self::assertSame([], $this->listed('/covid-19/requests/get-request-list.php', [
            'gender' => 'female") OR ("1"="1', 'hidesrcofreq' => '',
        ]));
    }

    /**
     * The "unreported" sex clause was pushed without parentheses, so its ORs split
     * the WHERE: a sample with a blank sex from any other facility was listed too.
     */
    #[RunInSeparateProcess]
    public function testRequestListUnreportedSexStaysInsideTheOtherFilters(): void
    {
        $this->seed([
            'sample_code' => 'C19004', 'patient_id' => 'PT-10', 'patient_name' => 'Di', 'patient_gender' => '',
            'facility_id' => self::OTHER_FACILITY_ID, 'lab_id' => self::OTHER_FACILITY_ID,
        ]);

        self::assertSame([], $this->listed('/covid-19/requests/get-request-list.php', [
            'facilityName' => (string) self::FACILITY_ID, 'gender' => 'unreported', 'hidesrcofreq' => '',
        ]));
    }

    /**
     * The confirmatory tests grid lists samples with a positive test. Its totals
     * came from a different query, and with a search term the "result" status
     * filter named a column `vl.` that does not exist.
     */
    #[RunInSeparateProcess]
    public function testConfirmatoryTestsListThePositiveSampleWithItsSearchAndStatus(): void
    {
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO covid19_tests (covid19_id, test_name, result) VALUES (1, 'PCR', 'positive')"
        );

        self::assertSame(['C19001'], $this->listed('/covid-19/results/get-record-confirmatory-tests.php', [
            'sSearch' => 'C19001', 'status' => 'result',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListDiscardsAnythingButAscOrDescAsTheSortDirection(): void
    {
        self::assertSame(['C19001', 'C19002', 'C19003'], $this->listed('/covid-19/requests/get-request-list.php', [
            'iSortCol_0' => 0, 'iSortingCols' => 1, 'bSortable_0' => 'true',
            'sSortDir_0' => 'asc, (SELECT 1 FROM form_covid19 WHERE 1=1 UNION SELECT 2)',
            'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsKeepOnlyTheNumbersOfAStatusList(): void
    {
        // "5,0) OR (1=1" used to widen the status filter to every row; as an id
        // list it is 5, the one failed test, and a piece that is not a number.
        self::assertSame(['C19003'], $this->listed('/covid-19/results/get-failed-results.php', [
            'status' => '5,0) OR (1=1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsTreatAQuoteInThePatientNameAsPartOfTheName(): void
    {
        self::assertSame([], $this->listed('/covid-19/results/get-failed-results.php', [
            'status' => '5', 'patientName' => "Cy%' OR '1'='1",
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListTreatsAQuoteInThePatientIdAsPartOfTheId(): void
    {
        self::assertSame([], $this->listed('/covid-19/results/get-results-for-print.php', [
            'patientId' => 'PT" OR 1=1 OR "', 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListStillFindsAPatientId(): void
    {
        self::assertSame(['C19002'], $this->listed('/covid-19/results/get-results-for-print.php', [
            'patientId' => 'PT-8', 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultEntryTreatsAQuoteInTheManifestCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed('/covid-19/results/covid-19-samples-for-manual-result-entry.php', [
            'manifestCode' => 'MF-1" OR "1"="1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultEntryStillFindsAManifest(): void
    {
        self::assertSame(['C19001'], $this->listed('/covid-19/results/covid-19-samples-for-manual-result-entry.php', [
            'manifestCode' => 'MF-1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testResultStatusTreatsAQuoteInTheBatchCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed('/covid-19/results/get-covid-19-result-status.php', [
            'batchCode' => 'B-ONE%" OR "1"="1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testResultStatusKeepsOnlyTheNumbersOfAFacilityList(): void
    {
        self::assertSame(['C19003'], $this->listed('/covid-19/results/get-covid-19-result-status.php', [
            'facilityName' => self::OTHER_FACILITY_ID . ',0) OR (1=1',
        ]));
    }
}
