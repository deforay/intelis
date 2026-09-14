<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Services\SampleStatusDetailsService;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The sample status drilldown for the modules other than VL.
 *
 * Each module keeps its samples in its own table with its own lookups, and a
 * few differ in ways the listing has to respect: EID names the patient by
 * child_id and stores the sample type as text, Hepatitis can carry a count
 * with no interpreted result, TB and Custom Tests record a referral in their
 * own columns, and the Custom Tests pie counts cancelled samples as a slice.
 *
 * Every test runs in its own process and drives the handler once.
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SampleStatusDetailsModulesTest extends TestCase
{
    private const DATABASE = 'intelis_sample_status_modules_test';

    private const LAB_ID = 701;
    private const REFERRING_LAB_ID = 702;
    private const FACILITY_ID = 703;

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

        $db = LegacyAppHarness::boot(self::DATABASE, [
            'r_sample_status', 'facility_details', 'batch_details', 'roles', 'user_details',
            'r_eid_sample_type', 'r_tb_sample_type', 'r_cd4_sample_types', 'r_hepatitis_sample_type',
            'r_generic_sample_types',
            'r_eid_sample_rejection_reasons', 'r_tb_sample_rejection_reasons', 'r_cd4_sample_rejection_reasons',
            'r_hepatitis_sample_rejection_reasons', 'r_generic_sample_rejection_reasons',
            'r_test_failure_reasons', 'r_vl_test_failure_reasons', 'r_generic_test_failure_reasons',
            'form_eid', 'form_tb', 'form_cd4', 'form_hepatitis', 'form_generic',
            'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_state, facility_district, facility_type, vlsm_instance_id, status)
             VALUES (" . self::LAB_ID . ", 'Central Lab', 'North', 'Capital', 2, 'test', 'active'),
                    (" . self::REFERRING_LAB_ID . ", 'Referring Lab', 'South', 'Coast', 2, 'test', 'active'),
                    (" . self::FACILITY_ID . ", 'Riverside Clinic', 'North', 'Capital', 1, 'test', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name, status) VALUES
                (4, 'Rejected', 'active'), (6, 'Sample Registered at Testing Lab', 'active'),
                (7, 'Accepted', 'active'), (12, 'Cancelled', 'active'), (13, 'Referred', 'active')"
        );
        $db->rawQuery("INSERT INTO r_eid_sample_type (sample_id, sample_name, status) VALUES (2, 'DBS', 'active')");
        $db->rawQuery(
            "INSERT INTO r_generic_sample_types (sample_type_id, sample_type_code, sample_type_name, sample_type_status)
             VALUES (5, 'SW', 'Swab', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO r_eid_sample_rejection_reasons
                (rejection_reason_id, rejection_reason_name, rejection_type, rejection_reason_status)
             VALUES (3, 'Insufficient blood', 'general', 'active')"
        );
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $columns */
    private function seed(string $table, array $columns): void
    {
        static $sequence = 0;
        $sequence++;

        $row = $columns + [
            'unique_id' => 'uid-' . $sequence,
            'vlsm_instance_id' => 'test',
            'vlsm_country_id' => 1,
            'sample_code' => 'S' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
            'facility_id' => self::FACILITY_ID,
            'lab_id' => self::LAB_ID,
            'sample_collection_date' => date('Y-m-d H:i:s', strtotime('-30 days')),
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            $values[] = "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO $table (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
        );
    }

    /** @return array<string, mixed> */
    private function drive(array $post): array
    {
        $request = LegacyAppHarness::withPost($post + [
            'draw' => 1, 'start' => 0, 'length' => 25,
        ], '/reports/get-sample-status-details.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();
        $json = json_decode($body, true);
        self::assertIsArray($json, "Endpoint did not return JSON: $body");
        return $json;
    }

    /** @return list<array<string, string>> rows keyed by column */
    private static function rows(string $testType, int $status, array $json): array
    {
        $keys = array_keys(SampleStatusDetailsService::columns($status, $testType));
        return array_map(static fn(array $row): array => array_combine($keys, $row), $json['data']);
    }

    #[RunInSeparateProcess]
    public function testEidRejectionsReadTheEidTableAndItsOwnReasons(): void
    {
        $this->seed('form_eid', [
            'result_status' => 4, 'child_id' => 'CH-1', 'specimen_type' => '2',
            'reason_for_sample_rejection' => 3, 'is_sample_rejected' => 'yes',
        ]);
        // Same status, other sample type: the text filter must leave it out.
        $this->seed('form_eid', ['result_status' => 4, 'child_id' => 'CH-2', 'specimen_type' => '9']);

        $json = $this->drive(['testType' => 'eid', 'status' => 4, 'sampleType' => '2']);
        self::assertSame(1, $json['recordsTotal'], json_encode($json));

        $row = self::rows('eid', 4, $json)[0];
        self::assertSame('CH-1', $row['patientId'], 'EID names the patient by child_id');
        self::assertSame('DBS', $row['sampleType']);
        self::assertSame('Insufficient blood', $row['rejectionReason']);
        self::assertArrayNotHasKey('rejectedBy', $row, 'form_eid records no rejecting facility');
    }

    #[RunInSeparateProcess]
    public function testCustomTestsListCancelledSamplesBecauseTheirPieCountsThem(): void
    {
        $this->seed('form_generic', ['result_status' => 12, 'specimen_type' => 5, 'request_created_by' => 'u1']);

        $json = $this->drive(['testType' => 'generic-tests', 'status' => 12]);
        self::assertSame(1, $json['recordsTotal'], json_encode($json));
        self::assertSame('Swab', self::rows('generic-tests', 12, $json)[0]['sampleType']);
    }

    #[RunInSeparateProcess]
    public function testEveryOtherModuleLeavesCancelledSamplesOut(): void
    {
        $this->seed('form_eid', ['result_status' => 12]);

        $json = $this->drive(['testType' => 'eid', 'status' => 12]);
        self::assertSame(0, $json['recordsTotal']);
    }

    #[RunInSeparateProcess]
    public function testHepatitisShowsTheCountsWhenNoResultWasInterpreted(): void
    {
        $this->seed('form_hepatitis', ['result_status' => 7, 'hcv_vl_count' => '1200', 'hbv_vl_count' => '']);

        $json = $this->drive(['testType' => 'hepatitis', 'status' => 7]);
        self::assertSame('HCV: 1200', self::rows('hepatitis', 7, $json)[0]['result'], json_encode($json));
    }

    #[RunInSeparateProcess]
    public function testCd4ShowsItsOwnResultColumn(): void
    {
        $this->seed('form_cd4', ['result_status' => 7, 'cd4_result' => '350']);

        $json = $this->drive(['testType' => 'cd4', 'status' => 7]);
        self::assertSame('350', self::rows('cd4', 7, $json)[0]['result'], json_encode($json));
    }

    #[RunInSeparateProcess]
    public function testTbReferralsNameTheLabAndTheReferralManifest(): void
    {
        $this->seed('form_tb', [
            'result_status' => 13, 'sample_code_key' => 1,
            'referred_by_lab_id' => self::REFERRING_LAB_ID, 'referral_manifest_code' => 'REF-9',
            'sample_package_code' => 'PKG-1',
        ]);

        $json = $this->drive(['testType' => 'tb', 'status' => 13]);
        $row = self::rows('tb', 13, $json)[0];
        self::assertSame('Referring Lab', $row['referringLab'], json_encode($json));
        self::assertSame('REF-9', $row['manifestCode']);
    }

    #[RunInSeparateProcess]
    public function testAUserWithOnlyTheEidModuleCanListEidSamples(): void
    {
        LegacyAppHarness::withSession(['roleId' => 5, 'privileges' => [], 'modules' => ['eid' => 'eid']]);
        $this->seed('form_eid', ['result_status' => 6]);

        $json = $this->drive(['testType' => 'eid', 'status' => 6]);
        self::assertSame(1, $json['recordsTotal'], json_encode($json));
    }

    #[RunInSeparateProcess]
    public function testTheEidPieLinksEachSliceToTheEidListing(): void
    {
        $this->seed('form_eid', ['result_status' => 6]);
        $this->seed('form_eid', ['result_status' => 6]);

        $request = LegacyAppHarness::withPost(
            ['labName' => self::LAB_ID, 'sampleCollectionDate' => ''],
            '/eid/management/getSampleStatus.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();

        self::assertSame(
            1,
            preg_match('/y:\s*(\d+),\s*color:\s*\'#[0-9a-f]{6}\',\s*url:\s*"([^"]*)"/', $body, $slice),
            $body
        );
        self::assertSame('2', $slice[1]);
        $link = json_decode('"' . $slice[2] . '"');
        self::assertStringStartsWith('/reports/sample-status-details.php?testType=eid&status=6&', $link);
        self::assertStringNotContainsString('vlTestResultStatus', $body);
    }

    #[RunInSeparateProcess]
    public function testEveryTestTypeTheServiceKnowsHasColumnsForEveryStatus(): void
    {
        foreach (SampleStatusDetailsService::testTypes() as $testType) {
            foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13] as $status) {
                $columns = SampleStatusDetailsService::columns($status, $testType);
                self::assertArrayHasKey('sampleCode', $columns, "$testType / $status");
            }
        }
    }
}
