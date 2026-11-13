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
 * A facility name is unique only within its province and district:
 * FacilitiesService::isFacilityNameTakenInArea() is the check the add and edit
 * forms rely on now that the unique index on facility_name is gone.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class FacilityNameInAreaTest extends TestCase
{
    private const DATABASE = 'intelis_facility_name_area_test';

    private FacilitiesService $facilities;

    protected function setUp(): void
    {
        if ((string) getenv('INTELIS_TEST_DB_HOST') === '' || (string) getenv('INTELIS_TEST_DB_USER') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        $db = LegacyAppHarness::boot(TestRun::database(self::DATABASE), [
            'facility_details', 'geographical_divisions',
        ]);
        LegacyAppHarness::withSession(['instanceId' => 'test-instance']);
        AppRegistry::set('request', LegacyAppHarness::withPost([]));

        // sql/init.sql still carries the old country-wide unique name index;
        // migration 5.8.7 drops it, so do the same here.
        $db->rawQuery('ALTER TABLE facility_details DROP INDEX facility_name, DROP INDEX facility_name_2');

        $db->rawQuery(
            "INSERT INTO geographical_divisions (geo_id, geo_name, geo_parent, geo_status) VALUES
                (1, 'North', 0, 'active'), (2, 'Capital', 1, 'active'), (5, 'Hills', 1, 'active'),
                (3, 'South', 0, 'active'), (4, 'Coast', 3, 'active')"
        );
        $db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_state_id, facility_district_id, facility_type,
                 vlsm_instance_id, status)
             VALUES
                (1, 'Riverside Clinic', 1, 2, 1, 'test-instance', 'active'),
                (2, '\tMaman  Wa Huruma ', 3, 4, 1, 'test-instance', 'active'),
                (3, 'Old Mission', NULL, NULL, 1, 'test-instance', 'active')"
        );

        $this->facilities = ContainerRegistry::get(FacilitiesService::class);
    }

    protected function tearDown(): void
    {
        LegacyAppHarness::shutdown();
    }

    public function testSameNameInAnotherDistrictIsFree(): void
    {
        self::assertFalse($this->facilities->isFacilityNameTakenInArea('Riverside Clinic', 3, 4));
        self::assertFalse(
            $this->facilities->isFacilityNameTakenInArea('Riverside Clinic', 1, 5),
            'Another district of the same province'
        );
    }

    public function testSameNameInTheSameDistrictIsTaken(): void
    {
        self::assertTrue($this->facilities->isFacilityNameTakenInArea('Riverside Clinic', 1, 2));
        self::assertTrue(
            $this->facilities->isFacilityNameTakenInArea('Riverside Clinic', '1', '2'),
            'Ids as posted by a form'
        );
    }

    public function testCaseAndWhitespaceAreIgnored(): void
    {
        self::assertTrue($this->facilities->isFacilityNameTakenInArea('RIVERSIDE clinic', 1, 2));
        self::assertTrue($this->facilities->isFacilityNameTakenInArea("  Riverside   Clinic\t", 1, 2));
        self::assertTrue($this->facilities->isFacilityNameTakenInArea("Riverside\tClinic", 1, 2));
        self::assertTrue(
            $this->facilities->isFacilityNameTakenInArea('maman wa huruma', 3, 4),
            'Stored name has a tab and extra spaces'
        );
    }

    public function testTheFacilitysOwnIdIsFreeOnEdit(): void
    {
        self::assertFalse($this->facilities->isFacilityNameTakenInArea('Riverside Clinic', 1, 2, 1));
        self::assertTrue($this->facilities->isFacilityNameTakenInArea('Riverside Clinic', 1, 2, 2));
    }

    public function testNoProvinceAndNoDistrictMatchOnlyFacilitiesWithout(): void
    {
        self::assertTrue($this->facilities->isFacilityNameTakenInArea('Old Mission', null, null));
        self::assertTrue($this->facilities->isFacilityNameTakenInArea('Old Mission', '', ''), 'Blank form fields');
        self::assertFalse($this->facilities->isFacilityNameTakenInArea('Old Mission', 1, 2));
        self::assertFalse($this->facilities->isFacilityNameTakenInArea('Riverside Clinic', null, null));
    }
}
