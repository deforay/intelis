<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;

/**
 * /api/v1.1/user/login.php has three outcomes and the app shows the message of
 * each: status 1 with a token, status 2 for a rejected credential, and status
 * 'failed' with a reason for anything the server could not act on. The last
 * one is the point: a broken table used to read as a wrong password.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiLoginEndpointTest extends TestCase
{
    private const DATABASE = 'intelis_api_login_test';

    // Parents before children: roles_privileges_map references privileges and roles.
    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config', 'track_api_requests',
        'resources', 'privileges', 'roles', 'roles_privileges_map', 'user_details',
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
        require_once ROOT_PATH . '/app/system/version.php';

        LegacyAppHarness::boot(self::DATABASE, self::TABLES);
        $db = LegacyAppHarness::db();
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'test-instance']);
        $db->insert('system_config', [
            'display_name' => 'Instance type',
            'name' => 'sc_user_type',
            'value' => 'vlsm',
        ]);
        $db->insert('roles', [
            'role_id' => 1,
            'role_name' => 'Lab',
            'role_code' => 'lab',
            'access_type' => 'testing-lab',
        ]);
        $db->insert('user_details', [
            'user_id' => 'user-1',
            'user_name' => 'Lab User',
            'login_id' => 'labuser',
            'password' => ContainerRegistry::get(UsersService::class)
                ->passwordHash('right-password'),
            'status' => 'active',
            'role_id' => 1,
            'app_access' => 'yes',
            'api_token' => 'existing-token',
        ]);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /**
     * @param array<string, mixed>|string $body
     * @return array<string, mixed>
     */
    private function login(array|string $body): array
    {
        $path = '/api/v1.1/user/login.php';
        $_SERVER['HTTP_HOST'] = 'tests.local';
        $_SERVER['REQUEST_URI'] = $path;
        $json = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream($json));

        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));

        return json_decode((string) $handler->handle($request)->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    #[RunInSeparateProcess]
    public function testRightCredentialsAnswerStatusOneWithTheToken(): void
    {
        $payload = $this->login(['userName' => 'labuser', 'password' => 'right-password']);

        self::assertSame(1, $payload['status']);
        self::assertSame('existing-token', $payload['data']['api_token']);
        self::assertSame('labuser', $payload['data']['user']['login_id']);
        self::assertArrayNotHasKey('password', $payload['data']['user']);
    }

    #[RunInSeparateProcess]
    public function testWrongPasswordAnswersStatusTwo(): void
    {
        $payload = $this->login(['userName' => 'labuser', 'password' => 'wrong']);

        self::assertSame(2, $payload['status']);
        self::assertArrayNotHasKey('data', $payload);
    }

    #[RunInSeparateProcess]
    public function testMissingFieldIsNotReportedAsAWrongPassword(): void
    {
        $payload = $this->login(['userName' => 'labuser']);

        self::assertSame('failed', $payload['status']);
        self::assertStringContainsString('password', $payload['message']);
    }

    /** With no roles table the user query throws; that is a server fault, not status 2. */
    #[RunInSeparateProcess]
    public function testServerFaultIsNotReportedAsAWrongPassword(): void
    {
        $db = LegacyAppHarness::db();
        $db->rawQuery('SET FOREIGN_KEY_CHECKS = 0');
        $db->rawQuery('DROP TABLE roles');

        $payload = $this->login(['userName' => 'labuser', 'password' => 'right-password']);

        self::assertSame('failed', $payload['status']);
        self::assertStringContainsString('could not process the login', $payload['message']);
        self::assertNotSame(2, $payload['status']);
    }
}
