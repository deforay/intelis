<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\GenericTestsService;
use App\Services\UsersService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

/**
 * The user, facility and Custom Test edit pages log what changed by comparing
 * a snapshot taken before the save with one taken after. These run the
 * snapshots against the real schema.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ActivityLogSnapshotsTest extends TestCase
{
    private bool $booted = false;

    protected function tearDown(): void
    {
        if ($this->booted) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param list<string> $tables */
    private function boot(array $tables): void
    {
        if (!LegacyAppHarness::configured()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $database = TestRun::database('intelis_activity_log_snapshots_test');
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', [
                'database' => ['db' => $database],
                'modules' => ['vl' => true, 'generic-tests' => true],
            ]);
        }
        LegacyAppHarness::boot($database, $tables);
        $this->booted = true;
        LegacyAppHarness::withSession(['userId' => 'u-admin', 'roleId' => 1]);
    }

    /**
     * @param list<array<string, mixed>> $changes
     * @return array<string, array<string, mixed>>
     */
    private static function byField(array $changes): array
    {
        return array_column($changes, null, 'field');
    }

    #[RunInSeparateProcess]
    public function testAUserEditNamesTheRoleLabAndFacilitiesAndFlagsAccessChanges(): void
    {
        $this->boot([
            'roles', 'facility_details', 'user_details', 'user_facility_map', 'system_config', 'global_config',
        ]);
        $db = LegacyAppHarness::db();
        $db->rawQuery("INSERT INTO roles (role_id, role_name, role_code, status)
                       VALUES (2, 'Lab User', 'LU', 'active'), (3, 'Lab Manager', 'LM', 'active')");
        $db->rawQuery("INSERT INTO facility_details
                              (facility_id, facility_name, vlsm_instance_id, facility_type, status)
                       VALUES (1, 'Central Lab', 'i', 2, 'active'), (2, 'Clinic A', 'i', 1, 'active'),
                              (3, 'Clinic B', 'i', 1, 'active')");
        $db->rawQuery("INSERT INTO user_details
                              (user_id, user_name, login_id, role_id, status, password, testing_lab_id)
                       VALUES ('u-7', 'Sam', 'sam', 2, 'active', 'hash-one', 1)");
        $db->rawQuery("INSERT INTO user_facility_map (user_id, facility_id) VALUES ('u-7', 2)");
        $users = ContainerRegistry::get(UsersService::class);

        $before = $users->activitySnapshot('u-7');
        $db->rawQuery("UPDATE user_details SET role_id = 3, password = 'hash-two' WHERE user_id = 'u-7'");
        $db->rawQuery("DELETE FROM user_facility_map WHERE user_id = 'u-7'");
        $db->rawQuery("INSERT INTO user_facility_map (user_id, facility_id) VALUES ('u-7', 3)");
        $details = $users->activityDetails('u-7', $before);

        $this->assertSame(['type' => 'user', 'id' => 'u-7', 'label' => 'Sam'], $details['entity']);
        $changes = self::byField($details['changes']);
        $this->assertEqualsCanonicalizing(['role', 'password', 'facilities'], array_keys($changes));
        $this->assertSame(['Lab User', 'Lab Manager'], [$changes['role']['from'], $changes['role']['to']]);
        $this->assertSame(['(hidden)', '(hidden)'], [$changes['password']['from'], $changes['password']['to']]);
        $this->assertSame(['Clinic B'], $changes['facilities']['added']);
        $this->assertSame(['Clinic A'], $changes['facilities']['removed']);
        $this->assertContains('role', $details['importantFields']);
        $this->assertSame([], $users->activityDetails('u-7', null)['importantFields'], 'a new user is not flagged');
    }

    #[RunInSeparateProcess]
    public function testAFacilityEditNamesItsTypeAndReadsItsTargetsAndSettings(): void
    {
        $this->boot(['facility_type', 'facility_details', 'testing_labs']);
        $db = LegacyAppHarness::db();
        $db->rawQuery("INSERT INTO facility_type VALUES (1, 'Health Facility'), (2, 'Testing Lab')");
        $db->rawQuery("INSERT INTO facility_details
                              (facility_id, facility_name, vlsm_instance_id, facility_type, status, test_type,
                               facility_attributes)
                       VALUES (5, 'Central Lab', 'i', 2, 'active', 'vl, eid',
                               '{\"display_signature_table\": \"yes\"}')");
        $db->rawQuery("INSERT INTO testing_labs (test_type, facility_id, monthly_target) VALUES ('vl', 5, 100)");
        $facilities = ContainerRegistry::get(FacilitiesService::class);

        $before = $facilities->activitySnapshot(5);
        $db->rawQuery("UPDATE facility_details
                          SET status = 'inactive', test_type = 'vl',
                              facility_attributes = '{\"display_signature_table\": \"no\"}'
                        WHERE facility_id = 5");
        $db->rawQuery("UPDATE testing_labs SET monthly_target = 150 WHERE facility_id = 5");
        $changes = self::byField($facilities->activityDetails(5, $before)['changes']);

        $this->assertSame('Testing Lab', $before['facility_type']);
        $this->assertSame(['active', 'inactive'], [$changes['status']['from'], $changes['status']['to']]);
        $this->assertSame(['eid'], $changes['test_type']['removed']);
        $signature = $changes['facility_attributes.display_signature_table'];
        $this->assertSame('Settings › Display signature table', $signature['label']);
        $target = $changes['targets.vl.monthly_target'];
        $this->assertSame(['100', '150'], [$target['from'], $target['to']]);
    }

    #[RunInSeparateProcess]
    public function testACustomTestEditReadsItsResultSettingsAndLinkedLists(): void
    {
        $this->boot([
            'r_generic_test_categories', 'r_test_types', 'r_generic_sample_types', 'r_generic_test_reasons',
            'r_generic_symptoms', 'r_generic_test_failure_reasons', 'r_generic_sample_rejection_reasons',
            'r_generic_test_result_units', 'r_generic_test_methods', 'generic_test_sample_type_map',
            'generic_test_reason_map', 'generic_test_symptoms_map', 'generic_test_failure_reason_map',
            'generic_sample_rejection_reason_map', 'generic_test_result_units_map', 'generic_test_methods_map',
            'system_config', 'global_config',
        ]);
        $db = LegacyAppHarness::db();
        $db->rawQuery("INSERT INTO r_test_types (test_type_id, test_standard_name, test_status, test_results_config)
                       VALUES (4, 'Hepatitis B VL', 'active',
                               '{\"result_type\": \"quantitative\", \"threshold_value\": \"20\"}')");
        $db->rawQuery("INSERT INTO r_generic_test_methods (test_method_id, test_method_name, test_method_status)
                       VALUES (1, 'PCR', 'active'), (2, 'ELISA', 'active')");
        $db->rawQuery("INSERT INTO generic_test_methods_map (test_method_id, test_type_id) VALUES (1, 4)");
        $generic = ContainerRegistry::get(GenericTestsService::class);

        $before = $generic->activitySnapshot(4);
        $db->rawQuery("UPDATE r_test_types
                          SET test_results_config = '{\"result_type\": \"quantitative\", \"threshold_value\": \"10\"}'
                        WHERE test_type_id = 4");
        $db->rawQuery("INSERT INTO generic_test_methods_map (test_method_id, test_type_id) VALUES (2, 4)");
        $details = $generic->activityDetails(4, $before);
        $changes = self::byField($details['changes']);

        $this->assertSame('Hepatitis B VL', $details['entity']['label']);
        $threshold = $changes['test_results_config.threshold_value'];
        $this->assertSame('Result settings › Threshold value', $threshold['label']);
        $this->assertSame(['ELISA'], $changes['test_methods']['added']);
        $this->assertContains('test_results_config', $details['importantFields']);
    }
}
