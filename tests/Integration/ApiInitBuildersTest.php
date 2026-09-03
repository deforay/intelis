<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The two list builders behind init.php that dominated its response time and
 * size on a national instance. The district list used to run one query per
 * district; the facility list carried facility_attributes that no app build
 * reads. Both shapes are pinned here so the app keeps getting what it parses.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiInitBuildersTest extends TestCase
{
    private const DATABASE = 'intelis_api_init_test';

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    protected function setUp(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        LegacyAppHarness::boot(self::DATABASE, ['geographical_divisions', 'facility_details', 'health_facilities']);

        $db = LegacyAppHarness::db();
        $db->insert('geographical_divisions', [
            'geo_id' => 1,
            'geo_name' => 'North',
            'geo_parent' => '0',
            'geo_status' => 'active',
        ]);
        $db->insert('geographical_divisions', [
            'geo_id' => 10,
            'geo_name' => 'Alpha',
            'geo_parent' => '1',
            'geo_status' => 'active',
        ]);
        $db->insert('geographical_divisions', [
            'geo_id' => 11,
            'geo_name' => 'Beta',
            'geo_parent' => '1',
            'geo_status' => 'active',
        ]);
        $db->insert('geographical_divisions', [
            'geo_id' => 12,
            'geo_name' => 'Empty',
            'geo_parent' => '1',
            'geo_status' => 'active',
        ]);
        foreach ([[1, 'Clinic Two', 10], [2, 'Clinic One', 10], [3, 'Clinic Three', 11]] as [$id, $name, $district]) {
            $db->insert('facility_details', [
                'facility_id' => $id,
                'facility_name' => $name,
                'facility_code' => "F$id",
                'facility_state_id' => 1,
                'facility_state' => 'North',
                'facility_district_id' => $district,
                'facility_district' => $district === 10 ? 'Alpha' : 'Beta',
                'facility_attributes' => '{"big":"' . str_repeat('x', 200) . '"}',
                'status' => 'active',
            ]);
            $db->insert('health_facilities', ['facility_id' => $id, 'test_type' => 'vl']);
        }
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testDistrictListGroupsFacilitiesPerDistrictInNameOrder(): void
    {
        $districts = ContainerRegistry::get(CommonService::class)->getDistrictDetailsApi(null, true, null);

        // Only districts that have an active facility, in district name order.
        self::assertSame(['Alpha', 'Beta'], array_column($districts, 'show'));
        self::assertSame([10, 11], array_map('intval', array_column($districts, 'value')));

        $alpha = $districts[0];
        self::assertSame(['Clinic One', 'Clinic Two'], array_column($alpha['facilityDetails'], 'show'));
        self::assertSame([2, 1], array_map('intval', array_column($alpha['facilityDetails'], 'value')));
        self::assertSame([['value' => 1, 'show' => 'North']], array_map(
            static fn(array $p): array => ['value' => (int) $p['value'], 'show' => $p['show']],
            $alpha['provinceDetails']
        ));

        self::assertSame(['Clinic Three'], array_column($districts[1]['facilityDetails'], 'show'));
    }

    #[RunInSeparateProcess]
    public function testFacilityListKeepsTheFieldsTheAppStoresAndDropsAttributes(): void
    {
        $facilities = ContainerRegistry::get(CommonService::class)
            ->getAppHealthFacilitiesAPI(null, null, false, 0, false, null, null);

        self::assertCount(3, $facilities);
        $row = $facilities[0];
        foreach (
            ['facility_id', 'facility_name', 'facility_code', 'facility_state', 'facility_state_id',
             'facility_district', 'facility_district_id', 'other_id', 'testing_points', 'status'] as $field
        ) {
            self::assertArrayHasKey($field, $row, $field);
        }
        self::assertArrayNotHasKey('facility_attributes', $row);
    }
}
