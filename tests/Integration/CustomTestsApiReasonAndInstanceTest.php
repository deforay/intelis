<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;

use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;

/**
 * A Custom Tests request saved through api/v1.1/generic-tests/save-request.php.
 *
 * The endpoint saved reasonForTest through json_encode(), so a reason id 4 was
 * stored as "\"4\"" where the web form stores 4, and reports could not join the
 * sample to its reason. It also saved the client's instanceId as the sample's
 * instance, where every other module saves the server's own.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class CustomTestsApiReasonAndInstanceTest extends TestCase
{
    private const DATABASE = 'intelis_custom_tests_api_reason_instance_test';
    private const TOKEN = 'clinic-app-token';

    private string $database;

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        require_once ROOT_PATH . '/app/system/version.php';
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['REQUEST_URI'] = '/api/v1.1/generic-tests/save-request.php';

        $this->database = TestRun::database(self::DATABASE);
        $db = LegacyAppHarness::boot($this->database, [
            's_vlsm_instance', 'system_config', 'global_config', 'roles', 'user_details', 'facility_details',
            'user_facility_map', 'form_generic', 'generic_test_results', 'activity_log', 'audit_log',
            'test_result_attempts', 'r_sample_status', 'track_api_requests', 'geographical_divisions',
            'r_funding_sources', 'r_implementation_partners',
        ]);
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'server-instance']);
        $db->insert('system_config', [
            'display_name' => 'Instance type', 'name' => 'sc_user_type', 'value' => 'remoteuser',
        ]);
        $db->insert('roles', [
            'role_id' => 2, 'role_name' => 'Clinic', 'role_code' => 'clinic', 'access_type' => 'collection-site',
        ]);
        $db->insert('facility_details', [
            'facility_id' => 5, 'facility_name' => 'Lab Five', 'facility_type' => 2, 'status' => 'active',
        ]);
        $db->insert('facility_details', [
            'facility_id' => 1, 'facility_name' => 'Clinic One', 'facility_type' => 1, 'status' => 'active',
        ]);
        $db->insert('user_details', [
            'user_id' => 'clinic-user', 'user_name' => 'Clinic User', 'login_id' => 'clinic',
            'api_token' => self::TOKEN, 'status' => 'active', 'role_id' => 2,
        ]);
        $db->insert('form_generic', [
            'sample_id' => 60, 'unique_id' => 'gen-u-1', 'app_sample_code' => 'APP-1', 'sample_code' => 'GEN-1',
            'vlsm_instance_id' => 'server-instance', 'lab_id' => 5, 'facility_id' => 1,
            'sample_collection_date' => '2026-09-20 09:00:00', 'test_type' => 3,
            'result_status' => RECEIVED_AT_CLINIC, 'locked' => 'no', 'request_created_by' => 'clinic-user',
        ]);
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testAPostedReasonIdAndTheServersInstanceAreSaved(): void
    {
        $body = json_encode(['appVersion' => '1.5.1', 'data' => [[
            'appSampleCode' => 'APP-1',
            'labId' => '5',
            'facilityId' => '1',
            'sampleCollectionDate' => '2026-09-20 09:00:00',
            'instanceId' => 'client-instance',
            'testType' => 3,
            'testTypeForm' => [],
            'reasonForTest' => '4',
        ]]], JSON_THROW_ON_ERROR);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1.1/generic-tests/save-request.php')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->withBody((new StreamFactory())->createStream($body));
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $handler->handle($request);

        $mysqli = new mysqli(
            (string) getenv('INTELIS_TEST_DB_HOST'),
            (string) getenv('INTELIS_TEST_DB_USER'),
            (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            $this->database,
            (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
        );
        $form = $mysqli->query('SELECT reason_for_testing, vlsm_instance_id FROM form_generic WHERE sample_id = 60')
            ->fetch_assoc();
        $mysqli->close();

        self::assertSame('4', $form['reason_for_testing']);
        self::assertSame('server-instance', $form['vlsm_instance_id']);
    }
}
