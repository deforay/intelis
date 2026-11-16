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
 * A COVID-19 update through api/v1.1/covid-19/save-request.php that leaves keys out.
 *
 * The endpoint read revisedBy and revisedOn from $_POST, which is empty for a
 * JSON body, so every update blanked them. It also set request_created_by to
 * the posting user on every update, and deleted the saved comorbidities when
 * the record carried none.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class Covid19ApiUpdateKeepsSavedValuesTest extends TestCase
{
    private const DATABASE = 'intelis_c19_api_keeps_saved';
    private const CLINIC_TOKEN = 'clinic-app-token';
    private const OTHER_TOKEN = 'other-app-token';

    private string $database;

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        require_once ROOT_PATH . '/app/system/version.php';
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['REQUEST_URI'] = '/api/v1.1/covid-19/save-request.php';

        $this->database = TestRun::database(self::DATABASE);
        $db = LegacyAppHarness::boot($this->database, [
            's_vlsm_instance', 'system_config', 'global_config', 'roles', 'user_details', 'facility_details',
            'user_facility_map', 'form_covid19', 'covid19_tests', 'covid19_patient_symptoms',
            'covid19_reasons_for_testing', 'covid19_patient_comorbidities', 'activity_log', 'audit_log',
            'test_result_attempts', 'r_sample_status', 'track_api_requests', 'geographical_divisions',
            'batch_details', 'r_funding_sources', 'r_implementation_partners', 'r_countries',
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
            'api_token' => self::CLINIC_TOKEN, 'status' => 'active', 'role_id' => 2,
        ]);
        $db->insert('user_details', [
            'user_id' => 'other-user', 'user_name' => 'Other User', 'login_id' => 'other',
            'api_token' => self::OTHER_TOKEN, 'status' => 'active', 'role_id' => 2,
        ]);
        $db->insert('form_covid19', [
            'covid19_id' => 40, 'unique_id' => 'c19-u-1', 'app_sample_code' => 'APP-1', 'sample_code' => 'C19-1',
            'vlsm_instance_id' => 'test-instance', 'lab_id' => 5, 'facility_id' => 1,
            'sample_collection_date' => '2026-09-20 09:00:00', 'result_status' => RECEIVED_AT_CLINIC,
            'is_sample_rejected' => 'no', 'patient_id' => 'P-1', 'locked' => 'no',
            'request_created_by' => 'clinic-user',
        ]);
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $record */
    private function post(string $token, array $record): void
    {
        $body = json_encode(['appVersion' => '1.5.1', 'data' => [$record + [
            'appSampleCode' => 'APP-1',
            'labId' => '5',
            'facilityId' => '1',
            'sampleCollectionDate' => '2026-09-20 09:00:00',
            'instanceId' => 'test-instance',
            'isSampleRejected' => 'no',
        ]]], JSON_THROW_ON_ERROR);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/v1.1/covid-19/save-request.php')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withBody((new StreamFactory())->createStream($body));
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $handler->handle($request);
    }

    /** @return array{0: array<string, mixed>, 1: list<array<string, mixed>>} */
    private function saved(): array
    {
        $mysqli = new mysqli(
            (string) getenv('INTELIS_TEST_DB_HOST'),
            (string) getenv('INTELIS_TEST_DB_USER'),
            (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            $this->database,
            (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
        );
        $form = $mysqli->query(
            'SELECT revised_by, revised_on, request_created_by, last_modified_by, patient_id
               FROM form_covid19 WHERE covid19_id = 40'
        )->fetch_assoc();
        $comorbidities = $mysqli->query(
            'SELECT comorbidity_id, comorbidity_detected FROM covid19_patient_comorbidities
              WHERE covid19_id = 40 ORDER BY comorbidity_id'
        )->fetch_all(MYSQLI_ASSOC);
        $mysqli->close();
        return [$form, $comorbidities];
    }

    #[RunInSeparateProcess]
    public function testAnUpdateWritesRevisedByOnAndComorbiditiesFromTheRecord(): void
    {
        $this->post(self::CLINIC_TOKEN, [
            'revisedBy' => 'reviser-user',
            'revisedOn' => '2026-09-22 10:00:00',
            'comorbidityId' => [1, 2],
            'comorbidityDetected' => ['yes', 'no'],
        ]);

        [$form, $comorbidities] = $this->saved();
        self::assertSame('reviser-user', $form['revised_by']);
        self::assertSame('2026-09-22 10:00:00', $form['revised_on']);
        self::assertSame([
            ['comorbidity_id' => '1', 'comorbidity_detected' => 'yes'],
            ['comorbidity_id' => '2', 'comorbidity_detected' => 'no'],
        ], $comorbidities);
    }

    #[RunInSeparateProcess]
    public function testAnUpdateWithoutTheKeysKeepsTheSavedValues(): void
    {
        $db = LegacyAppHarness::db();
        $db->where('covid19_id', 40);
        $db->update('form_covid19', ['revised_by' => 'reviser-user', 'revised_on' => '2026-09-22 10:00:00']);
        $db->insert('covid19_patient_comorbidities', [
            'covid19_id' => 40, 'comorbidity_id' => 1, 'comorbidity_detected' => 'yes',
        ]);
        $db->insert('covid19_patient_comorbidities', [
            'covid19_id' => 40, 'comorbidity_id' => 2, 'comorbidity_detected' => 'no',
        ]);

        // Another user corrects the patient id and sends nothing else.
        $this->post(self::OTHER_TOKEN, ['patientId' => 'P-2']);

        [$form, $comorbidities] = $this->saved();
        self::assertSame('P-2', $form['patient_id']);
        self::assertSame('reviser-user', $form['revised_by']);
        self::assertSame('2026-09-22 10:00:00', $form['revised_on']);
        self::assertSame('clinic-user', $form['request_created_by']);
        self::assertSame('other-user', $form['last_modified_by']);
        self::assertSame([
            ['comorbidity_id' => '1', 'comorbidity_detected' => 'yes'],
            ['comorbidity_id' => '2', 'comorbidity_detected' => 'no'],
        ], $comorbidities);
    }
}
