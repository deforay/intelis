<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;

/**
 * Driving the InteLIS Mobile results endpoints under app/api/v1.1 end to end.
 *
 * These are the calls the app makes on every poll and every search, and until now
 * nothing ran them. They are procedural files, so the only way to run one is the
 * way the API runs it: LegacyRequestHandler requires the file with the request in
 * AppRegistry and output buffered. This drives that handler against a real
 * database built from sql/init.sql, with a bearer token the file resolves itself.
 *
 * One drive per test: the handler uses require_once, so a second drive of the
 * same file in one process silently does nothing.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiResultsEndpointTest extends TestCase
{
    private const DATABASE = 'intelis_api_results_test';

    private const TOKEN = 'test-api-token';

    private const USER = 'user-api-1';

    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config',
        'roles', 'user_details', 'user_facility_map', 'facility_details',
        'geographical_divisions', 'batch_details', 'r_sample_status',
        'r_funding_sources', 'r_implementation_partners', 'track_api_requests',
        'form_vl', 'r_vl_sample_rejection_reasons', 'r_vl_sample_type', 'r_vl_test_reasons',
        'form_eid', 'r_eid_sample_rejection_reasons', 'r_eid_sample_type', 'r_eid_test_reasons',
        'form_covid19', 'covid19_tests', 'covid19_patient_symptoms', 'covid19_patient_comorbidities',
        'covid19_reasons_for_testing', 'r_covid19_test_reasons', 'r_covid19_sample_rejection_reasons',
        'r_covid19_sample_type', 'r_countries',
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

        // ApiService reads VERSION at construction; the app defines it in bootstrap.
        require_once ROOT_PATH . '/app/system/version.php';

        // Config lookups are memoised on disk; a stale entry from an earlier run
        // would answer for this database.
        self::clearFileCache();

        LegacyAppHarness::boot(self::DATABASE, self::TABLES);
        $this->seedInstanceAndUser();
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
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

    private function seedInstanceAndUser(): void
    {
        $db = LegacyAppHarness::db();
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'test-instance']);
        // form_vl.result_status is a foreign key; the other form tables carry none.
        $db->insert('r_sample_status', ['status_id' => 7, 'status_name' => 'Accepted', 'status' => 'active']);
        $db->insert('system_config', ['display_name' => 'Instance type', 'name' => 'sc_user_type', 'value' => 'vlsm']);
        $db->insert('roles', [
            'role_id' => 1,
            'role_name' => 'Lab',
            'role_code' => 'lab',
            'access_type' => 'testing-lab',
        ]);
        $db->insert('user_details', [
            'user_id' => self::USER,
            'user_name' => 'API User',
            'login_id' => 'api',
            'api_token' => self::TOKEN,
            'status' => 'active',
            'role_id' => 1,
            'app_access' => 'yes',
        ]);
        // The user sees facility 1 only; a row at facility 2 must never come back.
        $db->insert('user_facility_map', ['user_id' => self::USER, 'facility_id' => 1]);
        foreach ([1, 2] as $facilityId) {
            $db->insert('facility_details', [
                'facility_id' => $facilityId,
                'facility_name' => "Facility $facilityId",
                'status' => 'active',
            ]);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function seedVl(string $artNo, int $facilityId, array $overrides = []): int
    {
        $db = LegacyAppHarness::db();
        $db->insert('form_vl', $overrides + [
            'vlsm_instance_id' => 'test-instance',
            'unique_id' => bin2hex(random_bytes(8)),
            'sample_code' => 'VL-' . $artNo,
            'app_sample_code' => 'APP-' . $artNo,
            'facility_id' => $facilityId,
            'lab_id' => 1,
            'patient_art_no' => $artNo,
            'result_status' => 7,
            'sample_collection_date' => '2026-08-01 09:00:00',
            'last_modified_datetime' => '2026-08-02 09:00:00',
            'request_created_by' => self::USER,
        ]);

        return (int) $db->getInsertId();
    }

    /** @param array<string, mixed> $overrides */
    private function seedEid(string $childId, int $facilityId, array $overrides = []): int
    {
        $db = LegacyAppHarness::db();
        $db->insert('form_eid', $overrides + [
            'vlsm_instance_id' => 'test-instance',
            'unique_id' => bin2hex(random_bytes(8)),
            'sample_code' => 'EID-' . $childId,
            'app_sample_code' => 'APP-' . $childId,
            'facility_id' => $facilityId,
            'lab_id' => 1,
            'child_id' => $childId,
            'result_status' => 7,
            'sample_collection_date' => '2026-08-01 09:00:00',
            'last_modified_datetime' => '2026-08-02 09:00:00',
            'request_created_by' => self::USER,
        ]);

        return (int) $db->getInsertId();
    }

    private function seedCovid(string $patientId, int $facilityId): int
    {
        $db = LegacyAppHarness::db();
        $db->insert('form_covid19', [
            'vlsm_instance_id' => 'test-instance',
            'unique_id' => bin2hex(random_bytes(8)),
            'sample_code' => 'C19-' . $patientId,
            'app_sample_code' => 'APP-' . $patientId,
            'facility_id' => $facilityId,
            'lab_id' => 1,
            'patient_id' => $patientId,
            'result' => 'negative',
            'result_status' => 7,
            'sample_collection_date' => '2026-08-01 09:00:00',
            'last_modified_datetime' => '2026-08-02 09:00:00',
            'request_created_by' => self::USER,
        ]);

        return (int) $db->getInsertId();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function drive(string $path, array $body): array
    {
        $_SERVER['HTTP_HOST'] = 'tests.local';
        $_SERVER['REQUEST_URI'] = $path;

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $path)
            ->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $response = $handler->handle($request);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private static function row(string $table, string $key, int $id): array
    {
        return LegacyAppHarness::db()->rawQueryOne("SELECT * FROM $table WHERE $key = ?", [$id]) ?? [];
    }

    /**
     * The app sends sampleCollectionDate: [] and sampleStatus: [] on every search.
     * The endpoint must treat that as "no filter", scope to the user's facilities,
     * and stamp what it returned as sent to the source, and only that.
     */
    #[RunInSeparateProcess]
    public function testVlFetchResultsWithEmptyFiltersReturnsFacilityScopedRowsAndStampsThem(): void
    {
        $mine = $this->seedVl('ART-1', 1);
        $mineToo = $this->seedVl('ART-2', 1);
        $elsewhere = $this->seedVl('ART-3', 2);

        $payload = $this->drive('/api/v1.1/vl/fetch-results.php', [
            'sampleCode' => [],
            'sampleCollectionDate' => [],
            'sampleStatus' => [],
        ]);

        self::assertSame('success', $payload['status']);
        self::assertEqualsCanonicalizing(['VL-ART-1', 'VL-ART-2'], array_column($payload['data'], 'sampleCode'));

        foreach ([$mine, $mineToo] as $id) {
            $row = self::row('form_vl', 'vl_sample_id', $id);
            self::assertSame('sent', $row['result_sent_to_source']);
            self::assertNotNull($row['result_sent_to_source_datetime']);
            self::assertNotNull($row['result_dispatched_datetime']);
            self::assertNotNull($row['result_pulled_via_api_datetime']);
        }
        $untouched = self::row('form_vl', 'vl_sample_id', $elsewhere);
        self::assertNull($untouched['result_sent_to_source_datetime']);
        self::assertNull($untouched['result_pulled_via_api_datetime']);
    }

    /**
     * patientId is the cross-test-type name; on VL it filters patient_art_no. The
     * row the filter left out is in the same facility and must not be stamped:
     * the stamp covers what was returned, not what the user could see.
     */
    #[RunInSeparateProcess]
    public function testVlFetchResultsAcceptsPatientIdAsAliasOfPatientArtNo(): void
    {
        $filteredOut = $this->seedVl('ART-1', 1);
        $this->seedVl('ART-2', 1);

        $payload = $this->drive('/api/v1.1/vl/fetch-results.php', [
            'patientId' => ['ART-2'],
            'sampleCollectionDate' => [],
            'sampleStatus' => [],
        ]);

        self::assertCount(1, $payload['data']);
        self::assertSame('VL-ART-2', $payload['data'][0]['sampleCode']);
        self::assertSame('ART-2', $payload['data'][0]['patientArtNo']);
        self::assertSame('ART-2', $payload['data'][0]['artNo']);

        $row = self::row('form_vl', 'vl_sample_id', $filteredOut);
        self::assertNull($row['result_sent_to_source_datetime']);
        self::assertNull($row['result_dispatched_datetime']);
        self::assertNull($row['result_pulled_via_api_datetime']);
    }

    /**
     * A quote in a filter value is data, not SQL. Unescaped, this payload closes
     * the IN clause and ORs the facility predicate away, so every row in the
     * table comes back, including the one at a facility the user cannot see.
     */
    #[RunInSeparateProcess]
    public function testVlFetchResultsQuotesInFilterValuesAreData(): void
    {
        $this->seedVl('ART-1', 1);
        $this->seedVl('ART-2', 1);
        $this->seedVl('ART-3', 2);

        $payload = $this->drive('/api/v1.1/vl/fetch-results.php', [
            'patientId' => ["ART-1') OR ('1'='1"],
            'sampleCollectionDate' => [],
            'sampleStatus' => [],
        ]);

        self::assertSame('success', $payload['status']);
        self::assertSame([], $payload['data']);
    }

    /**
     * A search screen passes markAsSent: false so that browsing results does not
     * mark them as delivered. The rows come back; nothing is stamped.
     */
    #[RunInSeparateProcess]
    public function testVlFetchResultsWithMarkAsSentFalseReturnsRowsWithoutStampingThem(): void
    {
        $id = $this->seedVl('ART-1', 1);

        $payload = $this->drive('/api/v1.1/vl/fetch-results.php', [
            'sampleCode' => [],
            'sampleCollectionDate' => [],
            'sampleStatus' => [],
            'markAsSent' => false,
        ]);

        self::assertSame('success', $payload['status']);
        self::assertSame(['VL-ART-1'], array_column($payload['data'], 'sampleCode'));

        $row = self::row('form_vl', 'vl_sample_id', $id);
        self::assertNull($row['result_sent_to_source_datetime']);
        self::assertNull($row['result_dispatched_datetime']);
        self::assertNull($row['result_pulled_via_api_datetime']);
    }

    /**
     * limit and offset page through the newest-first list and total counts the
     * whole filtered set, so a client can tell it has not seen everything. The
     * defaults of 100 and 0 are what every deployed app gets without asking.
     */
    #[RunInSeparateProcess]
    public function testVlFetchResultsPagesWithLimitOffsetAndReportsTotal(): void
    {
        $this->seedVl('ART-1', 1, ['last_modified_datetime' => '2026-08-01 09:00:00']);
        $this->seedVl('ART-2', 1, ['last_modified_datetime' => '2026-08-02 09:00:00']);
        $this->seedVl('ART-3', 1, ['last_modified_datetime' => '2026-08-03 09:00:00']);
        $this->seedVl('ART-4', 2);

        $payload = $this->drive('/api/v1.1/vl/fetch-results.php', [
            'sampleCode' => [],
            'sampleCollectionDate' => [],
            'sampleStatus' => [],
            'limit' => 1,
            'offset' => 1,
            'markAsSent' => false,
        ]);

        self::assertSame('success', $payload['status']);
        self::assertSame(['VL-ART-2'], array_column($payload['data'], 'sampleCode'));
        self::assertSame(3, $payload['total']);
        self::assertSame(1, $payload['limit']);
        self::assertSame(1, $payload['offset']);
    }

    /** get-request is the read the app uses to browse; it must not stamp anything. */
    #[RunInSeparateProcess]
    public function testVlGetRequestReturnsRowsWithoutStampingThem(): void
    {
        $id = $this->seedVl('ART-1', 1);

        $payload = $this->drive('/api/v1.1/vl/get-request.php', [
            'patientId' => ['ART-1'],
            'sampleCollectionDate' => [],
            'sampleStatus' => [],
        ]);

        self::assertSame('success', $payload['status']);
        self::assertSame(['ART-1'], array_column($payload['data'], 'patientArtNo'));

        $row = self::row('form_vl', 'vl_sample_id', $id);
        self::assertNull($row['result_sent_to_source_datetime']);
        self::assertNull($row['result_pulled_via_api_datetime']);
    }

    /**
     * On EID, patientId filters child_id, the user's facilities still bound the
     * read, and a rejected sample carries both the reason id and its name.
     */
    #[RunInSeparateProcess]
    public function testEidFetchResultsAcceptsPatientIdAsAliasOfChildIdAndStamps(): void
    {
        LegacyAppHarness::db()->insert('r_eid_sample_rejection_reasons', [
            'rejection_reason_id' => 5,
            'rejection_reason_name' => 'Haemolysed',
            'rejection_reason_status' => 'active',
        ]);
        $this->seedEid('CH-1', 1);
        $wanted = $this->seedEid('CH-2', 1, ['is_sample_rejected' => 'yes', 'reason_for_sample_rejection' => 5]);
        $elsewhere = $this->seedEid('CH-2', 2, ['sample_code' => 'EID-CH-2-F2', 'app_sample_code' => 'APP-CH-2-F2']);

        $payload = $this->drive('/api/v1.1/eid/fetch-results.php', [
            'patientId' => ['CH-2'],
            'sampleCollectionDate' => [],
            'sampleStatus' => [],
        ]);

        self::assertSame('success', $payload['status']);
        self::assertCount(1, $payload['data']);
        self::assertSame('CH-2', $payload['data'][0]['childId']);
        self::assertSame('APP-CH-2', $payload['data'][0]['appSampleCode']);
        self::assertSame('5', (string) $payload['data'][0]['rejectionReasonId']);
        self::assertSame('Haemolysed', $payload['data'][0]['rejectionReason']);
        self::assertSame('sent', self::row('form_eid', 'eid_id', $wanted)['result_sent_to_source']);
        self::assertNull(self::row('form_eid', 'eid_id', $elsewhere)['result_sent_to_source_datetime']);
    }

    /**
     * get-request now takes the same filters as fetch-results on every test type.
     * COVID had only sampleCode, dates and facility, so a patient filter used to
     * return every request the token could see.
     */
    #[RunInSeparateProcess]
    public function testCovidGetRequestFiltersByPatientIdAndStatusWithoutStamping(): void
    {
        $wanted = $this->seedCovid('PT-1', 1);
        $this->seedCovid('PT-2', 1);
        $this->seedCovid('PT-1-F2', 2);

        $payload = $this->drive('/api/v1.1/covid-19/get-request.php', [
            'patientId' => ['PT-1', 'PT-1-F2'],
            'sampleStatus' => [7],
            'sampleCollectionDate' => [],
        ]);

        self::assertSame('success', $payload['status']);
        self::assertSame(['C19-PT-1'], array_column($payload['data'], 'sampleCode'));
        self::assertSame(1, $payload['total']);
        self::assertNull(self::row('form_covid19', 'covid19_id', $wanted)['result_sent_to_source_datetime']);
    }

    /** COVID rows expose the result under both names the app has used. */
    #[RunInSeparateProcess]
    public function testCovidFetchResultsReturnsTestResultAliasAndStamps(): void
    {
        $id = $this->seedCovid('PT-1', 1);
        $this->seedCovid('PT-2', 2);

        $payload = $this->drive('/api/v1.1/covid-19/fetch-results.php', [
            'sampleCode' => [],
            'sampleCollectionDate' => [],
            'sampleStatus' => [],
        ]);

        self::assertSame('success', $payload['status']);
        self::assertCount(1, $payload['data']);
        self::assertSame('negative', $payload['data'][0]['result']);
        self::assertSame('negative', $payload['data'][0]['testResult']);
        self::assertSame('sent', self::row('form_covid19', 'covid19_id', $id)['result_sent_to_source']);
    }
}
