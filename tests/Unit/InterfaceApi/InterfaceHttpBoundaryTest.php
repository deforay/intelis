<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\HttpHandlers\InterfaceApi\ActivateInstallationHandler;
use App\Middlewares\Api\InterfaceRequestGuardMiddleware;
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

        $response = (new InterfaceRequestGuardMiddleware())->process($request, $next);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('payload_too_large', $payload['error']['code']);
    }

    public function testReconnectRequestNeedsOnlyConnectionCode(): void
    {
        $repository = new InMemoryInterfaceInstallationRepository();
        $installationId = '550e8400-e29b-41d4-a716-446655440000';
        $repository->observe($installationId, 'source-installation-clean-reinstall', 101);
        $service = new InterfaceInstallationService($repository, new InterfaceCredentialService());
        $connectionCode = $service->createReconnectCode(101, $installationId)['activationCode'];
        $handler = new ActivateInstallationHandler($service);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1/interface/activate')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody(['activationCode' => $connectionCode]);

        $response = $handler($request);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame($installationId, $payload['installation']['installationId']);
        self::assertSame(
            'source-installation-clean-reinstall',
            $payload['installation']['sourceInstallationId']
        );
    }

    public function testInterfaceRuntimeDoesNotUseLegacyTrackingOrLogging(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root . '/app/classes/HttpHandlers/InterfaceApi',
            $root . '/app/classes/Middlewares/Api/InterfaceRequestGuardMiddleware.php',
            $root . '/app/classes/Middlewares/Api/InterfaceApiEnabledMiddleware.php',
            $root . '/app/classes/Middlewares/Api/InterfaceInstallationAuthMiddleware.php',
            $root . '/app/classes/Repositories/InterfaceApi',
            $root . '/app/classes/Services/InterfaceApi',
            $root . '/app/facilities/interfaceConnectionAction.php',
        ];

        foreach ($this->phpFiles($paths) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('LoggerUtility', $contents, $file);
            self::assertStringNotContainsString('addApiTracking', $contents, $file);
            self::assertStringNotContainsString('track_api_requests', $contents, $file);
        }

        $guard = file_get_contents(
            $root . '/app/classes/Middlewares/Api/InterfaceRequestGuardMiddleware.php'
        );
        self::assertIsString($guard);
        self::assertStringContainsString('RateLimitUtility::exceeded', $guard);

        $facilityAction = file_get_contents($root . '/app/facilities/interfaceConnectionAction.php');
        self::assertIsString($facilityAction);
        self::assertStringContainsString("_requirePrivilege('/facilities/editFacility.php')", $facilityAction);
        self::assertStringContainsString('assertCanManage($facilityId)', $facilityAction);
        self::assertStringContainsString("getGlobalConfig('interface_api_enabled')", $facilityAction);
        preg_match_all('/activityLog\((.*?)\);/s', $facilityAction, $auditCalls);
        foreach ($auditCalls[1] as $auditCall) {
            self::assertStringNotContainsString('activationCode', $auditCall);
            self::assertStringNotContainsString('credential', $auditCall);
        }
    }

    public function testFeatureDisabledUiAndApiRemainFailClosed(): void
    {
        $root = dirname(__DIR__, 3);
        $middleware = file_get_contents(
            $root . '/app/classes/Middlewares/Api/InterfaceApiEnabledMiddleware.php'
        );
        $editFacility = file_get_contents($root . '/app/facilities/editFacility.php');

        self::assertIsString($middleware);
        self::assertStringContainsString("getGlobalConfig('interface_api_enabled')", $middleware);
        self::assertStringContainsString("'interface_api_disabled'", $middleware);
        self::assertStringContainsString('503', $middleware);
        self::assertIsString($editFacility);
        self::assertStringContainsString('$showInterfaceConnections = false;', $editFacility);
        self::assertStringContainsString('$interfaceApiEnabled &&', $editFacility);
    }

    public function testConnectionControlsExistOnlyOnSavedFacilityEditPage(): void
    {
        $root = dirname(__DIR__, 3);
        foreach (
            [
                $root . '/app/facilities/addFacility.php',
                $root . '/app/instruments/add-instrument.php',
                $root . '/app/instruments/add-instrument-helper.php',
            ] as $path
        ) {
            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Interface Tool Connections', $contents, $path);
            self::assertStringNotContainsString('generate-reconnect', $contents, $path);
        }

        $editFacility = file_get_contents($root . '/app/facilities/editFacility.php');
        self::assertIsString($editFacility);
        self::assertStringContainsString('interface-connections.php', $editFacility);
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
