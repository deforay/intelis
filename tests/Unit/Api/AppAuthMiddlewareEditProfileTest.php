<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use App\Middlewares\App\AppAuthMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * The Edit Profile save needs a signed-in user. It was public so Recency could
 * post password changes to it without one; that branch is gone. A user who has
 * to change their password must still be able to save the change there, and is
 * sent back to Edit Profile from everywhere else.
 */
final class AppAuthMiddlewareEditProfileTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $this->savedSession = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->savedSession;
    }

    /** @return array{reached: bool, status: int, location: string} */
    private static function post(string $path): array
    {
        $handler = new class implements RequestHandlerInterface {
            public bool $reached = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->reached = true;
                return new Response(200);
            }
        };
        $request = (new ServerRequestFactory())->createServerRequest('POST', $path);

        $response = (new AppAuthMiddleware(cli: false))->process($request, $handler);

        return [
            'reached' => $handler->reached,
            'status' => $response->getStatusCode(),
            'location' => $response->getHeaderLine('Location'),
        ];
    }

    /** @param array<string, mixed> $extra */
    private static function signedIn(array $extra = []): void
    {
        $_SESSION = $extra + [
            'userId' => 'u-1',
            'last_activity' => time(),
            '_session_regenerated' => time(),
        ];
    }

    public function testTheEditProfileSaveNeedsASignedInUser(): void
    {
        $_SESSION = [];

        $result = self::post('/users/edit-profile-helper.php');

        $this->assertFalse($result['reached']);
        $this->assertSame(401, $result['status']);
    }

    public function testASignedInUserCanSaveTheirProfile(): void
    {
        self::signedIn();

        $this->assertTrue(self::post('/users/edit-profile-helper.php')['reached']);
    }

    public function testTheLoginPagesStayOpenWithoutSigningIn(): void
    {
        $_SESSION = [];

        $this->assertTrue(self::post('/login/loginProcess.php')['reached']);
    }

    public function testAUserWhoMustChangeTheirPasswordCanStillSaveIt(): void
    {
        self::signedIn(['forcePasswordReset' => 1]);

        $this->assertTrue(self::post('/users/edit-profile-helper.php')['reached']);
    }

    public function testAUserWhoMustChangeTheirPasswordIsSentBackFromOtherPages(): void
    {
        self::signedIn(['forcePasswordReset' => 1]);

        $result = self::post('/dashboard/index.php');

        $this->assertFalse($result['reached']);
        $this->assertSame('/users/edit-profile.php', $result['location']);
    }
}
