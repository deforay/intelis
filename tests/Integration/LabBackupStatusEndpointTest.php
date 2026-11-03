<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\SystemException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * A lab's backup status, sent with its metadata sync to the STS endpoint
 * remote/remote/lab-metadata-receiver.php and shown on the lab sync status page.
 * It is stored only under the lab whose token sent it, and only in the fixed
 * shape BackupStatusService::sanitizeReport() returns.
 *
 * One drive per test: the handler uses require_once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class LabBackupStatusEndpointTest extends TestCase
{
    private const LAB = 7;

    private const TOKEN = 'lab-7-token';

    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    private function boot(): mixed
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        require_once ROOT_PATH . '/app/system/version.php';
        $database = TestRun::database('intelis_lab_backup_status_test');
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['database' => ['db' => $database], 'modules' => ['vl' => true]]);
        }
        $db = LegacyAppHarness::boot($database, [
            'system_config', 'global_config', 's_vlsm_instance', 'facility_details', 'track_api_requests',
            'roles', 'user_details',
        ]);
        $this->booted = true;
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'sts-instance']);
        $db->insert('system_config', [
            'display_name' => 'Instance type', 'name' => 'sc_user_type', 'value' => 'remoteuser',
        ]);
        foreach ([self::LAB => self::TOKEN, 8 => 'lab-8-token'] as $labId => $token) {
            $db->insert('facility_details', [
                'facility_id' => $labId, 'facility_name' => "Lab $labId", 'facility_type' => 2, 'status' => 'active',
                'sts_token' => $token, 'sts_token_expiry' => '2099-01-01 00:00:00',
            ]);
        }
        return $db;
    }

    private static function body(array $backupStatus): string
    {
        return json_encode(['labId' => self::LAB, 'backupStatus' => $backupStatus], JSON_THROW_ON_ERROR);
    }

    /**
     * What the endpoint answered. It catches its own errors and writes them into
     * the body, and the test runs where http_response_code() sets nothing, so a
     * refusal is read from the body rather than the status.
     */
    private static function drive(string $body, ?string $token = self::TOKEN): string
    {
        $_SERVER['HTTP_HOST'] = 'tests.local';
        $_SERVER['REQUEST_URI'] = '/remote/remote/lab-metadata-receiver.php';
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/remote/remote/lab-metadata-receiver.php')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream($body));
        if ($token !== null) {
            $request = $request->withHeader('Authorization', "Bearer $token");
        }
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        try {
            $answer = json_decode((string) $handler->handle($request)->getBody(), true);
        } catch (SystemException $e) {
            return 'error';
        }
        return (string) ($answer['status'] ?? 'no answer');
    }

    /** @return array<string, mixed>|null */
    private static function stored(mixed $db, int $labId = self::LAB): ?array
    {
        $raw = $db->rawQueryOne(
            "SELECT facility_attributes->>'$.backup' AS backup FROM facility_details WHERE facility_id = ?",
            [$labId]
        )['backup'] ?? null;
        return is_string($raw) && $raw !== 'null' ? json_decode($raw, true) : null;
    }

    #[RunInSeparateProcess]
    public function testTheLabsOwnTokenStoresTheCleanedReport(): void
    {
        $db = $this->boot();

        $status = self::drive(self::body([
            'setUp' => true, 'status' => 'failed', 'lastSuccessHours' => 30,
            'lastError' => 'Cannot reach the backup server', 'destination' => 'ssh', 'injected' => 'x',
        ]));

        self::assertSame('success', $status);
        // assertEquals: MySQL keeps a JSON object's keys in an order of its own.
        self::assertEquals([
            'setUp' => true, 'status' => 'failed', 'lastSuccessHours' => 30,
            'lastError' => 'Cannot reach the backup server', 'destination' => 'ssh', 'auditRestoreHours' => null,
        ], self::stored($db));
        self::assertNotEmpty($db->rawQueryOne(
            "SELECT facility_attributes->>'$.backupSeenAt' AS seen FROM facility_details WHERE facility_id = 7"
        )['seen'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testAnotherLabsTokenStoresNothing(): void
    {
        $db = $this->boot();

        self::assertSame('error', self::drive(self::body(['setUp' => true, 'status' => 'ok']), 'lab-8-token'));

        self::assertNull(self::stored($db));
        self::assertNull(self::stored($db, 8));
    }

    #[RunInSeparateProcess]
    public function testNoTokenStoresNothing(): void
    {
        $db = $this->boot();

        self::assertSame('error', self::drive(self::body(['setUp' => true, 'status' => 'ok']), null));

        self::assertNull(self::stored($db));
    }

    #[RunInSeparateProcess]
    public function testALabThatSendsNoBackupStatusLeavesItAlone(): void
    {
        $db = $this->boot();
        $db->rawQuery(
            "UPDATE facility_details SET facility_attributes = JSON_OBJECT('backup', JSON_OBJECT('status', 'ok'))
              WHERE facility_id = 7"
        );

        self::drive(json_encode(['labId' => self::LAB, 'users' => []], JSON_THROW_ON_ERROR));

        self::assertSame(['status' => 'ok'], self::stored($db));
    }
}
