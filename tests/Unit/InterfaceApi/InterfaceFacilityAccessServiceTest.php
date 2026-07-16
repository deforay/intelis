<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\Services\InterfaceApi\InterfaceFacilityAccessService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InterfaceFacilityAccessServiceTest extends TestCase
{
    public function testLocalLisCanManageOnlyConfiguredTestingLab(): void
    {
        self::assertTrue(InterfaceFacilityAccessService::policyAllows(
            101,
            true,
            true,
            101,
            false,
            0,
            false,
            [202]
        ));
        self::assertFalse(InterfaceFacilityAccessService::policyAllows(
            202,
            true,
            true,
            101,
            false,
            0,
            true,
            [202]
        ));
    }

    public function testCloudLisOperatorCanManageOnlyAssignedTestingLab(): void
    {
        self::assertTrue(InterfaceFacilityAccessService::policyAllows(
            101,
            true,
            false,
            0,
            true,
            101,
            false,
            [202]
        ));
        self::assertFalse(InterfaceFacilityAccessService::policyAllows(
            202,
            true,
            false,
            0,
            true,
            101,
            false,
            [202]
        ));
    }

    public function testCentralAdministratorCanManageAuthorizedTestingLabs(): void
    {
        self::assertTrue(InterfaceFacilityAccessService::policyAllows(
            202,
            true,
            false,
            0,
            false,
            0,
            false,
            [202]
        ));
        self::assertTrue(InterfaceFacilityAccessService::policyAllows(
            303,
            true,
            false,
            0,
            false,
            0,
            true,
            []
        ));
        self::assertFalse(InterfaceFacilityAccessService::policyAllows(
            404,
            true,
            false,
            0,
            false,
            0,
            false,
            [202]
        ));
    }

    #[DataProvider('modifyingActions')]
    public function testUnauthorizedFacilityUserCannotPerformModifyingAction(string $action): void
    {
        self::assertContains($action, ['generate', 'reconnect', 'revoke']);
        self::assertFalse(InterfaceFacilityAccessService::policyAllows(
            202,
            true,
            false,
            0,
            true,
            101,
            false,
            [202]
        ));
    }

    /** @return array<string, array{string}> */
    public static function modifyingActions(): array
    {
        return [
            'generate' => ['generate'],
            'reconnect' => ['reconnect'],
            'revoke' => ['revoke'],
        ];
    }

    public function testNonTestingFacilityNeverGetsConnectionManagement(): void
    {
        self::assertFalse(InterfaceFacilityAccessService::policyAllows(
            101,
            false,
            false,
            0,
            false,
            0,
            true,
            [101]
        ));
    }
}
