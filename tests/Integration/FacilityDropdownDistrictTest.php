<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * Two facilities may share a name in different districts. The facility
 * dropdowns add the district to names that repeat, and only to those.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class FacilityDropdownDistrictTest extends TestCase
{
    private const DATABASE = 'intelis_facility_dropdown_district_test';

    private FacilitiesService $facilities;

    protected function setUp(): void
    {
        if ((string) getenv('INTELIS_TEST_DB_HOST') === '' || (string) getenv('INTELIS_TEST_DB_USER') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        $db = LegacyAppHarness::boot(TestRun::database(self::DATABASE), [
            'facility_details', 'health_facilities', 'testing_labs', 'roles', 'user_details',
        ]);
        LegacyAppHarness::withSession(['instanceId' => 'test-instance']);
        AppRegistry::set('request', LegacyAppHarness::withPost([]));

        // sql/init.sql still carries the old country-wide unique name index;
        // migration 5.8.7 drops it, so do the same here.
        $db->rawQuery('ALTER TABLE facility_details DROP INDEX facility_name, DROP INDEX facility_name_2');

        foreach ([1, 2] as $type) {
            $db->rawQuery(
                "INSERT INTO facility_details
                    (facility_id, facility_name, facility_district, facility_type, vlsm_instance_id, status)
                 VALUES
                    ({$type}1, 'Health Centre A', 'North', $type, 'test-instance', 'active'),
                    ({$type}2, 'Health Centre A', 'South', $type, 'test-instance', 'active'),
                    ({$type}3, 'Riverside Clinic', 'North', $type, 'test-instance', 'active')"
            );
            $table = $type === 1 ? 'health_facilities' : 'testing_labs';
            $db->rawQuery(
                "INSERT INTO $table (test_type, facility_id) VALUES
                    ('vl', {$type}1), ('vl', {$type}2), ('vl', {$type}3)"
            );
        }

        $this->facilities = ContainerRegistry::get(FacilitiesService::class);
    }

    protected function tearDown(): void
    {
        LegacyAppHarness::shutdown();
    }

    public function testHealthFacilitiesShowDistrictOnRepeatedNamesOnly(): void
    {
        $list = $this->facilities->getHealthFacilities('vl', true, false, ['facility_type = 1']);

        self::assertSame('Health Centre A (North)', $list[11]);
        self::assertSame('Health Centre A (South)', $list[12]);
        self::assertSame('Riverside Clinic', $list[13]);
    }

    public function testTestingLabsShowDistrictOnRepeatedNamesOnly(): void
    {
        $list = $this->facilities->getTestingLabs('vl');

        self::assertSame('Health Centre A (North)', $list[21]);
        self::assertSame('Health Centre A (South)', $list[22]);
        self::assertSame('Riverside Clinic', $list[23]);
    }

    public function testAllColumnsPathIsUnchanged(): void
    {
        $names = array_column($this->facilities->getTestingLabs('vl', true, true), 'facility_name', 'facility_id');

        self::assertSame('Health Centre A', $names[21]);
        self::assertSame('Health Centre A', $names[22]);
    }

    public function testFacilitiesDropdownShowsDistrictOnRepeatedNamesOnly(): void
    {
        $html = $this->facilities->getFacilitiesDropdown('vl', 1);

        self::assertStringContainsString("value='11'>Health Centre A (North)</option>", $html);
        self::assertStringContainsString("value='12'>Health Centre A (South)</option>", $html);
        self::assertStringContainsString("value='13'>Riverside Clinic</option>", $html);
    }

    public function testRepeatedNameWithoutDistrictStaysUnchanged(): void
    {
        $labels = FacilitiesService::addDistrictToRepeatedNames(
            [1 => 'Health Centre A', 2 => 'health centre a ', 3 => 'Unique'],
            [1 => 'North', 2 => null, 3 => 'North']
        );

        self::assertSame([1 => 'Health Centre A (North)', 2 => 'health centre a ', 3 => 'Unique'], $labels);
    }
}
