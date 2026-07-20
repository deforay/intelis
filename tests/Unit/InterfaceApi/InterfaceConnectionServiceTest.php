<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\HttpHandlers\InterfaceApi\GetConnectionHandler;
use App\Services\InterfaceApi\InterfaceConnectionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Slim\Psr7\Factory\ServerRequestFactory;

final class InterfaceConnectionServiceTest extends TestCase
{
    /**
     * The facility must come from the credential, never the request. The service is
     * deliberately left unconstructed: if the handler reached it at all, this would
     * fail on an uninitialised database connection instead of returning 400.
     */
    public function testConnectionHandlerRejectsClientFacilitySelector(): void
    {
        $service = (new ReflectionClass(InterfaceConnectionService::class))->newInstanceWithoutConstructor();
        $handler = new GetConnectionHandler($service);
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/interface/connection?facilityId=202')
            ->withQueryParams(['facilityId' => '202'])
            ->withAttribute('interfaceInstallation', [
                'facility_id' => 101,
                'credential_scopes' => ['connection:read'],
            ]);

        $response = $handler($request);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('facility_selector_not_allowed', $payload['error']['code']);
    }

    /**
     * The instrument query is scoped to the installation's lab and selects stable ids
     * only. Asserted against the source because the query needs a live database.
     */
    public function testConnectionQueryUsesStableIdsAndFacilityScope(): void
    {
        $service = file_get_contents(
            dirname(__DIR__, 3) . '/app/classes/Services/InterfaceApi/InterfaceConnectionService.php'
        );

        self::assertIsString($service);
        self::assertStringContainsString('im.config_machine_id', $service);
        self::assertStringContainsString('WHERE i.lab_id = ?', $service);
        self::assertStringNotContainsString('im.file_name', $service);
        self::assertStringNotContainsString('im.latitude', $service);
        self::assertStringNotContainsString('im.longitude', $service);
    }
}
