<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;

/**
 * /api/v1.1/init.php end to end: the shape InteLIS Mobile parses on first
 * login. Pins what the app reads at the top level, that the module blocks no
 * longer carry copies of the district and province lists, and that facility
 * rows carry the ten fields the app stores and nothing heavier.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiInitEndpointTest extends TestCase
{
    private const DATABASE = 'intelis_api_init_endpoint_test';

    private const TOKEN = 'init-test-token';

    private const MODULES = ['vl' => true, 'eid' => true, 'covid19' => true, 'tb' => true];

    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config', 'track_api_requests',
        'resources', 'privileges', 'roles', 'roles_privileges_map', 'user_details', 'user_facility_map',
        'geographical_divisions', 'facility_details', 'health_facilities', 'testing_labs', 'instruments',
        'r_countries', 'r_funding_sources', 'r_implementation_partners', 'r_sample_status',
        'r_vl_art_regimen', 'r_vl_results', 'r_vl_sample_type', 'r_vl_test_failure_reasons',
        'r_vl_sample_rejection_reasons', 'r_vl_test_reasons',
        'r_eid_results', 'r_eid_sample_type', 'r_eid_sample_rejection_reasons', 'r_eid_test_reasons',
        'r_covid19_results', 'r_covid19_sample_type', 'r_covid19_test_reasons', 'r_covid19_symptoms',
        'r_covid19_comorbidities', 'r_covid19_sample_rejection_reasons',
        'r_tb_results', 'r_tb_sample_type', 'r_tb_sample_rejection_reasons', 'r_tb_test_reasons',
    ];

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
        require_once ROOT_PATH . '/app/system/version.php';
        // init.php reads the module switches from both the constant and the container.
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['modules' => self::MODULES, 'system' => ['debug_mode' => false]]);
        }

        LegacyAppHarness::boot(self::DATABASE, self::TABLES, ['modules' => self::MODULES]);
        $db = LegacyAppHarness::db();
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'test-instance']);
        $db->insert('system_config', [
            'display_name' => 'Instance type',
            'name' => 'sc_user_type',
            'value' => 'vlsm',
        ]);
        $db->insert('roles', [
            'role_id' => 1,
            'role_name' => 'Lab',
            'role_code' => 'lab',
            'access_type' => 'testing-lab',
        ]);
        $db->insert('user_details', [
            'user_id' => 'user-1',
            'user_name' => 'API User',
            'login_id' => 'api',
            'api_token' => self::TOKEN,
            'status' => 'active',
            'role_id' => 1,
            'app_access' => 'yes',
        ]);
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
        foreach ([[1, 'Clinic One'], [2, 'Clinic Two']] as [$id, $name]) {
            $db->insert('facility_details', [
                'facility_id' => $id,
                'facility_name' => $name,
                'facility_code' => "F$id",
                'facility_type' => 1,
                'facility_state_id' => 1,
                'facility_state' => 'North',
                'facility_district_id' => 10,
                'facility_district' => 'Alpha',
                'facility_attributes' => '{"big":"' . str_repeat('x', 200) . '"}',
                'status' => 'active',
            ]);
            $db->insert('health_facilities', ['facility_id' => $id, 'test_type' => 'vl']);
            $db->insert('user_facility_map', ['user_id' => 'user-1', 'facility_id' => $id]);
        }
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @return array<string, mixed> */
    private function init(): array
    {
        $path = '/api/v1.1/init.php';
        $_SERVER['HTTP_HOST'] = 'tests.local';
        $_SERVER['REQUEST_URI'] = $path;

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $path)
            ->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream('{}'));

        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));

        return json_decode((string) $handler->handle($request)->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    #[RunInSeparateProcess]
    public function testInitServesTheListsOnceAtTheTopLevelWithoutFacilityAttributes(): void
    {
        $payload = $this->init();

        self::assertSame(1, $payload['status'], json_encode($payload));
        $data = $payload['data'];

        foreach (
            ['formId', 'activeModule', 'facilitiesList', 'testingLabsList', 'provinceList', 'districtList',
             'implementingPartnerList', 'fundingSourceList', 'nationalityList', 'labTechniciansList', 'userList',
             'vl', 'eid', 'covid19', 'tb'] as $key
        ) {
            self::assertArrayHasKey($key, $data, $key);
        }

        // The district and province lists live at the top level only.
        self::assertSame(['Alpha'], array_column($data['districtList'], 'show'));
        self::assertSame(['North'], array_column($data['provinceList'], 'show'));
        foreach (['eid', 'covid19'] as $module) {
            self::assertArrayNotHasKey('districtList', $data[$module], $module);
            self::assertArrayNotHasKey('provinceList', $data[$module], $module);
            self::assertArrayHasKey('statusFilterList', $data[$module], $module);
            self::assertArrayHasKey('resultsList', $data[$module], $module);
        }

        // Facility rows: the ten fields the app stores, and no attributes blob.
        self::assertCount(2, $data['facilitiesList']);
        $facility = $data['facilitiesList'][0];
        foreach (
            ['facility_id', 'facility_name', 'facility_code', 'facility_state', 'facility_state_id',
             'facility_district', 'facility_district_id', 'other_id', 'testing_points', 'status'] as $field
        ) {
            self::assertArrayHasKey($field, $facility, $field);
        }
        self::assertArrayNotHasKey('facility_attributes', $facility);
    }
}
