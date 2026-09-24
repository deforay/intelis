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

use const SAMPLE_STATUS\PENDING_APPROVAL;

/**
 * A client re-posting a sample the lab has resulted (api/v1.1/tb/save-request.php).
 *
 * Clients re-post their whole dataset, holding only what they last pulled. The
 * endpoint wrote it all, so a re-post made before the result reached the client
 * blanked the lab's result and moved the sample back to received.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiRepostKeepsLabResultTest extends TestCase
{
    private const DATABASE = 'intelis_api_repost_keeps_lab_result_test';
    private const TOKEN = 'clinic-app-token';

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        require_once ROOT_PATH . '/app/system/version.php';
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['REQUEST_URI'] ??= '/api/v1.1/tb/save-request.php';

        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            's_vlsm_instance', 'system_config', 'global_config', 'roles', 'user_details', 'facility_details',
            'user_facility_map', 'form_tb', 'tb_tests', 'activity_log', 'audit_log', 'test_result_attempts',
            'r_sample_status', 'track_api_requests',
        ]);
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'test-instance']);
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
        $db->insert('form_tb', [
            'tb_id' => 30, 'unique_id' => 'tb-u-1', 'app_sample_code' => 'APP-1', 'sample_code' => 'TB-1',
            'lab_id' => 5, 'facility_id' => 1, 'sample_collection_date' => '2026-09-20 09:00:00',
            'result' => 'MTB detected', 'tested_by' => 'lab-user', 'result_status' => PENDING_APPROVAL,
            'is_sample_rejected' => 'no', 'patient_id' => 'P-1', 'locked' => 'no',
        ]);
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $sample */
    private function post(array $sample): string
    {
        $body = json_encode(['appVersion' => '1.0', 'data' => [$sample]], JSON_THROW_ON_ERROR);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1.1/tb/save-request.php')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->withBody((new StreamFactory())->createStream($body));
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return (string) $handler->handle($request)->getBody();
    }

    #[RunInSeparateProcess]
    public function testARepostBeforeTheResultReachedTheClientKeepsIt(): void
    {
        $this->post([
            'appSampleCode' => 'APP-1',
            'labId' => '5',
            'facilityId' => '1',
            'sampleCollectionDate' => '2026-09-20 09:00:00',
            'instanceId' => 'test-instance',
            'patientId' => 'P-2',
            'result' => '',
            'testedBy' => '',
            'isSampleRejected' => '',
        ]);

        $row = LegacyAppHarness::db()->rawQueryOne('SELECT * FROM form_tb WHERE tb_id = 30');
        self::assertSame('MTB detected', $row['result']);
        self::assertSame('lab-user', $row['tested_by']);
        self::assertSame(PENDING_APPROVAL, (int) $row['result_status']);
        // The request fields the client owns are still saved.
        self::assertSame('P-2', $row['patient_id']);
    }
}
