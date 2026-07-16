<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\HttpHandlers\InterfaceApi\ActivateInstallationHandler;
use App\Middlewares\Api\InterfaceActivationGuardMiddleware;
use App\Services\InterfaceApi\InterfaceCredentialService;
use App\Services\InterfaceApi\InterfaceInstallationService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\InMemoryInterfaceInstallationRepository;

final class InterfaceHttpBoundaryTest extends TestCase
{
    public function testActivationRejectsClientSuppliedFacilityField(): void
    {
        $handler = new ActivateInstallationHandler(new InterfaceInstallationService(
            new InMemoryInterfaceInstallationRepository(),
            new InterfaceCredentialService()
        ));
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/activate')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody([
                'activationCode' => 'not-used',
                'sourceInstallationId' => 'source-installation-100',
                'displayName' => 'Laboratory computer',
                'facilityId' => 202,
            ]);

        $response = $handler($request);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('unexpected_field', $payload['error']['code']);
    }

    public function testActivationBodyLimitIsEnforcedBeforeHandler(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/activate')
            ->withHeader('Content-Length', '16385');
        $next = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };

        $response = (new InterfaceActivationGuardMiddleware())->process($request, $next);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('payload_too_large', $payload['error']['code']);
    }

    public function testInterfaceRuntimeDoesNotUseLegacyTrackingOrLogging(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root . '/app/classes/HttpHandlers/InterfaceApi',
            $root . '/app/classes/Middlewares/Api/InterfaceActivationGuardMiddleware.php',
            $root . '/app/classes/Middlewares/Api/InterfaceApiEnabledMiddleware.php',
            $root . '/app/classes/Middlewares/Api/InterfaceInstallationAuthMiddleware.php',
            $root . '/app/classes/Repositories/InterfaceApi',
            $root . '/app/classes/Services/InterfaceApi',
        ];

        foreach ($this->phpFiles($paths) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('LoggerUtility', $contents, $file);
            self::assertStringNotContainsString('addApiTracking', $contents, $file);
            self::assertStringNotContainsString('track_api_requests', $contents, $file);
        }

        $guard = file_get_contents(
            $root . '/app/classes/Middlewares/Api/InterfaceActivationGuardMiddleware.php'
        );
        self::assertIsString($guard);
        self::assertStringContainsString('RateLimitUtility::exceeded', $guard);
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private function phpFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;
                continue;
            }
            foreach (glob($path . '/*.php') ?: [] as $file) {
                $files[] = $file;
            }
        }
        return $files;
    }
}
