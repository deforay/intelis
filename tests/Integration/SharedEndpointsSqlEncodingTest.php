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
 * Shared lookup and admin listing endpoints, driven with values that try to
 * rewrite the query.
 *
 * The two select2 lookups took their table and column names from the query
 * string and put them into SQL as identifiers, so any table could be read
 * through them; the sample-type dropdown built its table name from the test
 * type. Those now answer only for the tables and columns their pages ask for.
 * The admin listings concatenated a filter or the search box straight into the
 * WHERE clause.
 *
 * Every test runs in its own process: LegacyRequestHandler requires a page with
 * require_once, so a process can drive it once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SharedEndpointsSqlEncodingTest extends TestCase
{
    private const DATABASE = 'intelis_shared_endpoints_sql_test';

    /** A value that must never come back out of an endpoint that was not asked for it. */
    private const SECRET = 'secret-password-hash';

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

        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            'roles', 'facility_details', 'user_details', 'user_login_history', 'r_generic_test_methods',
            'lab_storage', 'r_vl_sample_type', 'r_funding_sources', 'global_config', 'system_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO facility_details (facility_id, facility_name, facility_type, vlsm_instance_id, status)
             VALUES (11, 'Riverside Lab', 2, 'test', 'active'), (12, 'Hilltop Lab', 2, 'test', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO user_details (user_id, user_name, login_id, password, status)
             VALUES ('u1', 'Admin', 'admin', '" . self::SECRET . "', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO user_login_history (login_id, login_attempted_datetime, login_status)
             VALUES ('alice', NOW(), 'successful'), ('bob', NOW(), 'successful')"
        );
        $db->rawQuery(
            "INSERT INTO r_generic_test_methods (test_method_id, test_method_name, test_method_status)
             VALUES (1, 'PCR', 'active'), (2, 'Culture', 'inactive')"
        );
        $db->rawQuery(
            "INSERT INTO lab_storage (storage_id, storage_code, lab_id)
             VALUES ('s1', 'FRIDGE-A', 11), ('s2', 'FRIDGE-B', 12)"
        );
        $db->rawQuery("INSERT INTO r_vl_sample_type (sample_id, sample_name, status) VALUES (1, 'Plasma', 'active')");
        $db->rawQuery(
            "INSERT INTO r_funding_sources (funding_source_id, funding_source_name, funding_source_status)
             VALUES (1, 'Global Fund', 'active'), (2, 'PEPFAR', 'active')"
        );
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $post */
    private function drive(string $endpoint, array $post = [], array $query = []): string
    {
        $_GET = $query;
        $request = LegacyAppHarness::withPost($post, $endpoint)->withQueryParams($query);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return (string) $handler->handle($request)->getBody();
    }

    /**
     * @param array<string, mixed> $query
     * @return list<string> the ids a select2 lookup answered with
     */
    private function lookedUp(string $endpoint, array $query): array
    {
        $body = $this->drive($endpoint, [], $query);
        $json = json_decode($body, true);
        self::assertIsArray($json, "Endpoint did not return JSON: $body");
        self::assertStringNotContainsString(self::SECRET, $body);
        return array_values(array_filter(array_map(
            static fn(array $row): string => (string) ($row['id'] ?? ''),
            $json['result']
        )));
    }

    /**
     * @param array<string, mixed> $post
     * @return list<string> the first cell of every listed row
     */
    private function listed(string $endpoint, array $post): array
    {
        $body = $this->drive($endpoint, $post + [
            'sEcho' => 1, 'iDisplayStart' => 0, 'iDisplayLength' => 25, 'sSearch' => '',
            'iSortCol_0' => 0, 'iSortingCols' => 1, 'bSortable_0' => 'true', 'sSortDir_0' => 'asc',
        ]);
        $json = json_decode($body, true);
        self::assertIsArray($json, "Endpoint did not return JSON: $body");
        $cells = array_map(static fn(array $row): string => strip_tags((string) $row[0]), $json['aaData']);
        sort($cells);
        return $cells;
    }

    #[RunInSeparateProcess]
    public function testGenericLookupStillFindsAnActiveTestMethod(): void
    {
        self::assertSame(['1'], $this->lookedUp('/includes/get-data-list-for-generic.php', [
            'tableName' => 'r_generic_test_methods', 'fieldName' => 'test_method_name',
            'fieldId' => 'test_method_id', 'status' => 'test_method_status', 'q' => 'PC',
        ]));
    }

    #[RunInSeparateProcess]
    public function testGenericLookupDoesNotReadATableNoPageAsksFor(): void
    {
        self::assertSame([], $this->lookedUp('/includes/get-data-list-for-generic.php', [
            'tableName' => 'user_details', 'fieldName' => 'login_id', 'fieldId' => 'password',
        ]));
    }

    #[RunInSeparateProcess]
    public function testGenericLookupStatusCannotWidenTheFilter(): void
    {
        // As a column name this closed the status test and listed the inactive
        // method as well.
        self::assertSame([], $this->lookedUp('/includes/get-data-list-for-generic.php', [
            'tableName' => 'r_generic_test_methods', 'fieldName' => 'test_method_name', 'fieldId' => 'test_method_id',
            'status' => "test_method_status like 'x' OR 1=1 OR test_method_status",
        ]));
    }

    #[RunInSeparateProcess]
    public function testGenericLookupCastsTheLabToAnId(): void
    {
        self::assertSame(['s1'], $this->lookedUp('/includes/get-data-list-for-generic.php', [
            'tableName' => 'lab_storage', 'fieldName' => 'storage_code', 'fieldId' => 'storage_id',
            'labId' => '11 OR 1=1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testLookupDoesNotReadATableNoPageAsksFor(): void
    {
        self::assertSame([], $this->lookedUp('/includes/get-data-list.php', [
            'tableName' => 'user_details', 'fieldName' => 'password',
        ]));
    }

    #[RunInSeparateProcess]
    public function testSampleTypesStillListForAKnownTestType(): void
    {
        self::assertStringContainsString('Plasma', $this->drive('/includes/get-sample-type.php', [
            'facilityId' => 11, 'testType' => 'vl',
        ]));
    }

    #[RunInSeparateProcess]
    public function testSampleTypesDoNotReadAnotherTableThroughTheTestType(): void
    {
        $body = $this->drive('/includes/get-sample-type.php', [
            'facilityId' => 11,
            'testType' => "vl_sample_type UNION SELECT 9, password, 'active', NULL, 0 FROM user_details #",
        ]);
        self::assertStringNotContainsString(self::SECRET, $body);
        self::assertStringNotContainsString('Plasma', $body);
    }

    #[RunInSeparateProcess]
    public function testLoginHistoryTreatsAQuoteInTheLoginIdAsPartOfTheId(): void
    {
        self::assertSame([], $this->listed('/system-admin/user-login-history/getUserLoginHistoryDetails.php', [
            'loginId' => 'alice" OR "1"="1',
        ]));
    }

    #[RunInSeparateProcess]
    public function testLoginHistoryStillFindsALoginId(): void
    {
        self::assertSame(['alice'], $this->listed('/system-admin/user-login-history/getUserLoginHistoryDetails.php', [
            'loginId' => 'alice',
        ]));
    }

    #[RunInSeparateProcess]
    public function testFundingSourceSearchCannotCloseItsQuote(): void
    {
        self::assertSame([], $this->listed('/common/reference/get-funding-sources-helper.php', [
            'sSearch' => "zz'OR'%'='",
        ]));
    }

    #[RunInSeparateProcess]
    public function testFundingSourceSortDirectionIsOnlyAscOrDesc(): void
    {
        self::assertSame(['Global Fund', 'PEPFAR'], $this->listed('/common/reference/get-funding-sources-helper.php', [
            'sSortDir_0' => 'asc, (SELECT 1 UNION SELECT 2)',
        ]));
    }
}
