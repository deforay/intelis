<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\Exceptions\InterfaceApiException;
use App\Services\InterfaceApi\InterfaceCredentialService;
use App\Services\InterfaceApi\InterfaceInstallationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryInterfaceInstallationRepository;

final class InterfaceInstallationServiceTest extends TestCase
{
    private InMemoryInterfaceInstallationRepository $repository;
    private InterfaceInstallationService $service;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->repository = new InMemoryInterfaceInstallationRepository();
        $this->service = new InterfaceInstallationService($this->repository, new InterfaceCredentialService());
        $this->now = new DateTimeImmutable('2026-07-16 10:00:00');
    }

    public function testActivationIssuesIndependentScopedCredential(): void
    {
        $code = $this->createCode(101);
        $activation = $this->service->activate($code, 'source-installation-001', 'Laboratory computer', $this->now);

        self::assertNotSame('source-installation-001', $activation['installationId']);
        self::assertSame('source-installation-001', $activation['sourceInstallationId']);
        self::assertSame(InterfaceInstallationService::SCOPES, $activation['scopes']);

        $authenticated = $this->service->authenticate($activation['credential'], 'connection:read');
        self::assertSame(101, $authenticated['facility_id']);
        self::assertSame($activation['installationId'], $authenticated['installation_id']);
    }

    public function testExpiredActivationCodeIsRejected(): void
    {
        $code = $this->createCode(101, 5);

        $this->assertInterfaceError(
            'activation_code_expired',
            fn() => $this->service->activate(
                $code,
                'source-installation-002',
                'Expired computer',
                $this->now->modify('+6 minutes')
            )
        );
    }

    public function testActivationCodeCannotBeReplayed(): void
    {
        $code = $this->createCode(101);
        $this->service->activate($code, 'source-installation-003', 'First computer', $this->now);

        $this->assertInterfaceError(
            'activation_code_used',
            fn() => $this->service->activate($code, 'source-installation-004', 'Replay computer', $this->now)
        );
    }

    public function testRevocationInvalidatesOnlyThatInstallationCredential(): void
    {
        $first = $this->service->activate($this->createCode(101), 'source-installation-005', 'First', $this->now);
        $second = $this->service->activate($this->createCode(101), 'source-installation-006', 'Second', $this->now);

        self::assertTrue($this->service->revoke($first['installationId'], $this->now));
        $this->assertInterfaceError(
            'invalid_credential',
            fn() => $this->service->authenticate($first['credential'], 'connection:read')
        );
        self::assertSame(101, $this->service->authenticate($second['credential'], 'connection:read')['facility_id']);
    }

    public function testOneFacilityCanHaveMultipleInstallations(): void
    {
        $first = $this->service->activate($this->createCode(101), 'source-installation-007', 'Bench one', $this->now);
        $second = $this->service->activate($this->createCode(101), 'source-installation-008', 'Bench two', $this->now);

        self::assertNotSame($first['installationId'], $second['installationId']);
        self::assertCount(2, $this->service->listInstallations(101));
    }

    public function testAuthenticationEnforcesCredentialScope(): void
    {
        $activation = $this->service->activate(
            $this->createCode(101),
            'source-installation-scoped',
            'Scoped computer',
            $this->now
        );
        $this->repository->setScopes($activation['installationId'], ['telemetry:write']);

        $this->assertInterfaceError(
            'insufficient_scope',
            fn() => $this->service->authenticate($activation['credential'], 'connection:read')
        );
    }

    public function testSameFacilityCanClaimExistingSourceWithoutChangingServerIdentity(): void
    {
        $first = $this->service->activate($this->createCode(101), 'source-installation-009', 'Relayed', $this->now);
        $claimed = $this->service->activate(
            $this->createCode(101),
            'source-installation-009',
            'Direct',
            $this->now->modify('+1 minute')
        );

        self::assertSame($first['installationId'], $claimed['installationId']);
        self::assertNotSame($first['credential'], $claimed['credential']);
        $this->assertInterfaceError(
            'invalid_credential',
            fn() => $this->service->authenticate($first['credential'], 'connection:read')
        );
    }

    public function testDirectActivationClaimsSourcePreviouslyObservedThroughRelay(): void
    {
        $serverInstallationId = '550e8400-e29b-41d4-a716-446655440000';
        $sourceInstallationId = 'source-installation-relayed';
        $this->repository->observe($serverInstallationId, $sourceInstallationId, 101);

        $claimed = $this->service->activate(
            $this->createCode(101),
            $sourceInstallationId,
            'Direct laboratory computer',
            $this->now
        );

        self::assertSame($serverInstallationId, $claimed['installationId']);
        self::assertSame(
            101,
            $this->service->authenticate($claimed['credential'], 'connection:read')['facility_id']
        );
    }

    public function testCrossFacilityClaimIsRejectedWithoutConsumingCode(): void
    {
        $source = 'source-installation-010';
        $this->service->activate($this->createCode(101), $source, 'Facility A', $this->now);
        $facilityBCode = $this->createCode(202);

        try {
            $this->service->activate($facilityBCode, $source, 'Facility B', $this->now);
            self::fail('Expected a cross-facility conflict.');
        } catch (InterfaceApiException $exception) {
            self::assertSame('source_facility_conflict', $exception->getErrorCode());
        }

        $valid = $this->service->activate($facilityBCode, 'source-installation-011', 'Facility B', $this->now);
        self::assertSame(202, $this->service->authenticate($valid['credential'], 'connection:read')['facility_id']);
    }

    private function createCode(int $facilityId, int $ttlMinutes = 30): string
    {
        return $this->service->createActivationCode($facilityId, $ttlMinutes, 'test', $this->now)['activationCode'];
    }

    private function assertInterfaceError(string $errorCode, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected Interface API error {$errorCode}.");
        } catch (InterfaceApiException $exception) {
            self::assertSame($errorCode, $exception->getErrorCode());
        }
    }
}
