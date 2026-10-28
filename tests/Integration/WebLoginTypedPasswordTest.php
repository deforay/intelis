<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\RedirectException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;
use Throwable;

/**
 * The web sign-in takes the password as typed. It used to compare the HTML-
 * sanitized copy, so a password with & set since 2026-08-12 (hashed as typed)
 * could never sign in, while one set before (hashed sanitized) still could.
 * Both have to sign in.
 *
 * Every test runs in its own process and drives the sign-in once.
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class WebLoginTypedPasswordTest extends TestCase
{
    private const DATABASE = 'intelis_web_login_typed_test';

    private const TYPED = 'Pass&word1';

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
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'recency' => ['crosslogin' => false, 'url' => ''],
                'system' => ['debug_mode' => false],
            ]);
        }
        require_once ROOT_PATH . '/app/system/version.php';

        $db = LegacyAppHarness::boot(TestRun::database(self::DATABASE), [
            's_vlsm_instance', 'system_config', 'global_config',
            'resources', 'privileges', 'roles', 'roles_privileges_map', 'user_details',
            'user_login_history', 'facility_details', 'user_facility_map', 'activity_log',
        ]);
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'test-instance']);
        $db->insert('system_config', ['display_name' => 'Instance type', 'name' => 'sc_user_type', 'value' => 'vlsm']);
        $db->insert('roles', [
            'role_id' => 2, 'role_name' => 'Lab', 'role_code' => 'lab', 'status' => 'active',
            'access_type' => 'testing-lab',
        ]);
        // The sign-in renews the session id, which needs a session; no cookie in a test
        ini_set('session.use_cookies', '0');
        session_start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    private static function addUser(string $hashOf): void
    {
        LegacyAppHarness::db()->insert('user_details', [
            'user_id' => 'u-1',
            'user_name' => 'Test Person',
            'login_id' => 'tester',
            'password' => password_hash($hashOf, PASSWORD_BCRYPT),
            'role_id' => 2,
            'status' => 'active',
            'force_password_reset' => 0,
        ]);
    }

    /** Signs in and returns where the page sent the browser. */
    private function signIn(string $password): ?string
    {
        $request = LegacyAppHarness::withPost(
            ['username' => 'tester', 'password' => $password],
            '/login/loginProcess.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        try {
            $handler->handle($request);
        } catch (Throwable $e) {
            for ($cause = $e; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
                if ($cause instanceof RedirectException) {
                    return $cause->getUrl();
                }
            }
            throw $e;
        }
        return null;
    }

    /** @return list<string> */
    private static function history(): array
    {
        $rows = LegacyAppHarness::db()->rawQuery(
            "SELECT login_status FROM user_login_history WHERE login_id = 'tester' ORDER BY history_id"
        );
        return array_column($rows, 'login_status');
    }

    #[RunInSeparateProcess]
    public function testAPasswordWithAnAmpersandStoredAsTypedSignsIn(): void
    {
        self::addUser(self::TYPED);

        $url = $this->signIn(self::TYPED);

        self::assertSame(['successful'], self::history(), (string) ($_SESSION['alertMsg'] ?? ''));
        self::assertNotSame('/login/login.php', $url, (string) ($_SESSION['alertMsg'] ?? ''));
        self::assertSame('u-1', $_SESSION['userId'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testAPasswordStoredInTheOldSanitizedFormStillSignsIn(): void
    {
        self::addUser(UsersService::sanitizedAsBefore(self::TYPED));

        $this->signIn(self::TYPED);

        self::assertSame(['successful'], self::history(), (string) ($_SESSION['alertMsg'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function testAWrongPasswordDoesNotSignIn(): void
    {
        self::addUser(self::TYPED);

        $url = $this->signIn('Pass&word2');

        self::assertSame('/login/login.php', $url);
        self::assertSame(['failed'], self::history());
        self::assertArrayNotHasKey('userId', $_SESSION);
    }

    /**
     * The suite's _sanitizeInput() returns its input unchanged, so the tests above
     * cannot tell the sanitized copy from the typed password. This checks the page
     * hands the typed one to the password check.
     */
    public function testThePageChecksThePasswordAsTyped(): void
    {
        $source = (string) file_get_contents(APPLICATION_PATH . '/login/loginProcess.php');

        self::assertStringContainsString("_rawInput('password')", $source);
        self::assertMatchesRegularExpression('/passwordVerify\([^;]*\$typedPassword/', $source);
        self::assertDoesNotMatchRegularExpression('/passwordVerify\([^;]*\$_POST\[.password.\]/', $source);
    }
}
