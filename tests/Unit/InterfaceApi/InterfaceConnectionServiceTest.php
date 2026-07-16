<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\HttpHandlers\InterfaceApi\GetConnectionHandler;
use App\Services\InterfaceApi\InterfaceConnectionService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\InMemoryInterfaceConnectionRepository;

final class InterfaceConnectionServiceTest extends TestCase
{
    public function testConnectionUsesOnlyAuthenticatedInstallationFacility(): void
    {
        $service = $this->service();
        $connection = $service->getConnection([
            'facility_id' => 101,
            'credential_scopes' => ['connection:read'],
        ]);

        self::assertSame(101, $connection['facility']['id']);
        self::assertSame('FAC-A', $connection['facility']['code']);
        self::assertSame('instrument-a', $connection['instruments'][0]['id']);
        self::assertSame('intelis', $connection['capabilities']['adapter']['id']);
        self::assertTrue($connection['capabilities']['adapter']['managed']);
        self::assertStringNotContainsString('Facility B', json_encode($connection, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('coordinates', $connection['facility']);
        self::assertArrayNotHasKey('fileName', $connection['instruments'][0]);
    }

    public function testConnectionHandlerRejectsClientFacilitySelector(): void
    {
        $handler = new GetConnectionHandler($this->service());
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

    private function service(): InterfaceConnectionService
    {
        return new InterfaceConnectionService(new InMemoryInterfaceConnectionRepository([
            101 => [
                'facility' => ['id' => 101, 'code' => 'FAC-A', 'name' => 'Facility A'],
                'supportedTests' => ['vl'],
                'instruments' => [[
                    'id' => 'instrument-a',
                    'name' => 'Instrument A',
                    'supportedTests' => ['vl'],
                    'aliases' => ['Analyzer A'],
                ]],
            ],
            202 => [
                'facility' => ['id' => 202, 'code' => 'FAC-B', 'name' => 'Facility B'],
                'supportedTests' => ['tb'],
                'instruments' => [[
                    'id' => 'instrument-b',
                    'name' => 'Instrument B',
                    'supportedTests' => ['tb'],
                    'aliases' => [],
                ]],
            ],
        ]));
    }
}
