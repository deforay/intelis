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
 * The CD4 request, result-entry, print, failed and result-status listings, driven
 * with filter values that try to rewrite the query.
 *
 * These endpoints assemble WHERE clauses as strings, and each filter used to be
 * concatenated straight from $_POST. A value that closed its quote and added
 * `OR "1"="1"` turned the whole WHERE into a tautology and listed every sample.
 * Three samples are seeded; an injected filter must list none of them (or only the
 * ones the numeric part of an id list names), and a legitimate filter on the same
 * field must still find its sample.
 *
 * form_cd4 is empty in every copy of a lab database, so this test is the proof that
 * the encoded listings still match the rows they matched before.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can drive
 * it once: every test runs in its own process and drives exactly once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class Cd4ListingSqlEncodingTest extends TestCase
{
    private const DATABASE = 'intelis_cd4_listing_sql_test';

    private const FACILITY_ID = 11;
    private const OTHER_FACILITY_ID = 12;
    private const LAB_ID = 21;

    private const REQUESTS = '/cd4/requests/get-request-list.php';
    private const MANUAL_RESULTS = '/cd4/results/get-manual-results.php';
    private const PRINT_RESULTS = '/cd4/results/get-results-for-print.php';
    private const FAILED_RESULTS = '/cd4/results/get-failed-results.php';
    private const RESULT_STATUS = '/cd4/results/get-cd4-result-status.php';

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
            'r_cd4_sample_types', 'r_cd4_sample_rejection_reasons', 'r_cd4_test_reasons',
            'r_funding_sources', 'r_implementation_partners', 'r_vl_art_regimen',
            'form_cd4', 'activity_log', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_state, facility_state_id, facility_district,
                 facility_district_id, facility_type, vlsm_instance_id, status)
             VALUES (" . self::FACILITY_ID . ", 'Riverside Clinic', 'North', 31, 'Capital', 41, 1, 'test', 'active'),
                    (" . self::OTHER_FACILITY_ID . ", 'Hilltop Clinic', 'South', 32, 'Coast', 42, 1, 'test', 'active'),
                    (" . self::LAB_ID . ", 'Central Lab', 'North', 31, 'Capital', 41, 2, 'test', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name) VALUES
                (1, 'On Hold'), (4, 'Rejected'), (5, 'Test Failed'), (6, 'Received at Testing Lab'),
                (7, 'Accepted'), (9, 'Received at Clinic')"
        );
        $db->rawQuery("INSERT INTO batch_details (batch_id, batch_code, test_type) VALUES (1, 'B-ONE', 'cd4')");

        // Two accepted results with nothing printed yet, and one failed test at
        // another facility. The failed test carries a result so the result-status
        // listing, which needs one, shows all three.
        $this->seed([
            'sample_code' => 'CD4001', 'patient_art_no' => 'ART-7', 'patient_first_name' => 'Alice',
            'patient_gender' => 'female', 'sample_batch_id' => 1, 'sample_package_code' => 'MF-1',
            'funding_source' => 3,
        ]);
        $this->seed([
            'sample_code' => 'CD4002', 'patient_art_no' => 'ART-8', 'patient_first_name' => 'Bruno',
            'patient_gender' => 'male',
        ]);
        $this->seed([
            'sample_code' => 'CD4003', 'patient_art_no' => 'ART-9', 'patient_first_name' => 'Chidi',
            'patient_gender' => 'male', 'facility_id' => self::OTHER_FACILITY_ID,
            'cd4_result' => 'failed', 'result_status' => 5,
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
            'lab_id' => self::LAB_ID,
            'sample_collection_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'cd4_result' => '350',
            'result_status' => 7,
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            $values[] = $value === null ? 'NULL' : "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_cd4 (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
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
                if (preg_match('/CD400\d/', strip_tags((string) $cell), $m) === 1) {
                    $codes[] = $m[0];
                    break;
                }
            }
        }
        sort($codes);
        return $codes;
    }

    // Request list

    #[RunInSeparateProcess]
    public function testRequestListWithoutFiltersListsEverySample(): void
    {
        // The baseline the injection cases below would have reproduced.
        self::assertSame(['CD4001', 'CD4002', 'CD4003'], $this->listed(self::REQUESTS, ['hidesrcofreq' => '']));
    }

    #[RunInSeparateProcess]
    public function testRequestListTreatsAQuoteInTheBatchCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed(self::REQUESTS, [
            'batchCode' => 'B-ONE" OR "1"="1', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListStillFindsABatch(): void
    {
        self::assertSame(['CD4001'], $this->listed(self::REQUESTS, [
            'batchCode' => 'B-ONE', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListKeepsOnlyTheNumbersOfAFacilityList(): void
    {
        self::assertSame(['CD4001', 'CD4002'], $this->listed(self::REQUESTS, [
            'facilityName' => self::FACILITY_ID . ',0) OR (1=1', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListMatchesThePatientIdAsAPatternWithoutLeavingIt(): void
    {
        self::assertSame(['CD4001'], $this->listed(self::REQUESTS, [
            'patientId' => 'ART-7', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListTreatsAQuoteInThePatientIdAsPartOfTheId(): void
    {
        self::assertSame([], $this->listed(self::REQUESTS, [
            'patientId' => 'ART-7" OR "1"="1', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListTreatsAQuoteInThePatientNameAsPartOfTheName(): void
    {
        self::assertSame([], $this->listed(self::REQUESTS, [
            'patientName' => "zz' OR 1=1 OR '", 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListFiltersBySexWithoutLeavingTheValue(): void
    {
        self::assertSame(['CD4001'], $this->listed(self::REQUESTS, [
            'gender' => 'female', 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListTreatsADecodedFundingSourceAsOneValue(): void
    {
        self::assertSame([], $this->listed(self::REQUESTS, [
            // funding_source is an int column, so the escaped string still casts to its
            // leading number; 0 names no funding source.
            'fundingSource' => base64_encode('0") OR ("1"="1'), 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListStillFindsAFundingSource(): void
    {
        self::assertSame(['CD4001'], $this->listed(self::REQUESTS, [
            'fundingSource' => base64_encode('3'), 'hidesrcofreq' => '',
        ]));
    }

    #[RunInSeparateProcess]
    public function testRequestListCastsThePagingValues(): void
    {
        // "0,25 UNION ..." used to land in LIMIT verbatim.
        self::assertSame(['CD4001', 'CD4002', 'CD4003'], $this->listed(self::REQUESTS, [
            'iDisplayStart' => '0', 'iDisplayLength' => '25 UNION SELECT 1', 'hidesrcofreq' => '',
        ]));
    }

    // Result entry

    #[RunInSeparateProcess]
    public function testManualResultsSearchBoxCannotCloseItsQuote(): void
    {
        self::assertSame([], $this->listed(self::MANUAL_RESULTS, [
            'sSearch' => "zz' OR 1=1 OR '",
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultsSearchBoxStillFindsASample(): void
    {
        self::assertSame(['CD4002'], $this->listed(self::MANUAL_RESULTS, [
            'sSearch' => 'ART-8',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultsDiscardAnythingButAscOrDescAsTheSortDirection(): void
    {
        self::assertSame(['CD4001', 'CD4002'], $this->listed(self::MANUAL_RESULTS, [
            'iSortCol_0' => 0, 'iSortingCols' => 1, 'bSortable_0' => 'true',
            'sSortDir_0' => 'asc, (SELECT 1 FROM form_cd4 WHERE 1=1 UNION SELECT 2)',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultsTreatAQuoteInTheManifestCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed(self::MANUAL_RESULTS, [
            'manifestCode' => 'MF-1" OR "1"="1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultsStillFindAManifest(): void
    {
        self::assertSame(['CD4001'], $this->listed(self::MANUAL_RESULTS, [
            'manifestCode' => 'MF-1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testManualResultsTreatAQuoteInTheArtNumberAsPartOfTheNumber(): void
    {
        self::assertSame([], $this->listed(self::MANUAL_RESULTS, [
            'artNo' => "ART' OR 1=1 OR '",
        ]));
    }

    // Print

    #[RunInSeparateProcess]
    public function testPrintListKeepsOnlyTheNumbersOfATestingLabList(): void
    {
        self::assertSame([], $this->listed(self::PRINT_RESULTS, [
            'vlLab' => '0) OR (1=1', 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListStillFiltersByTestingLab(): void
    {
        self::assertSame(['CD4001', 'CD4002'], $this->listed(self::PRINT_RESULTS, [
            'vlLab' => (string) self::LAB_ID, 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListTreatsAQuoteInThePatientNameAsPartOfTheName(): void
    {
        self::assertSame([], $this->listed(self::PRINT_RESULTS, [
            'patientName' => "zz' OR 1=1 OR '", 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListStillFindsAPatientName(): void
    {
        self::assertSame(['CD4002'], $this->listed(self::PRINT_RESULTS, [
            'patientName' => 'Bruno', 'vlPrint' => 'not-print',
        ]));
    }

    #[RunInSeparateProcess]
    public function testPrintListTreatsAQuoteInTheSexAsPartOfTheValue(): void
    {
        self::assertSame([], $this->listed(self::PRINT_RESULTS, [
            'gender' => 'female" OR "1"="1', 'vlPrint' => 'not-print',
        ]));
    }

    // Failed results

    #[RunInSeparateProcess]
    public function testFailedResultsKeepOnlyTheNumbersOfAStatusList(): void
    {
        // As an id list, "5,0) OR (1=1" is 5, the one failed test.
        self::assertSame(['CD4003'], $this->listed(self::FAILED_RESULTS, [
            'status' => '5,0) OR (1=1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsSearchBoxCannotCloseItsQuote(): void
    {
        self::assertSame([], $this->listed(self::FAILED_RESULTS, [
            'sSearch' => "zz' OR 1=1 OR '",
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsTreatAQuoteInThePatientNameAsPartOfTheName(): void
    {
        self::assertSame([], $this->listed(self::FAILED_RESULTS, [
            'patientName' => "zz' OR 1=1 OR '",
        ]));
    }

    #[RunInSeparateProcess]
    public function testFailedResultsStillFilterByDistrict(): void
    {
        self::assertSame(['CD4003'], $this->listed(self::FAILED_RESULTS, [
            'district' => '42',
        ]));
    }

    // Result status

    #[RunInSeparateProcess]
    public function testResultStatusTreatsAQuoteInTheBatchCodeAsPartOfTheCode(): void
    {
        self::assertSame([], $this->listed(self::RESULT_STATUS, [
            'batchCode' => 'ONE%" OR 1=1 OR "',
        ]));
    }

    #[RunInSeparateProcess]
    public function testResultStatusStillFindsPartOfABatchCode(): void
    {
        self::assertSame(['CD4001'], $this->listed(self::RESULT_STATUS, [
            'batchCode' => 'ONE',
        ]));
    }

    #[RunInSeparateProcess]
    public function testResultStatusKeepsOnlyTheNumbersOfAFacilityList(): void
    {
        // Unencoded, "11,0) OR (1=1" listed the other facility's sample as well.
        self::assertSame(['CD4001', 'CD4002'], $this->listed(self::RESULT_STATUS, [
            'facilityName' => self::FACILITY_ID . ',0) OR (1=1',
        ]));
    }
}
