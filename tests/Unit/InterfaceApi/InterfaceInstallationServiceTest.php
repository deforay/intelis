<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\Exceptions\InterfaceApiException;
use App\Services\InterfaceApi\InterfaceCredentialService;
use App\Services\InterfaceApi\InterfaceInstallationService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testNewCodeRejectsAnAlreadyRegisteredSource(): void
    {
        $first = $this->service->activate($this->createCode(101), 'source-installation-009', 'Relayed', $this->now);
        self::assertNotEmpty($first['installationId']);

        $this->assertInterfaceError(
            'source_already_registered',
            fn() => $this->service->activate(
                $this->createCode(101),
                'source-installation-009',
                'Duplicate',
                $this->now->modify('+1 minute')
            )
        );
    }

    public function testReconnectPreservesIdentitiesAndRotatesCredential(): void
    {
        $first = $this->service->activate(
            $this->createCode(101),
            'source-installation-reconnect',
            'Original computer',
            $this->now
        );
        $reconnectCode = $this->service->createReconnectCode(
            101,
            $first['installationId'],
            30,
            'test',
            $this->now
        )['activationCode'];
        $reconnected = $this->service->activate(
            $reconnectCode,
            null,
            null,
            $this->now->modify('+1 minute')
        );

        self::assertSame($first['installationId'], $reconnected['installationId']);
        self::assertSame($first['sourceInstallationId'], $reconnected['sourceInstallationId']);
        self::assertSame(2, $reconnected['credentialVersion']);
        self::assertNotSame($first['credential'], $reconnected['credential']);
        $this->assertInterfaceError(
            'invalid_credential',
            fn() => $this->service->authenticate($first['credential'], 'connection:read')
        );
        self::assertSame(
            101,
            $this->service->authenticate($reconnected['credential'], 'connection:read')['facility_id']
        );
    }

    public function testDirectActivationClaimsSourcePreviouslyObservedThroughRelay(): void
    {
        $serverInstallationId = '550e8400-e29b-41d4-a716-446655440000';
        $sourceInstallationId = 'source-installation-relayed';
        $this->repository->observe($serverInstallationId, $sourceInstallationId, 101);

        $reconnectCode = $this->service->createReconnectCode(
            101,
            $serverInstallationId,
            30,
            'test',
            $this->now
        )['activationCode'];
        $claimed = $this->service->activate($reconnectCode, null, null, $this->now);

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

    public function testReconnectCodeCannotBeReplayed(): void
    {
        $installation = $this->service->activate(
            $this->createCode(101),
            'source-installation-replay',
            'Replay computer',
            $this->now
        );
        $code = $this->createReconnectCode(101, $installation['installationId']);
        $this->service->activate($code, null, null, $this->now->modify('+1 minute'));

        $this->assertInterfaceError(
            'activation_code_used',
            fn() => $this->service->activate($code, null, null, $this->now->modify('+2 minutes'))
        );
    }

    public function testExpiredReconnectCodeFails(): void
    {
        $installation = $this->service->activate(
            $this->createCode(101),
            'source-installation-expired-reconnect',
            'Expired reconnect',
            $this->now
        );
        $code = $this->createReconnectCode(101, $installation['installationId'], 5);

        $this->assertInterfaceError(
            'activation_code_expired',
            fn() => $this->service->activate($code, null, null, $this->now->modify('+6 minutes'))
        );
    }

    public function testCrossFacilityReconnectCodeGenerationFails(): void
    {
        $installation = $this->service->activate(
            $this->createCode(101),
            'source-installation-cross-reconnect',
            'Facility A',
            $this->now
        );

        $this->assertInterfaceError(
            'installation_not_found',
            fn() => $this->service->createReconnectCode(
                202,
                $installation['installationId'],
                30,
                'test',
                $this->now
            )
        );
    }

    public function testReconnectDoesNotAffectOtherInstallations(): void
    {
        $first = $this->service->activate(
            $this->createCode(101),
            'source-installation-reconnect-one',
            'First',
            $this->now
        );
        $second = $this->service->activate(
            $this->createCode(101),
            'source-installation-reconnect-two',
            'Second',
            $this->now
        );
        $secondCredential = $second['credential'];

        $reconnected = $this->service->activate(
            $this->createReconnectCode(101, $first['installationId']),
            null,
            null,
            $this->now->modify('+1 minute')
        );

        self::assertSame($first['installationId'], $reconnected['installationId']);
        self::assertSame(
            $second['installationId'],
            $this->service->authenticate($secondCredential, 'connection:read')['installation_id']
        );
    }

    public function testReconnectReactivatesOnlySelectedRevokedInstallation(): void
    {
        $first = $this->service->activate(
            $this->createCode(101),
            'source-installation-reactivate-one',
            'First',
            $this->now
        );
        $second = $this->service->activate(
            $this->createCode(101),
            'source-installation-reactivate-two',
            'Second',
            $this->now
        );
        self::assertTrue($this->service->revokeForFacility($first['installationId'], 101, $this->now));
        self::assertTrue($this->service->revokeForFacility($second['installationId'], 101, $this->now));

        $reconnected = $this->service->activate(
            $this->createReconnectCode(101, $first['installationId']),
            null,
            null,
            $this->now->modify('+1 minute')
        );

        self::assertSame('active', $this->service->getInstallation($reconnected['installationId'])['status']);
        self::assertSame('revoked', $this->service->getInstallation($second['installationId'])['status']);
    }

    public function testReconnectCodeCanBeRevokedIndependently(): void
    {
        $installation = $this->service->activate(
            $this->createCode(101),
            'source-installation-code-revoke',
            'Code revoke',
            $this->now
        );
        $code = $this->service->createReconnectCode(
            101,
            $installation['installationId'],
            30,
            'test',
            $this->now
        );
        self::assertTrue($this->service->revokeActivationCode($code['activationCodeId'], 101, $this->now));

        $this->assertInterfaceError(
            'activation_code_revoked',
            fn() => $this->service->activate($code['activationCode'], null, null, $this->now)
        );
        self::assertSame('active', $this->service->getInstallation($installation['installationId'])['status']);
    }

    public function testGeneratedCodeIsTwelveCrockfordCharactersInThreeGroups(): void
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $code = $this->createCode(101);
            self::assertMatchesRegularExpression(
                '/^[0-9A-HJKMNP-TV-Z]{4}(?:-[0-9A-HJKMNP-TV-Z]{4}){2}$/D',
                $code
            );
            self::assertSame(
                InterfaceInstallationService::ACTIVATION_CODE_LENGTH,
                strlen(str_replace('-', '', $code))
            );
        }
    }

    public function testReconnectCodeUsesTheSameShortFormat(): void
    {
        $installation = $this->service->activate(
            $this->createCode(101),
            'source-installation-format',
            'Format check',
            $this->now
        );

        self::assertMatchesRegularExpression(
            '/^[0-9A-HJKMNP-TV-Z]{4}(?:-[0-9A-HJKMNP-TV-Z]{4}){2}$/D',
            $this->createReconnectCode(101, $installation['installationId'])
        );
    }

    /**
     * @return list<array{0: string}>
     */
    public static function malformedCodeProvider(): array
    {
        return [
            'eleven characters' => ['7KDM-P4XR-W9T'],
            'thirteen characters' => ['7KDM-P4XR-W9TCP'],
            'legacy thirty-two characters' => ['0123-4567-89AB-CDEF-GHJK-MNPQ-RSTV-WXYZ'],
            'empty' => [''],
            'stray punctuation' => ['7KDM.P4XR.W9TC'],
            'non-alphanumeric padding' => ['7KDM-P4XR-W9T#'],
        ];
    }

    #[DataProvider('malformedCodeProvider')]
    public function testMalformedCodesAreRejected(string $code): void
    {
        $this->assertInterfaceError(
            'invalid_activation_code',
            fn() => $this->service->activate($code, 'source-installation-malformed', 'Bad', $this->now)
        );
    }

    /**
     * @return list<array{0: callable(string): string}>
     */
    public static function equivalentCodeFormatProvider(): array
    {
        return [
            'as issued' => [static fn(string $code): string => $code],
            'lowercase' => [static fn(string $code): string => strtolower($code)],
            'ungrouped' => [static fn(string $code): string => str_replace('-', '', $code)],
            'space separated' => [static fn(string $code): string => str_replace('-', ' ', $code)],
            'surrounding whitespace' => [static fn(string $code): string => "  {$code}\n"],
        ];
    }

    /** @param callable(string): string $transform */
    #[DataProvider('equivalentCodeFormatProvider')]
    public function testEquivalentCodeFormatsAllActivate(callable $transform): void
    {
        $code = $this->createCode(101);

        $activation = $this->service->activate(
            $transform($code),
            'source-installation-format-variant',
            'Variant',
            $this->now
        );

        self::assertSame(
            101,
            $this->service->authenticate($activation['credential'], 'connection:read')['facility_id']
        );
    }

    public function testLookAlikeCharactersAreNormalized(): void
    {
        $substitutions = ['1' => 'I', '0' => 'O', 'V' => 'U'];
        $code = null;

        // Generated codes exclude I, L, O and U, so find one containing a character a
        // human might mistype as one of them.
        for ($attempt = 0; $attempt < 50 && $code === null; $attempt++) {
            $candidate = $this->createCode(101);
            if (strpbrk($candidate, '10V') !== false) {
                $code = $candidate;
            }
        }

        self::assertNotNull($code, 'Expected a code containing at least one substitutable character.');

        $mistyped = strtr($code, $substitutions);
        self::assertNotSame($code, $mistyped);

        $activation = $this->service->activate($mistyped, 'source-installation-lookalike', 'Mistyped', $this->now);
        self::assertSame(
            101,
            $this->service->authenticate($activation['credential'], 'connection:read')['facility_id']
        );
    }

    public function testNewAndReconnectCodesBothExpireAfterThirtyMinutes(): void
    {
        $new = $this->service->createActivationCode(101, 30, 'test', $this->now);
        self::assertSame($this->now->modify('+30 minutes')->format(DATE_ATOM), $new['expiresAt']);

        $installation = $this->service->activate(
            $new['activationCode'],
            'source-installation-ttl',
            'TTL check',
            $this->now
        );
        $reconnect = $this->service->createReconnectCode(
            101,
            $installation['installationId'],
            30,
            'test',
            $this->now
        );
        self::assertSame($this->now->modify('+30 minutes')->format(DATE_ATOM), $reconnect['expiresAt']);

        $this->assertInterfaceError(
            'activation_code_expired',
            fn() => $this->service->activate(
                $reconnect['activationCode'],
                null,
                null,
                $this->now->modify('+31 minutes')
            )
        );
    }

    public function testCodeRemainsValidJustBeforeTheThirtyMinuteExpiry(): void
    {
        $code = $this->createCode(101);

        $activation = $this->service->activate(
            $code,
            'source-installation-just-in-time',
            'Just in time',
            $this->now->modify('+29 minutes')
        );

        self::assertSame(
            101,
            $this->service->authenticate($activation['credential'], 'connection:read')['facility_id']
        );
    }

    private function createCode(int $facilityId, int $ttlMinutes = 30): string
    {
        return $this->service->createActivationCode($facilityId, $ttlMinutes, 'test', $this->now)['activationCode'];
    }

    private function createReconnectCode(
        int $facilityId,
        string $installationId,
        int $ttlMinutes = 30
    ): string {
        return $this->service->createReconnectCode(
            $facilityId,
            $installationId,
            $ttlMinutes,
            'test',
            $this->now
        )['activationCode'];
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
