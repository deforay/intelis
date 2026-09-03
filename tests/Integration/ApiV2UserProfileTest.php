<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\Api\V2\SaveUserProfileHandler;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;

/**
 * POST /api/v2/user/profile: the strict replacement for the v1.1 profile push.
 * A lab authenticates with its STS token and labId, a user with an API token,
 * and nothing else gets in. A pushed profile can never grant access: login id,
 * role and password are ignored, an existing user keeps its status, a new one
 * arrives inactive.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiV2UserProfileTest extends TestCase
{
    private const DATABASE = 'intelis_api_v2_profile_test';

    private const LAB_TOKEN = 'sts_lab-token-for-facility-7';

    private const USER_TOKEN = 'api-user-token';

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

        LegacyAppHarness::boot(self::DATABASE, [
            's_vlsm_instance', 'system_config', 'global_config', 'roles', 'user_details', 'facility_details',
        ]);
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
        $db->insert('facility_details', [
            'facility_id' => 7,
            'facility_name' => 'Lab Seven',
            'status' => 'active',
            'sts_token' => self::LAB_TOKEN,
            'sts_token_expiry' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $db->insert('facility_details', ['facility_id' => 8, 'facility_name' => 'Lab Eight', 'status' => 'active']);
        $db->insert('user_details', [
            'user_id' => 'lab8-user',
            'user_name' => 'Eight Person',
            'login_id' => 'eight',
            'email' => 'eight@example.org',
            'phone_number' => '888',
            'status' => 'active',
            'role_id' => 1,
            'testing_lab_id' => 8,
        ]);
        $db->insert('user_details', [
            'user_id' => 'api-user',
            'user_name' => 'API User',
            'login_id' => 'api',
            'api_token' => self::USER_TOKEN,
            'status' => 'active',
            'role_id' => 1,
        ]);
        // Pushed before labs were stamped: no testing_lab_id yet.
        $db->insert('user_details', [
            'user_id' => 'existing-1',
            'user_name' => 'Old Name',
            'login_id' => 'old',
            'email' => 'old@example.org',
            'phone_number' => '111',
            'status' => 'active',
            'role_id' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /**
     * @param array<string, mixed> $body
     * @return array{code: int, payload: array<string, mixed>}
     */
    private function post(?string $bearer, array $body): array
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v2/user/profile')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        if ($bearer !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $bearer);
        }

        $response = ContainerRegistry::get(SaveUserProfileHandler::class)($request);

        return [
            'code' => $response->getStatusCode(),
            'payload' => json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /** @return array<string, mixed> */
    private static function user(string $id): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM user_details WHERE user_id = ?', [$id]) ?? [];
    }

    #[RunInSeparateProcess]
    public function testLabTokenWithLabIdCreatesAnInactiveUserWithoutCredentials(): void
    {
        ['code' => $code, 'payload' => $payload] = $this->post(self::LAB_TOKEN, [
            'labId' => 7,
            'profile' => [
                'userId' => 'new-1',
                'userName' => 'New Person',
                'email' => 'new@example.org',
                'phoneNo' => '123',
                'loginId' => 'sneaky',
                'password' => 'sneaky',
                'role' => 1,
                'status' => 'active',
            ],
        ]);

        self::assertSame(200, $code, json_encode($payload));
        self::assertSame('success', $payload['status']);
        self::assertSame(['userId' => 'new-1', 'action' => 'created', 'caller' => 'lab'], $payload['data']);

        $row = self::user('new-1');
        self::assertSame('New Person', $row['user_name']);
        self::assertSame('inactive', $row['status']);
        self::assertNull($row['login_id']);
        self::assertNull($row['password']);
        self::assertNull($row['role_id']);
        self::assertSame(7, (int) $row['testing_lab_id']);
    }

    #[RunInSeparateProcess]
    public function testLabTokenUpdatesAnExistingUserByEmailAndKeepsItsStatus(): void
    {
        ['code' => $code, 'payload' => $payload] = $this->post(self::LAB_TOKEN, [
            'labId' => 7,
            'profile' => ['userName' => 'Renamed', 'email' => 'old@example.org', 'status' => 'inactive'],
        ]);

        self::assertSame(200, $code, json_encode($payload));
        self::assertSame('updated', $payload['data']['action']);
        $row = self::user('existing-1');
        self::assertSame('Renamed', $row['user_name']);
        self::assertSame('active', $row['status']);
        self::assertSame('old', $row['login_id']);
        // Omitted fields stay; an unstamped user is adopted by the pushing lab.
        self::assertSame('111', $row['phone_number']);
        self::assertSame(7, (int) $row['testing_lab_id']);
    }

    #[RunInSeparateProcess]
    public function testLabCannotTouchAnotherLabsUser(): void
    {
        ['code' => $code, 'payload' => $payload] = $this->post(self::LAB_TOKEN, [
            'labId' => 7,
            'profile' => ['userId' => 'lab8-user', 'userName' => 'Hijacked', 'email' => 'eight@example.org'],
        ]);

        self::assertSame(403, $code, json_encode($payload));
        self::assertSame('forbidden', $payload['error']['code']);
        self::assertSame('Eight Person', self::user('lab8-user')['user_name']);
    }

    #[RunInSeparateProcess]
    public function testLabTokenForAnotherLabIsRejected(): void
    {
        ['code' => $code, 'payload' => $payload] = $this->post(self::LAB_TOKEN, [
            'labId' => 8,
            'profile' => ['userName' => 'X', 'email' => 'x@example.org'],
        ]);

        self::assertSame(401, $code);
        self::assertSame('invalid_credential', $payload['error']['code']);
        $rows = LegacyAppHarness::db()->rawQuery("SELECT * FROM user_details WHERE email = 'x@example.org'");
        self::assertSame([], $rows);
    }

    #[RunInSeparateProcess]
    public function testNoTokenIsRejectedEvenWithTheLegacyKeyField(): void
    {
        ['code' => $code] = $this->post(null, [
            'x-api-key' => 'anything',
            'labId' => 7,
            'profile' => ['userName' => 'X', 'email' => 'x@example.org'],
        ]);

        self::assertSame(401, $code);
    }

    /** A user token edits its own profile only, whatever userId or email the body names. */
    #[RunInSeparateProcess]
    public function testUserTokenEditsItselfOnly(): void
    {
        ['code' => $code, 'payload' => $payload] = $this->post(self::USER_TOKEN, [
            'profile' => ['userId' => 'lab8-user', 'userName' => 'My New Name', 'email' => 'eight@example.org'],
        ]);

        self::assertSame(200, $code, json_encode($payload));
        self::assertSame(['userId' => 'api-user', 'action' => 'updated', 'caller' => 'user'], $payload['data']);
        self::assertSame('My New Name', self::user('api-user')['user_name']);
        self::assertSame('Eight Person', self::user('lab8-user')['user_name']);
        self::assertSame([], LegacyAppHarness::db()->rawQuery("SELECT * FROM user_details WHERE user_id = 'new-2'"));
    }

    #[RunInSeparateProcess]
    public function testMissingNameIsA400WithTheReason(): void
    {
        ['code' => $code, 'payload' => $payload] = $this->post(self::LAB_TOKEN, [
            'labId' => 7,
            'profile' => ['email' => 'x@example.org'],
        ]);

        self::assertSame(400, $code);
        self::assertSame('invalid_request', $payload['error']['code']);
        self::assertStringContainsString('userName', $payload['error']['message']);
    }
}
