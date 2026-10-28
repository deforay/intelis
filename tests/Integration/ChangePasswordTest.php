<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\RedirectException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;
use Throwable;

/**
 * The Change Password page's helper changes the session user's password only
 * after checking the current one. The page's own check is a convenience: a
 * direct POST skips it, so the helper is what has to refuse.
 *
 * Every test runs in its own process and drives the helper once.
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ChangePasswordTest extends TestCase
{
    private const DATABASE = 'intelis_change_password_test';

    private const CURRENT = 'oldPass123';
    private const REPLACEMENT = 'newPass456';

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
            define('SYSTEM_CONFIG', ['recency' => ['crosslogin' => false], 'system' => ['debug_mode' => false]]);
        }

        $db = LegacyAppHarness::boot(TestRun::database(self::DATABASE), ['roles', 'user_details', 'activity_log']);
        $db->rawQuery(
            "INSERT INTO roles (role_id, role_name, role_code, status) VALUES (2, 'Lab User', 'lab-user', 'active')"
        );
        foreach (['u-self' => 'self', 'u-other' => 'other'] as $userId => $loginId) {
            $db->insert('user_details', [
                'user_id' => $userId,
                'user_name' => ucfirst($loginId),
                'login_id' => $loginId,
                'password' => password_hash(self::CURRENT, PASSWORD_BCRYPT),
                'role_id' => 2,
                'force_password_reset' => 1,
                'status' => 'active',
            ]);
        }
        LegacyAppHarness::withSession(['userId' => 'u-self', 'loginId' => 'self', 'roleId' => 2]);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** Drives the helper and returns where it redirected to. */
    private function drive(array $post): ?string
    {
        $request = LegacyAppHarness::withPost($post, '/users/change-password-helper.php');
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

    /** @return array{password: string, force_password_reset: int|string|null} */
    private static function stored(string $userId): array
    {
        return LegacyAppHarness::db()->rawQueryOne(
            "SELECT password, force_password_reset FROM user_details WHERE user_id = ?",
            [$userId]
        );
    }

    private static function assertPasswordIs(string $expected, string $userId): void
    {
        self::assertTrue(
            password_verify($expected, self::stored($userId)['password']),
            "$userId's password should still be '$expected'"
        );
    }

    #[RunInSeparateProcess]
    public function testAWrongCurrentPasswordChangesNothing(): void
    {
        $url = $this->drive([
            'currentPassword' => 'notMyPass1',
            'newPassword' => self::REPLACEMENT,
            'confirmPassword' => self::REPLACEMENT,
        ]);

        self::assertSame('change-password.php', $url);
        self::assertPasswordIs(self::CURRENT, 'u-self');
        self::assertSame(1, (int) self::stored('u-self')['force_password_reset']);
        self::assertSame('Current password is incorrect.', $_SESSION['alertMsg'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testAMissingCurrentPasswordChangesNothing(): void
    {
        $this->drive(['newPassword' => self::REPLACEMENT, 'confirmPassword' => self::REPLACEMENT]);

        self::assertPasswordIs(self::CURRENT, 'u-self');
    }

    #[RunInSeparateProcess]
    public function testAConfirmationThatDiffersChangesNothing(): void
    {
        $this->drive([
            'currentPassword' => self::CURRENT,
            'newPassword' => self::REPLACEMENT,
            'confirmPassword' => 'otherPass789',
        ]);

        self::assertPasswordIs(self::CURRENT, 'u-self');
    }

    #[RunInSeparateProcess]
    public function testAWeakPasswordChangesNothing(): void
    {
        $this->drive(['currentPassword' => self::CURRENT, 'newPassword' => 'short', 'confirmPassword' => 'short']);

        self::assertPasswordIs(self::CURRENT, 'u-self');
    }

    #[RunInSeparateProcess]
    public function testTheRightCurrentPasswordChangesOnlyTheSessionUsersPassword(): void
    {
        $url = $this->drive([
            'userId' => 'u-other',
            'currentPassword' => self::CURRENT,
            'newPassword' => self::REPLACEMENT,
            'confirmPassword' => self::REPLACEMENT,
        ]);

        self::assertSame('change-password.php', $url);
        self::assertPasswordIs(self::REPLACEMENT, 'u-self');
        self::assertSame(0, (int) self::stored('u-self')['force_password_reset']);
        self::assertPasswordIs(self::CURRENT, 'u-other');
    }
}
