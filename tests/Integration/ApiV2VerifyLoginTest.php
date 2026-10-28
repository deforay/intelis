<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\Api\V2\VerifyLoginHandler;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\UsersService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * POST /api/v2/user/verify-login: Recency checks a user's InteLIS login and
 * password here, so both apps take the same credentials. It is off without
 * recency.authKey, answers only a caller holding that key, never changes the
 * user's token, and locks a login after repeated failures.
 *
 * Every test runs in its own process.
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiV2VerifyLoginTest extends TestCase
{
    private const DATABASE = 'intelis_api_v2_verify_login_test';

    private const KEY = 'recency-key-0123456789abcdef0123456789abcdef';

    private const PASSWORD = 'rightPass123';

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
        // The rate limit counts per address; keep runs from sharing one
        $_SERVER['REMOTE_ADDR'] = '192.0.2.' . (getmypid() % 250 + 1);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    private function start(string $key = self::KEY): void
    {
        $db = LegacyAppHarness::boot(
            TestRun::database(self::DATABASE),
            ['roles', 'user_details', 'user_login_history'],
            ['recency' => ['authKey' => $key]]
        );
        $db->insert('roles', ['role_id' => 2, 'role_name' => 'Lab', 'role_code' => 'lab', 'status' => 'active']);
        $this->addUser('u-1', 'tester', ['email' => 'tester@example.org', 'api_token' => 'mobile-token']);
    }

    /** @param array<string, mixed> $columns */
    private function addUser(string $userId, string $loginId, array $columns = []): void
    {
        LegacyAppHarness::db()->insert('user_details', $columns + [
            'user_id' => $userId,
            'user_name' => 'Test Person',
            'login_id' => $loginId,
            'password' => password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            'role_id' => 2,
            'status' => 'active',
            'force_password_reset' => 0,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{code: int, payload: array<string, mixed>}
     */
    private function post(?string $bearer, array $body): array
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v2/user/verify-login')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('User-Agent', 'Recency')
            ->withBody((new StreamFactory())->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        if ($bearer !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $bearer);
        }
        // The API middleware registers the request; the login history reads it
        AppRegistry::set('request', $request);

        $response = ContainerRegistry::get(VerifyLoginHandler::class)($request);

        return [
            'code' => $response->getStatusCode(),
            'payload' => json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /** @return array{code: int, payload: array<string, mixed>} */
    private function check(string $loginId, string $password): array
    {
        return $this->post(self::KEY, ['loginId' => $loginId, 'password' => $password]);
    }

    /** @return list<string> the login's history statuses, oldest first */
    private static function history(string $loginId): array
    {
        $rows = LegacyAppHarness::db()->rawQuery(
            'SELECT login_status FROM user_login_history WHERE login_id = ? ORDER BY history_id',
            [$loginId]
        );
        return array_column($rows, 'login_status');
    }

    private static function addHistory(string $loginId, string $status, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            LegacyAppHarness::db()->insert('user_login_history', [
                'login_id' => $loginId,
                'login_status' => $status,
                'login_attempted_datetime' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            ]);
        }
    }

    #[RunInSeparateProcess]
    public function testWithoutAKeyTheEndpointIsOff(): void
    {
        $this->start('');

        $result = $this->post('', ['loginId' => 'tester', 'password' => self::PASSWORD]);

        self::assertSame(404, $result['code']);
        self::assertSame([], self::history('tester'));
    }

    #[RunInSeparateProcess]
    public function testAShortKeyKeepsTheEndpointOff(): void
    {
        $this->start('too-short');

        self::assertSame(404, $this->post('too-short', ['loginId' => 'tester', 'password' => self::PASSWORD])['code']);
    }

    #[RunInSeparateProcess]
    public function testACallerWithoutTheKeyIsRefused(): void
    {
        $this->start();

        self::assertSame(401, $this->post(null, ['loginId' => 'tester', 'password' => self::PASSWORD])['code']);
        self::assertSame(401, $this->post('wrong-key', ['loginId' => 'tester', 'password' => self::PASSWORD])['code']);
        self::assertSame([], self::history('tester'), 'An unknown caller gets nothing checked');
    }

    #[RunInSeparateProcess]
    public function testAWrongPasswordIsNotVerifiedAndCountsAsAFailedSignIn(): void
    {
        $this->start();

        $result = $this->check('tester', 'wrongPass999');

        self::assertSame(200, $result['code']);
        self::assertSame(['verified' => false, 'reason' => 'invalid_credentials'], $result['payload']['data']);
        self::assertSame(['failed'], self::history('tester'));
    }

    #[RunInSeparateProcess]
    public function testAnUnknownLoginGetsTheSameAnswerAsAWrongPassword(): void
    {
        $this->start();

        $result = $this->check('nobody', self::PASSWORD);

        self::assertSame(['verified' => false, 'reason' => 'invalid_credentials'], $result['payload']['data']);
        self::assertSame(['failed'], self::history('nobody'));
    }

    #[RunInSeparateProcess]
    public function testAnInactiveUserIsNotVerified(): void
    {
        $this->start();
        $this->addUser('u-2', 'gone', ['status' => 'inactive']);

        self::assertFalse($this->check('gone', self::PASSWORD)['payload']['data']['verified']);
    }

    #[RunInSeparateProcess]
    public function testTwoActiveUsersWithOneLoginAreNotVerified(): void
    {
        $this->start();
        $this->addUser('u-2', 'twin');
        $this->addUser('u-3', 'twin');

        $result = $this->check('twin', self::PASSWORD);

        self::assertSame(['verified' => false, 'reason' => 'invalid_credentials'], $result['payload']['data']);
    }

    #[RunInSeparateProcess]
    public function testALockedLoginIsRefusedWithoutCheckingThePassword(): void
    {
        $this->start();
        self::addHistory('tester', 'failed', VerifyLoginHandler::LOCK_AFTER_FAILURES);

        $result = $this->check('tester', self::PASSWORD);

        self::assertSame(['verified' => false, 'reason' => 'locked'], $result['payload']['data']);
        self::assertCount(
            VerifyLoginHandler::LOCK_AFTER_FAILURES,
            self::history('tester'),
            'A refused check adds no row, so it does not extend the lock'
        );
    }

    #[RunInSeparateProcess]
    public function testFailuresBeforeASuccessfulSignInDoNotLock(): void
    {
        $this->start();
        self::addHistory('tester', 'failed', VerifyLoginHandler::LOCK_AFTER_FAILURES);
        self::addHistory('tester', 'successful');

        self::assertTrue($this->check('tester', self::PASSWORD)['payload']['data']['verified']);
    }

    #[RunInSeparateProcess]
    public function testAUserWhoMustResetTheirPasswordIsNotVerified(): void
    {
        $this->start();
        $this->addUser('u-2', 'reset', ['force_password_reset' => 1]);

        $result = $this->check('reset', self::PASSWORD);

        self::assertSame(['verified' => false, 'reason' => 'password_reset_required'], $result['payload']['data']);
    }

    #[RunInSeparateProcess]
    public function testTheRightPasswordIsVerifiedAndLeavesTheTokenAlone(): void
    {
        $this->start();

        $result = $this->check('tester', self::PASSWORD);

        self::assertSame(200, $result['code']);
        self::assertSame([
            'verified' => true,
            'user' => ['loginId' => 'tester', 'name' => 'Test Person', 'email' => 'tester@example.org'],
        ], $result['payload']['data']);
        self::assertSame(['successful'], self::history('tester'));
        $token = LegacyAppHarness::db()->rawQueryOne("SELECT api_token FROM user_details WHERE user_id = 'u-1'");
        self::assertSame('mobile-token', $token['api_token'], 'The mobile app stays signed in');
    }

    #[RunInSeparateProcess]
    public function testAMissingLoginOrPasswordIsABadRequest(): void
    {
        $this->start();

        self::assertSame(400, $this->post(self::KEY, ['loginId' => 'tester'])['code']);
        self::assertSame(400, $this->post(self::KEY, ['password' => self::PASSWORD])['code']);
        self::assertSame(400, $this->post(self::KEY, ['loginId' => ['tester'], 'password' => self::PASSWORD])['code']);
    }

    #[RunInSeparateProcess]
    public function testTooManyRequestsFromOneAddressAreRefused(): void
    {
        $this->start();
        $_SERVER['REMOTE_ADDR'] = '198.51.100.' . (getmypid() % 250 + 1);

        $codes = [];
        for ($i = 0; $i <= VerifyLoginHandler::MAX_REQUESTS_PER_MINUTE; $i++) {
            $codes[] = $this->post('wrong-key', [])['code'];
        }

        self::assertSame(429, end($codes));
    }

    #[RunInSeparateProcess]
    public function testTheWebCaptchaClearsAfterASuccessfulSignIn(): void
    {
        $this->start();
        // The count stores its result against the caller's address
        $webLogin = (new ServerRequestFactory())->createServerRequest('POST', '/login/loginProcess.php');
        AppRegistry::set('request', $webLogin);
        self::addHistory('tester', 'failed', 3);
        self::assertSame(3, (int) ContainerRegistry::get(UsersService::class)->continuousFailedLogins('tester'));

        self::addHistory('tester', 'successful');

        self::assertSame(0, (int) ContainerRegistry::get(UsersService::class)->continuousFailedLogins('tester'));
    }
}
