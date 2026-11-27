<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Repositories\Reference\ReferenceDataRepository;
use App\Services\ActivityLogFeedService;
use App\Services\ActivityLogService;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * The user activity log records what changed and what an entry is about, and
 * the User Activity Log page reads it back with its filters.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ActivityLogTest extends TestCase
{
    private const string DATABASE = 'intelis_activity_log_test';

    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(bool $migrated = true): void
    {
        if (!LegacyAppHarness::configured()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $database = TestRun::database(self::DATABASE);
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['vl' => true]]);
        }
        $db = LegacyAppHarness::boot($database, [
            'roles', 'user_details', 'activity_log', 'resources', 'system_config', 'global_config',
            'r_vl_results', 'r_vl_sample_type', 'instruments',
        ]);
        $this->booted = true;
        if ($migrated) {
            LegacyAppHarness::addMigrationColumns('5.9.1', ['activity_log']);
        }
        $db->rawQuery("INSERT INTO roles (role_id, role_name, role_code, status)
                       VALUES (1, 'Admin', 'AD', 'active'), (2, 'Lab User', 'lab-user', 'active')");
        $db->rawQuery("INSERT INTO user_details (user_id, user_name, login_id, role_id, status)
                       VALUES ('u-1', 'Jane Admin', 'jane', 1, 'active')");
        $db->rawQuery("INSERT INTO instruments (instrument_id, machine_name)
                       VALUES ('i-1', 'Abbott m2000'), ('i-2', 'Roche 6800')");
        LegacyAppHarness::withSession(['userId' => 'u-1', 'userName' => 'Jane Admin', 'roleId' => 1]);
    }

    /** A web request from a lab's own network, as the writer sees it. */
    private static function withRequest(string $path, string $remoteAddr = '192.168.1.25'): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $path, ['REMOTE_ADDR' => $remoteAddr])
            ->withHeader('User-Agent', 'intelis-tests');
        AppRegistry::set('request', $request);
    }

    /** @return array<string, mixed> */
    private static function lastEntry(): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM activity_log ORDER BY log_id DESC LIMIT 1') ?? [];
    }

    private static function handler(): LegacyRequestHandler
    {
        return new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
    }

    private static function log(): ActivityLogService
    {
        return ContainerRegistry::get(ActivityLogService::class);
    }

    private static function feed(array $filters = []): array
    {
        return ContainerRegistry::get(ActivityLogFeedService::class)->feed($filters, 1, 50);
    }

    #[RunInSeparateProcess]
    public function testAnEntryRecordsTheItemWhatChangedAndWhereItCameFrom(): void
    {
        $this->boot();
        self::withRequest('/users/editUserHelper.php');

        self::log()->record('user-update', 'Updated user Sam', 'user', [
            'entity' => ['type' => 'user', 'id' => 'u-7', 'label' => 'Sam'],
            'changes' => ActivityLogService::changes(['role' => 'Lab User'], ['role' => 'Admin'], ['role' => 'Role']),
            'importantFields' => ['role'],
            'reason' => 'Changes what the user can access',
        ]);

        $entry = self::lastEntry();
        $this->assertSame('u-1', $entry['user_id']);
        $this->assertSame(['user', 'u-7', 'Sam'], [$entry['entity_type'], $entry['entity_id'], $entry['entity_label']]);
        $this->assertSame(1, (int) $entry['important']);
        $this->assertSame('POST /users/editUserHelper.php', $entry['route']);
        $this->assertSame('web', $entry['channel']);
        $this->assertSame(ActivityLogService::requestId(), $entry['request_id']);
        // A LAN address used to be dropped as a private range.
        $this->assertSame('192.168.1.25', $entry['ip_address']);

        $details = json_decode((string) $entry['details'], true);
        $this->assertSame('Changes what the user can access', $details['reason']);
        // MySQL's JSON type keeps an object's keys in its own order.
        $this->assertEquals(
            [['field' => 'role', 'label' => 'Role', 'from' => 'Lab User', 'to' => 'Admin']],
            $details['changes']
        );
    }

    #[RunInSeparateProcess]
    public function testASaveThatChangedNothingSaysSo(): void
    {
        $this->boot();
        self::withRequest('/users/editUserHelper.php');

        self::log()->record('user-update', 'Updated user Sam', 'user', ['changes' => []]);

        $entry = self::lastEntry();
        // Stored as written and worded for the reader, so it can be translated.
        $this->assertSame('Updated user Sam', $entry['action']);
        $this->assertSame(0, (int) $entry['important']);
        $this->assertSame('Updated user Sam (no changes)', self::feed()['items'][0]['action']);
    }

    #[RunInSeparateProcess]
    public function testTheFeedReadsNoFurtherThanTheNewestEntryAnExportStartedAt(): void
    {
        $this->boot();
        self::withRequest('/users/editUserHelper.php');
        self::log()->record('user-update', 'Updated user Sam', 'user');
        $newest = ContainerRegistry::get(ActivityLogFeedService::class)->newestLogId();
        self::log()->record('user-update', 'Updated user Ann', 'user');

        $actions = array_column(self::feed(['maxLogId' => $newest])['items'], 'action');
        $this->assertSame(['Updated user Sam'], $actions);
    }

    #[RunInSeparateProcess]
    public function testTheExportHoldsEveryMatchingEntryBeyondOnePage(): void
    {
        $this->boot();
        $db = LegacyAppHarness::db();
        $rows = ActivityLogFeedService::EXPORT_PAGE_SIZE + 3;
        $db->rawQuery('SET SESSION cte_max_recursion_depth = 10000');
        $db->rawQuery(
            "INSERT INTO activity_log (event_type, action, user_id, date_time)
             WITH RECURSIVE n (i) AS (SELECT 1 UNION ALL SELECT i + 1 FROM n WHERE i < ?)
             SELECT 'user-update', CONCAT('Entry ', i), 'u-1', '2026-11-27 08:00:00' FROM n",
            [$rows]
        );

        $before = glob(TEMP_PATH . '/InteLIS-User-Activity-Log-*.csv') ?: [];
        $request = LegacyAppHarness::withPost([], '/admin/monitoring/export-activity-log.php')
            ->withHeader('X-Requested-With', 'XMLHttpRequest');
        self::handler()->handle($request);
        $files = array_values(array_diff(glob(TEMP_PATH . '/InteLIS-User-Activity-Log-*.csv') ?: [], $before));

        $this->assertCount(1, $files);
        $lines = array_filter(
            explode("\n", (string) file_get_contents($files[0])),
            static fn(string $l): bool => str_contains($l, 'Entry ')
        );
        unlink($files[0]);
        $this->assertCount($rows, $lines);
    }

    #[RunInSeparateProcess]
    public function testARenamedEidResultIsReadBackUnderItsNewKey(): void
    {
        $this->boot();
        LegacyAppHarness::boot(TestRun::database(self::DATABASE), [
            'roles', 'user_details', 'activity_log', 'system_config', 'global_config', 'r_eid_results',
        ]);
        LegacyAppHarness::addMigrationColumns('5.9.1', ['activity_log']);
        LegacyAppHarness::withSession(['userId' => 'u-1', 'userName' => 'Jane Admin', 'roleId' => 1]);
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO r_eid_results (result_id, result, status) VALUES ('positive', 'Positive', 'active')"
        );

        $request = LegacyAppHarness::withPost([
            'resultName' => 'Detected',
            'resultStatus' => 'active',
            'resultId' => base64_encode('positive'),
        ], '/eid/reference/save-eid-results-helper.php');
        try {
            self::handler()->handle($request);
        } catch (\Throwable) {
            // The helper redirects; the entry is written before that.
        }

        $entry = self::lastEntry();
        $this->assertSame(['eid-result', 'detected'], [$entry['entity_type'], $entry['entity_id']]);
        $changes = json_decode((string) $entry['details'], true)['changes'];
        $this->assertEqualsCanonicalizing(['result'], array_column($changes, 'field'));
    }

    #[RunInSeparateProcess]
    public function testAnInstallNotYetMigratedStillLogsAndShowsItsEntries(): void
    {
        $this->boot(migrated: false);
        self::withRequest('/users/editUserHelper.php');

        self::log()->record('user-update', 'Updated user Sam', 'user', [
            'entity' => ['type' => 'user', 'id' => 'u-7', 'label' => 'Sam'],
            'changes' => ActivityLogService::changes(['role' => 'Lab User'], ['role' => 'Admin']),
        ]);

        $this->assertSame('Updated user Sam', self::lastEntry()['action']);
        $feed = self::feed(['entityType' => 'user', 'important' => '1']);
        $this->assertSame(1, $feed['total'], 'filters on the new columns are ignored until they exist');
        $this->assertSame([], $feed['items'][0]['changes']);
    }

    #[RunInSeparateProcess]
    public function testTheFeedFiltersByItemImportanceSaveAndChangedValue(): void
    {
        $this->boot();
        self::withRequest('/facilities/editFacilityHelper.php');
        self::log()->record('update-facility', 'Updated facility Lab A', 'facility', [
            'entity' => ['type' => 'facility', 'id' => 12, 'label' => 'Lab A'],
            'changes' => ActivityLogService::changes(['facility_name' => 'Old Lab Name'], ['facility_name' => 'Lab A']),
        ]);
        self::log()->record('update-facility', 'Updated facility Lab B', 'facility', [
            'entity' => ['type' => 'facility', 'id' => 13, 'label' => 'Lab B'],
            'changes' => ActivityLogService::changes(['status' => 'active'], ['status' => 'inactive']),
            'importantFields' => ['status'],
        ]);
        LegacyAppHarness::db()->insert('activity_log', [
            'event_type' => 'login',
            'action' => 'Jane logged in',
            'user_id' => 'u-1',
            'date_time' => '2026-11-27 08:00:00',
        ]);

        $actions = static fn(array $filters): array => array_column(self::feed($filters)['items'], 'action');
        $this->assertSame(['Updated facility Lab A'], $actions(['entityType' => 'facility', 'entityId' => '12']));
        $this->assertSame(['Updated facility Lab B'], $actions(['important' => '1']));
        // The old name is only in the change, not in the statement.
        $this->assertSame(['Updated facility Lab A'], $actions(['search' => 'Old Lab Name']));
        $this->assertSame(2, self::feed(['requestId' => ActivityLogService::requestId()])['total']);
        $this->assertSame(['Jane logged in'], array_column(self::feed(['source' => 'logins'])['items'], 'action'));
        $this->assertSame(2, self::feed(['source' => 'actions'])['total']);

        $item = self::feed(['important' => '1'])['items'][0];
        $this->assertSame('Facility', $item['entityTypeLabel']);
        $this->assertSame('Important fields changed: Status', $item['reason']);
        $this->assertSame('Jane Admin', $item['userName']);
    }

    #[RunInSeparateProcess]
    public function testASearchCannotWidenTheFeed(): void
    {
        $this->boot();
        self::withRequest('/users/editUserHelper.php');
        self::log()->record('user-update', 'Updated user Sam', 'user');

        $this->assertSame(0, self::feed(['search' => "%' OR '1'='1"])['total']);
        $this->assertSame(0, self::feed(['search' => '%'])['total'], 'a % is a character to find, not a wildcard');
    }

    #[RunInSeparateProcess]
    public function testReferenceDataSavesListWhatChangedAndFlagResultRules(): void
    {
        $this->boot();
        self::withRequest('/vl/reference/save-vl-results-helper.php');
        $repository = ContainerRegistry::get(ReferenceDataRepository::class);

        $id = $repository->save('vl-result', 'vl', 'Target Not Detected', 'active', [
            'interpretation' => 'suppressed',
            'available_for_instruments' => json_encode(['i-1']),
        ]);
        $added = self::lastEntry();
        $this->assertSame('Added VL result Target Not Detected', $added['action']);
        $this->assertSame(['vl-vl-result', (string) $id], [$added['entity_type'], $added['entity_id']]);
        $this->assertSame(0, (int) $added['important'], 'a new row is not flagged');

        $repository->save('vl-result', 'vl', 'Target Not Detected', 'active', [
            'interpretation' => 'not suppressed',
            'available_for_instruments' => json_encode(['i-1', 'i-2']),
        ], $id);
        $updated = self::lastEntry();
        $this->assertSame('Updated VL result Target Not Detected', $updated['action']);
        $this->assertSame(1, (int) $updated['important']);
        $changes = json_decode((string) $updated['details'], true)['changes'];
        $byField = array_column($changes, null, 'field');
        $this->assertEqualsCanonicalizing(['interpretation', 'available_for_instruments'], array_keys($byField));
        $this->assertSame(
            ['Roche 6800'],
            $byField['available_for_instruments']['added'],
            'instruments are named, not listed by id'
        );
    }

    #[RunInSeparateProcess]
    public function testAStatusChangeLogsEachRowWhoseStatusChanged(): void
    {
        $this->boot();
        self::withRequest('/vl/reference/update-vl-sample-status.php');
        $repository = ContainerRegistry::get(ReferenceDataRepository::class);
        $plasma = $repository->save('sample-type', 'vl', 'Plasma', 'active');
        $dbs = $repository->save('sample-type', 'vl', 'DBS', 'inactive');
        $before = (int) LegacyAppHarness::db()->rawQueryOne('SELECT COUNT(*) AS c FROM activity_log')['c'];

        $repository->updateStatus('sample-type', 'vl', [$plasma, $dbs], 'inactive');

        $entries = LegacyAppHarness::db()->rawQuery('SELECT * FROM activity_log ORDER BY log_id');
        $this->assertCount($before + 1, $entries, 'DBS was already inactive');
        $entry = end($entries);
        $this->assertSame('Changed the status of VL sample type Plasma', $entry['action']);
        $this->assertSame(['vl-sample-type', (string) $plasma], [$entry['entity_type'], $entry['entity_id']]);
    }

    #[RunInSeparateProcess]
    public function testTheFeedRefusesAUserWithoutTheActivityLogPrivilege(): void
    {
        $this->boot();
        LegacyAppHarness::withSession([
            'userId' => 'u-1',
            'roleId' => 2,
            'privileges' => ['/vl/requests/vl-requests.php' => 1],
        ]);

        $request = LegacyAppHarness::withPost([], '/admin/monitoring/get-activity-log.php')
            ->withHeader('X-Requested-With', 'XMLHttpRequest');
        $response = self::handler()->handle($request);

        $this->assertSame(403, http_response_code());
        $this->assertSame([], json_decode((string) $response->getBody(), true)['items']);
    }

    #[RunInSeparateProcess]
    public function testTheFeedServesAUserWithThePrivilege(): void
    {
        $this->boot();
        self::withRequest('/users/editUserHelper.php');
        self::log()->record('user-update', 'Updated user Sam', 'user');

        $request = LegacyAppHarness::withPost(['pageSize' => '10'], '/admin/monitoring/get-activity-log.php');
        $response = self::handler()->handle($request);

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(['Updated user Sam'], array_column($payload['items'], 'action'));
    }
}
