<?php

declare(strict_types=1);

namespace App\Services\InterfaceApi;

use App\Exceptions\InterfaceApiException;
use App\Services\CommonService;
use App\Services\DatabaseService;

final readonly class InterfaceFacilityAccessService
{
    public function __construct(
        private CommonService $commonService,
        private DatabaseService $db
    ) {
    }

    public function canManage(int $facilityId): bool
    {
        $facility = $this->db->rawQueryOne(
            'SELECT facility_type FROM facility_details WHERE facility_id = ? LIMIT 1',
            [$facilityId]
        );
        $facilityType = (int) ($facility['facility_type'] ?? 0);

        return self::policyAllows(
            $facilityId,
            $facilityType === 2,
            $this->commonService->isLISInstance(),
            (int) ($this->commonService->getSystemConfig('sc_testing_lab_id') ?? 0),
            $this->commonService->isCloudLisNonAdmin(),
            (int) ($_SESSION['labId'] ?? 0),
            $this->commonService->isSTSInstance() && (int) ($_SESSION['roleId'] ?? 0) === 1,
            self::parseFacilityMap($_SESSION['facilityMap'] ?? '')
        );
    }

    public function assertCanManage(int $facilityId): void
    {
        if (!$this->canManage($facilityId)) {
            throw new InterfaceApiException(
                'facility_access_denied',
                'You are not authorized to manage Interface Tool connections for this facility.',
                403
            );
        }
    }

    /** @param list<int> $authorizedFacilityIds */
    public static function policyAllows(
        int $facilityId,
        bool $isTestingLab,
        bool $isLocalLis,
        int $configuredLabId,
        bool $isCloudLisOperator,
        int $assignedLabId,
        bool $isCentralSuperAdmin,
        array $authorizedFacilityIds
    ): bool {
        if ($facilityId <= 0 || !$isTestingLab) {
            return false;
        }
        if ($isLocalLis) {
            return $configuredLabId > 0 && $facilityId === $configuredLabId;
        }
        if ($isCloudLisOperator) {
            return $assignedLabId > 0 && $facilityId === $assignedLabId;
        }
        if ($isCentralSuperAdmin) {
            return true;
        }
        return in_array($facilityId, $authorizedFacilityIds, true);
    }

    /** @return list<int> */
    private static function parseFacilityMap(string|array|null $facilityMap): array
    {
        $values = is_array($facilityMap) ? $facilityMap : explode(',', (string) $facilityMap);
        return array_values(array_unique(array_filter(array_map('intval', $values))));
    }
}
