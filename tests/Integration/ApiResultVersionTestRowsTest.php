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
 * A client re-posting a sample on the current version, with the result the lab
 * saved (api/v1.1/covid-19 and generic-tests/save-request.php).
 *
 * The client re-posts the whole sample to correct a request detail, the tests
 * it pulled included, in its own copy: without the tester or the instrument.
 * COVID-19 replaced the lab's tests with that copy, so the lab lost both;
 * Custom Tests added the copy as another test. Either way the version the
 * client held went stale by its own post.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiResultVersionTestRowsTest extends TestCase
{
    private const DATABASE = 'intelis_api_result_version_rows_test';
    private const TOKEN = 'clinic-app-token';
    private const DECLARED = ['supports' => [SavedResultGuard::RESULT_VERSION]];

    private string $database;

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        require_once ROOT_PATH . '/app/system/version.php';
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['REQUEST_URI'] ??= '/api/v1.1/covid-19/save-request.php';

        $this->database = self::DATABASE . '_' . getmypid();
        $db = LegacyAppHarness::boot($this->database, [
            's_vlsm_instance', 'system_config', 'global_config', 'roles', 'user_details', 'facility_details',
            'user_facility_map', 'form_covid19', 'covid19_tests', 'covid19_patient_symptoms',
            'covid19_reasons_for_testing', 'covid19_patient_comorbidities', 'activity_log', 'audit_log',
            'test_result_attempts', 'r_sample_status', 'track_api_requests', 'geographical_divisions',
            'batch_details', 'r_funding_sources', 'r_implementation_partners', 'r_countries',
            'form_generic', 'generic_test_results',
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
        $db->insert('form_covid19', [
            'covid19_id' => 40, 'unique_id' => 'c19-u-1', 'app_sample_code' => 'APP-1', 'sample_code' => 'C19-1',
            'lab_id' => 5, 'facility_id' => 1, 'sample_collection_date' => '2026-09-20 09:00:00',
            'result' => 'negative', 'tested_by' => 'lab-user', 'sample_tested_datetime' => '2026-09-21 11:00:00',
            'result_status' => PENDING_APPROVAL, 'is_sample_rejected' => 'no', 'patient_id' => 'P-1',
            'locked' => 'no', 'request_created_by' => 'clinic-user',
        ]);
        $db->insert('covid19_tests', [
            'covid19_id' => 40, 'facility_id' => 5, 'test_name' => 'PCR', 'tested_by' => 'lab-user',
            'sample_tested_datetime' => '2026-09-21 11:00:00', 'testing_platform' => 'abbott',
            'instrument_id' => 'instrument-1', 'result' => 'negative',
        ]);
        $db->insert('form_generic', [
            'sample_id' => 50, 'unique_id' => 'gen-u-1', 'app_sample_code' => 'APP-2', 'sample_code' => 'GEN-1',
            'lab_id' => 5, 'facility_id' => 1, 'sample_collection_date' => '2026-09-20 09:00:00',
            'test_type' => 3, 'result' => 'reactive', 'tested_by' => 'lab-user',
            'sample_tested_datetime' => '2026-09-21 11:00:00', 'result_status' => PENDING_APPROVAL,
            'is_sample_rejected' => 'no', 'patient_id' => 'P-1', 'locked' => 'no',
            'request_created_by' => 'clinic-user',
        ]);
        $db->insert('generic_test_results', [
            'generic_id' => 50, 'facility_id' => 5, 'test_name' => 'ELISA', 'tested_by' => 'lab-user',
            'sample_tested_datetime' => '2026-09-21 00:00:00', 'testing_platform' => 'abbott',
            'result' => 'reactive', 'final_result' => 'reactive',
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
     * The sample as saved, read on a connection of its own.
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
        $form = $mysqli->query('SELECT * FROM form_covid19 WHERE covid19_id = 40')->fetch_assoc();
        $tests = $mysqli->query(
            'SELECT test_name, tested_by, instrument_id, result FROM covid19_tests WHERE covid19_id = 40'
        )->fetch_all(MYSQLI_ASSOC);
        $mysqli->close();
        return [$form, $tests];
    }

    #[RunInSeparateProcess]
    public function testAPostOfTheSavedResultKeepsTheLabsTestsAndItsVersion(): void
    {
        $current = SavedResultGuard::currentVersion(LegacyAppHarness::db(), 'covid19', 40);

        $response = $this->call('/api/v1.1/covid-19/save-request.php', [
            'appVersion' => '1.5.1',
            'capabilities' => self::DECLARED,
            'data' => [[
                'appSampleCode' => 'APP-1',
                'labId' => '5',
                'facilityId' => '1',
                'sampleCollectionDate' => '2026-09-20 09:00:00',
                'instanceId' => 'test-instance',
                'patientId' => 'P-2',
                'result' => 'negative',
                'isSampleRejected' => 'no',
                'resultVersion' => $current,
                'c19Tests' => [[
                    'testName' => 'PCR', 'testDate' => '2026-09-21 11:00:00',
                    'testingPlatform' => 'abbott', 'testResult' => 'negative',
                ]],
            ]],
        ]);

        [$form, $tests] = $this->saved();
        self::assertSame([[
            'test_name' => 'PCR', 'tested_by' => 'lab-user', 'instrument_id' => 'instrument-1', 'result' => 'negative',
        ]], $tests);
        self::assertSame('2026-09-21 11:00:00', $form['sample_tested_datetime']);
        self::assertSame('P-2', $form['patient_id']);
        self::assertArrayNotHasKey('resultKept', $response['data'][0]);
        self::assertSame($current, $response['data'][0]['resultVersion']);
    }

    #[RunInSeparateProcess]
    public function testACustomTestPostOfTheSavedResultAddsNoTestAndKeepsItsVersion(): void
    {
        $current = SavedResultGuard::currentVersion(LegacyAppHarness::db(), 'generic-tests', 50);

        // The client's copy of the test leaves out the platform the lab saved.
        $response = $this->call('/api/v1.1/generic-tests/save-request.php', [
            'appVersion' => '1.5.1',
            'capabilities' => self::DECLARED,
            'data' => [[
                'appSampleCode' => 'APP-2',
                'labId' => '5',
                'facilityId' => '1',
                'sampleCollectionDate' => '2026-09-20 09:00:00',
                'instanceId' => 'test-instance',
                'testType' => 3,
                'testTypeForm' => [],
                'patientId' => 'P-2',
                'isSampleRejected' => 'no',
                'resultVersion' => $current,
                'testName' => [['ELISA']],
                'testDate' => [['2026-09-21']],
                'testResult' => [['reactive']],
                'finalResult' => ['reactive'],
            ]],
        ]);

        $mysqli = new mysqli(
            (string) getenv('INTELIS_TEST_DB_HOST'),
            (string) getenv('INTELIS_TEST_DB_USER'),
            (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            $this->database,
            (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
        );
        $form = $mysqli->query('SELECT * FROM form_generic WHERE sample_id = 50')->fetch_assoc();
        $tests = $mysqli->query('SELECT test_name, tested_by, result FROM generic_test_results WHERE generic_id = 50')
            ->fetch_all(MYSQLI_ASSOC);
        $mysqli->close();

        self::assertSame([['test_name' => 'ELISA', 'tested_by' => 'lab-user', 'result' => 'reactive']], $tests);
        self::assertSame('lab-user', $form['tested_by']);
        self::assertSame('P-2', $form['patient_id']);
        self::assertArrayNotHasKey('resultKept', $response['data'][0]);
        self::assertSame($current, $response['data'][0]['resultVersion']);
    }
}
