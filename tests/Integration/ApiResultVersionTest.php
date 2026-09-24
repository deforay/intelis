<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\SavedResultGuard;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\LegacyAppHarness;

use const SAMPLE_STATUS\PENDING_APPROVAL;

/**
 * A client posting a result it pulled before the lab revised it
 * (api/v1.1/tb/save-request.php and fetch-results.php).
 *
 * The client pulled "MTB detected", the lab has since revised it to "MTB not
 * detected", and the client posts the sample again carrying what it pulled. A
 * post that carries a result goes through, so the lab's revision was lost. A
 * client that declares result-version sends back the version it pulled with
 * the sample; a post made on an older version keeps the lab's decision and
 * saves the rest.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiResultVersionTest extends TestCase
{
    private const DATABASE = 'intelis_api_result_version_test';
    private const TOKEN = 'clinic-app-token';
    private const PULLED = ['result' => 'MTB detected', 'is_sample_rejected' => 'no'];
    private const DECLARED = ['supports' => [SavedResultGuard::RESULT_VERSION]];

    private string $database;

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        require_once ROOT_PATH . '/app/system/version.php';
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['REQUEST_URI'] ??= '/api/v1.1/tb/save-request.php';

        $this->database = self::DATABASE . '_' . getmypid();
        $db = LegacyAppHarness::boot($this->database, [
            's_vlsm_instance', 'system_config', 'global_config', 'roles', 'user_details', 'facility_details',
            'user_facility_map', 'form_tb', 'tb_tests', 'activity_log', 'audit_log', 'test_result_attempts',
            'r_sample_status', 'track_api_requests', 'geographical_divisions', 'r_tb_sample_type',
            'r_tb_sample_rejection_reasons', 'r_tb_test_reasons', 'batch_details',
            'r_funding_sources', 'r_implementation_partners',
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
        // The lab's revision of what the client pulled, awaiting approval: the
        // endpoint refuses any post on an accepted sample.
        $db->insert('form_tb', [
            'tb_id' => 30, 'unique_id' => 'tb-u-1', 'app_sample_code' => 'APP-1', 'sample_code' => 'TB-1',
            'lab_id' => 5, 'facility_id' => 1, 'sample_collection_date' => '2026-09-20 09:00:00',
            'result' => 'MTB not detected', 'tested_by' => 'lab-user', 'result_status' => PENDING_APPROVAL,
            'is_sample_rejected' => 'no', 'patient_id' => 'P-1', 'locked' => 'no',
            'request_created_by' => 'clinic-user',
        ]);
        $db->insert('tb_tests', [
            'tb_id' => 30, 'lab_id' => 5, 'actual_no' => '1', 'test_result' => 'MTB not detected',
        ]);
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function call(string $path, array $body): array
    {
        $_SERVER['REQUEST_URI'] = $path;
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->withBody((new StreamFactory())->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return json_decode((string) $handler->handle($request)->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * The client's post of the sample: it carries the result it pulled.
     *
     * @param array<string, mixed> $extra
     * @param array<string, mixed>|null $capabilities
     * @return array<string, mixed>
     */
    private function post(array $extra, ?array $capabilities): array
    {
        $body = ['appVersion' => '1.5.1', 'data' => [[
            'appSampleCode' => 'APP-1',
            'labId' => '5',
            'facilityId' => '1',
            'sampleCollectionDate' => '2026-09-20 09:00:00',
            'instanceId' => 'test-instance',
            'patientId' => 'P-2',
            'result' => 'MTB detected',
            'isSampleRejected' => 'no',
            'testResults' => [['actualNo' => '1', 'testResult' => 'MTB detected']],
        ] + $extra]];
        if ($capabilities !== null) {
            $body['capabilities'] = $capabilities;
        }
        return $this->call('/api/v1.1/tb/save-request.php', $body);
    }

    /**
     * The sample as saved, read on a connection of its own, so the endpoint's
     * transaction has to have committed for it to be seen.
     *
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function saved(): array
    {
        $mysqli = new mysqli(
            (string) getenv('INTELIS_TEST_DB_HOST'),
            (string) getenv('INTELIS_TEST_DB_USER'),
            (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            $this->database,
            (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
        );
        $form = $mysqli->query('SELECT * FROM form_tb WHERE tb_id = 30')->fetch_assoc();
        $tests = $mysqli->query('SELECT actual_no, test_result FROM tb_tests WHERE tb_id = 30 ORDER BY tb_test_id')
            ->fetch_all(MYSQLI_ASSOC);
        $mysqli->close();
        return [$form, $tests];
    }

    #[RunInSeparateProcess]
    public function testAPostOnAnOlderResultKeepsTheLabsRevisionAndSavesTheRest(): void
    {
        $response = $this->post(['resultVersion' => SavedResultGuard::resultVersion(self::PULLED)], self::DECLARED);

        [$form, $tests] = $this->saved();
        self::assertSame('MTB not detected', $form['result']);
        self::assertSame('lab-user', $form['tested_by']);
        self::assertSame([['actual_no' => '1', 'test_result' => 'MTB not detected']], $tests);
        // The request fields the client owns are still saved.
        self::assertSame('P-2', $form['patient_id']);

        $sample = $response['data'][0];
        self::assertSame('success', $sample['status']);
        self::assertTrue($sample['resultKept']);
        self::assertSame(SavedResultGuard::currentVersion(LegacyAppHarness::db(), 'tb', 30), $sample['resultVersion']);
        self::assertSame(self::DECLARED, $response['capabilities']);
    }

    #[RunInSeparateProcess]
    public function testAPostWithoutAVersionKeepsTheLabsDecision(): void
    {
        $response = $this->post([], self::DECLARED);

        [$form, $tests] = $this->saved();
        self::assertSame('MTB not detected', $form['result']);
        self::assertSame([['actual_no' => '1', 'test_result' => 'MTB not detected']], $tests);
        self::assertTrue($response['data'][0]['resultKept']);
    }

    #[RunInSeparateProcess]
    public function testAPostOnTheCurrentResultChangesIt(): void
    {
        $current = SavedResultGuard::currentVersion(LegacyAppHarness::db(), 'tb', 30);

        $response = $this->post(['resultVersion' => $current], self::DECLARED);

        [$form, $tests] = $this->saved();
        self::assertSame('MTB detected', $form['result']);
        self::assertContains(['actual_no' => '1', 'test_result' => 'MTB detected'], $tests);

        $sample = $response['data'][0];
        self::assertArrayNotHasKey('resultKept', $sample);
        self::assertSame(SavedResultGuard::currentVersion(LegacyAppHarness::db(), 'tb', 30), $sample['resultVersion']);
        self::assertNotSame($current, $sample['resultVersion']);
    }

    #[RunInSeparateProcess]
    public function testAClientThatDeclaresNothingIsAnsweredAsBefore(): void
    {
        $response = $this->post(['resultVersion' => SavedResultGuard::resultVersion(self::PULLED)], null);

        [$form] = $this->saved();
        self::assertSame('MTB detected', $form['result']);
        self::assertArrayNotHasKey('capabilities', $response);
        self::assertArrayNotHasKey('resultVersion', $response['data'][0]);
        self::assertArrayNotHasKey('resultKept', $response['data'][0]);
    }

    #[RunInSeparateProcess]
    public function testFetchResultsHandsOutTheVersionOfEachSample(): void
    {
        $response = $this->call('/api/v1.1/tb/fetch-results.php', ['uniqueId' => ['tb-u-1'], 'markAsSent' => false]);

        self::assertCount(1, $response['data']);
        self::assertSame(
            SavedResultGuard::currentVersion(LegacyAppHarness::db(), 'tb', 30),
            $response['data'][0]['resultVersion']
        );
    }

    #[RunInSeparateProcess]
    public function testAFetchThatMarksResultsSentLeavesTheVersionItHandedOutCurrent(): void
    {
        $response = $this->call('/api/v1.1/tb/fetch-results.php', ['uniqueId' => ['tb-u-1'], 'markAsSent' => true]);

        [$form] = $this->saved();
        self::assertSame('sent', $form['result_sent_to_source']);
        self::assertSame(
            SavedResultGuard::currentVersion(LegacyAppHarness::db(), 'tb', 30),
            $response['data'][0]['resultVersion']
        );
    }

    #[RunInSeparateProcess]
    public function testAPostMadeBeforeTheLabCorrectedATestKeepsTheCorrection(): void
    {
        // The client pulls while the lab's test reads "MTB detected"; the lab then
        // corrects the test alone, leaving the sample's result as it was.
        $db = LegacyAppHarness::db();
        $db->rawQuery("UPDATE tb_tests SET test_result = 'MTB detected' WHERE tb_id = 30");
        $pulled = $this->call('/api/v1.1/tb/fetch-results.php', ['uniqueId' => ['tb-u-1'], 'markAsSent' => false])
            ['data'][0]['resultVersion'];
        $db->rawQuery("UPDATE tb_tests SET test_result = 'MTB not detected' WHERE tb_id = 30");

        $response = $this->post([
            'result' => 'MTB not detected',
            'resultVersion' => $pulled,
            'testResults' => [['actualNo' => '2', 'testResult' => 'MTB detected']],
        ], self::DECLARED);

        [$form, $tests] = $this->saved();
        self::assertSame([['actual_no' => '1', 'test_result' => 'MTB not detected']], $tests);
        self::assertSame('P-2', $form['patient_id']);
        self::assertTrue($response['data'][0]['resultKept']);
    }
}
