<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use App\Middlewares\Api\ApiAuthMiddleware;
use App\Services\UsersService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * The v1.1 auth middleware must step aside for the paths that authenticate
 * themselves. A request with no Authorization header reaches the handler on
 * those paths and is refused everywhere else.
 */
final class ApiAuthMiddlewareExclusionTest extends TestCase
{
    private function middleware(): ApiAuthMiddleware
    {
        // UsersService is final and needs a database; an excluded path never touches it.
        $users = (new ReflectionClass(UsersService::class))->newInstanceWithoutConstructor();

        return new ApiAuthMiddleware($users);
    }

    private function reached(string $path): bool
    {
        $handler = new class implements RequestHandlerInterface {
            public bool $reached = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->reached = true;
                return new Response(204);
            }
        };
        $request = (new ServerRequestFactory())->createServerRequest('POST', $path);
        $this->middleware()->process($request, $handler);

        return $handler->reached;
    }

    public function testV2AndHealthReachTheirHandlersWithoutABearer(): void
    {
        self::assertTrue($this->reached('/api/v2/user/profile'));
        self::assertTrue($this->reached('/api/v1.1/health'));
    }

    public function testAV11EndpointWithoutABearerIsStopped(): void
    {
        self::assertFalse($this->reached('/api/v1.1/vl/fetch-results.php'));
    }
}
