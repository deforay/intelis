<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use App\Middlewares\App\AppAuthMiddleware;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The Edit Profile save needs a signed-in user. It was public so Recency could
 * post password changes to it without one; that branch is gone. A user who has
 * to change their password must still be able to save the change there.
 */
final class AppAuthMiddlewareEditProfileTest extends TestCase
{
    private static function isPublic(string $path): bool
    {
        $method = new ReflectionMethod(AppAuthMiddleware::class, 'isPublic');
        return (bool) $method->invoke(new AppAuthMiddleware(), $path);
    }

    public function testTheEditProfileSaveNeedsASignedInUser(): void
    {
        $this->assertFalse(self::isPublic('/users/edit-profile-helper.php'));
    }

    public function testTheLoginPagesStayPublic(): void
    {
        $this->assertTrue(self::isPublic('/login/login.php'));
        $this->assertTrue(self::isPublic('/login/loginProcess.php'));
    }

    public function testAUserWhoMustChangeTheirPasswordCanStillSaveIt(): void
    {
        $this->assertTrue(AppAuthMiddleware::allowedDuringPasswordReset('/users/edit-profile.php'));
        $this->assertTrue(AppAuthMiddleware::allowedDuringPasswordReset('/users/edit-profile-helper.php'));
        $this->assertTrue(AppAuthMiddleware::allowedDuringPasswordReset('/includes/generate-password.php'));
    }

    public function testAUserWhoMustChangeTheirPasswordIsSentBackFromOtherPages(): void
    {
        $this->assertFalse(AppAuthMiddleware::allowedDuringPasswordReset('/dashboard/index.php'));
        $this->assertFalse(AppAuthMiddleware::allowedDuringPasswordReset('/users/change-password-helper.php'));
    }
}
