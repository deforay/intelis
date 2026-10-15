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
use Throwable;

/**
 * Edit Profile changes the password only with the current one, checked on the
 * server. A profile save without a new password needs no current password, so
 * a user can still change their name or language.
 *
 * Every test runs in its own process and drives the helper once.
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class EditProfilePasswordTest extends TestCase
{
    private const DATABASE = 'intelis_edit_profile_password_test';

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

        $db = LegacyAppHarness::boot(
            self::DATABASE . '_' . getmypid(),
            ['roles', 'user_details', 'activity_log', 'global_config']
        );
        $db->rawQuery(
            "INSERT INTO roles (role_id, role_name, role_code, status) VALUES (2, 'Lab User', 'lab-user', 'active')"
        );
        $db->insert('user_details', [
            'user_id' => 'u-self',
            'user_name' => 'Old Name',
            'login_id' => 'self',
            'password' => password_hash(self::CURRENT, PASSWORD_BCRYPT),
            'role_id' => 2,
            'force_password_reset' => 1,
            'status' => 'active',
        ]);
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
        $request = LegacyAppHarness::withPost(
            $post + ['userName' => 'New Name', 'email' => '', 'phoneNo' => '', 'userLocale' => 'en_US'],
            '/users/edit-profile-helper.php'
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

    /** @return array{password: string, user_name: string, force_password_reset: int|string|null} */
    private static function stored(): array
    {
        return LegacyAppHarness::db()->rawQueryOne(
            "SELECT password, user_name, force_password_reset FROM user_details WHERE user_id = 'u-self'"
        );
    }

    #[RunInSeparateProcess]
    public function testANewPasswordWithAWrongCurrentPasswordSavesNothing(): void
    {
        $url = $this->drive([
            'currentPassword' => 'notMyPass1',
            'password' => self::REPLACEMENT,
            'confirmPassword' => self::REPLACEMENT,
        ]);

        self::assertSame('edit-profile.php', $url);
        self::assertTrue(password_verify(self::CURRENT, self::stored()['password']));
        self::assertSame('Old Name', self::stored()['user_name'], 'The profile fields are not saved either');
        self::assertSame('Current password is incorrect.', $_SESSION['alertMsg'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testANewPasswordWithoutTheCurrentOneSavesNothing(): void
    {
        $this->drive(['password' => self::REPLACEMENT, 'confirmPassword' => self::REPLACEMENT]);

        self::assertTrue(password_verify(self::CURRENT, self::stored()['password']));
        self::assertSame('Old Name', self::stored()['user_name']);
    }

    #[RunInSeparateProcess]
    public function testAConfirmationThatDiffersSavesNothing(): void
    {
        $this->drive([
            'currentPassword' => self::CURRENT,
            'password' => self::REPLACEMENT,
            'confirmPassword' => 'otherPass789',
        ]);

        self::assertTrue(password_verify(self::CURRENT, self::stored()['password']));
    }

    #[RunInSeparateProcess]
    public function testAProfileSaveWithoutANewPasswordNeedsNoCurrentPassword(): void
    {
        $url = $this->drive([]);

        self::assertSame('edit-profile.php', $url);
        self::assertSame('New Name', self::stored()['user_name']);
        self::assertTrue(password_verify(self::CURRENT, self::stored()['password']));
    }

    #[RunInSeparateProcess]
    public function testTheRightCurrentPasswordChangesThePassword(): void
    {
        $url = $this->drive([
            'currentPassword' => self::CURRENT,
            'password' => self::REPLACEMENT,
            'confirmPassword' => self::REPLACEMENT,
        ]);

        self::assertSame('edit-profile.php', $url);
        self::assertTrue(password_verify(self::REPLACEMENT, self::stored()['password']));
        self::assertSame(0, (int) self::stored()['force_password_reset']);
        self::assertSame('New Name', self::stored()['user_name']);
    }
}
