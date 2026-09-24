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

use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;

/**
 * The country request forms' fields on api/v1.1/{vl,eid}/save-request.php.
 *
 * The web forms saved dozens of fields the API had no key for (Cameroon's CV
 * number and PCR history, PNG's test blocks, Burkina Faso's CD4...), so an EMR or
 * the app could not send them. They are written only when a record carries the
 * key: clients already in the field re-post whole records without them, and a
 * `?? null` would blank what the web form saved on every one of those posts.
 *
 * And only for a client that declares the request-form-fields capability. App
 * builds already in the field post many of these keys, some always empty, and
 * the server dropped them; an old build's re-post must go on doing what it did.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can drive
 * it once: every test runs in its own process.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ApiRequestFormFieldsTest extends TestCase
{
    private const DATABASE = 'intelis_api_request_form_fields_test';
    private const TOKEN = 'emr-token';

    protected function setUp(): void
    {
        if (!getenv('INTELIS_TEST_DB_HOST') || !getenv('INTELIS_TEST_DB_USER')) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        // Global config is file-cached across processes: a vl_form another run left
        // there would stand in for the one seeded here.
        self::clearFileCache();
        require_once ROOT_PATH . '/app/system/version.php';
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['REQUEST_URI'] ??= '/api/v1.1/vl/save-request.php';

        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            's_vlsm_instance', 'system_config', 'global_config', 'roles', 'user_details', 'facility_details',
            'geographical_divisions', 'r_sample_status', 'user_facility_map', 'r_vl_art_regimen',
            'batch_details', 'r_vl_test_reasons', 'r_vl_sample_type', 'r_vl_sample_rejection_reasons',
            'r_eid_test_reasons', 'r_eid_sample_type', 'r_eid_sample_rejection_reasons',
            'r_funding_sources', 'r_implementation_partners',
            'form_vl', 'form_eid', 'activity_log', 'audit_log', 'test_result_attempts', 'track_api_requests',
        ]);
        // sql/init.sql predates the change; 5.7.81 makes it text.
        $db->rawQuery('ALTER TABLE `form_eid` MODIFY `infant_phone` VARCHAR(32) NULL DEFAULT NULL');
        $db->insert('s_vlsm_instance', ['vlsm_instance_id' => 'test-instance']);
        foreach (range(1, 12) as $statusId) {
            $db->insert('r_sample_status', [
                'status_id' => $statusId, 'status_name' => "Status $statusId", 'status' => 'active',
            ]);
        }
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
            'user_id' => 'emr-user', 'user_name' => 'EMR', 'login_id' => 'emr',
            'api_token' => self::TOKEN, 'status' => 'active', 'role_id' => 2,
        ]);
        $db->insert('r_vl_art_regimen', [
            'art_code' => 'TDF-3TC-DTG', 'parent_art' => 0, 'art_status' => 'active',
        ]);
        // Saved through the web form, with fields of the country forms filled in.
        $db->insert('form_vl', [
            'vl_sample_id' => 40, 'unique_id' => 'vl-u-1', 'app_sample_code' => 'VL-APP-1',
            'lab_id' => 5, 'facility_id' => 1, 'sample_collection_date' => '2026-09-20 09:00:00',
            'result_status' => RECEIVED_AT_CLINIC, 'locked' => 'no', 'request_created_by' => 'emr-user',
            'cv_number' => 'CV-WEB', 'no_of_pregnancy_weeks' => 20, 'last_cd4_date' => '2026-06-01',
            'instrument_id' => 'web-instrument', 'recommended_corrective_action' => 2,
        ]);
        $db->insert('form_eid', [
            'eid_id' => 50, 'unique_id' => 'eid-u-1', 'app_sample_code' => 'EID-APP-1',
            'lab_id' => 5, 'facility_id' => 1, 'sample_collection_date' => '2026-09-20 09:00:00',
            'result_status' => RECEIVED_AT_CLINIC, 'locked' => 'no', 'request_created_by' => 'emr-user',
            'clinician_name' => 'Dr Web', 'pcr_1_test_date' => '2026-05-01', 'no_of_exposed_children' => 2,
            'test_1_result' => 'Detected', 'instrument_id' => 'lab-instrument',
        ]);
    }

    protected function tearDown(): void
    {
        if (getenv('INTELIS_TEST_DB_HOST') && getenv('INTELIS_TEST_DB_USER')) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $sample */
    private function post(string $module, array $sample, bool $declares = true): array
    {
        $payload = ['appVersion' => '1.0', 'data' => [$sample]];
        if ($declares) {
            $payload['capabilities'] = ['supports' => ['request-form-fields']];
        }
        return $this->call("/api/v1.1/$module/save-request.php", $payload);
    }

    /** @param array<string, mixed> $payload */
    private function call(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . self::TOKEN)
            ->withBody((new StreamFactory())->createStream($body));
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $response = json_decode((string) $handler->handle($request)->getBody(), true);
        self::assertSame('success', $response['status'] ?? null, json_encode($response));
        return $response;
    }

    /** @return array<string, mixed> */
    private function row(string $sql): array
    {
        return LegacyAppHarness::db()->rawQueryOne($sql) ?: [];
    }

    /** @return array<string, mixed> */
    private static function vlSample(array $extra = []): array
    {
        return [
            'appSampleCode' => 'VL-APP-1', 'labId' => '5', 'facilityId' => '1',
            'sampleCollectionDate' => '2026-09-20 09:00:00', 'patientGender' => 'female',
        ] + $extra;
    }

    /** @return array<string, mixed> */
    private static function eidSample(array $extra = []): array
    {
        return [
            'appSampleCode' => 'EID-APP-1', 'labId' => '5', 'facilityId' => '1',
            'sampleCollectionDate' => '2026-09-20 09:00:00', 'childGender' => 'male',
        ] + $extra;
    }

    #[RunInSeparateProcess]
    public function testAVlPostWithoutTheFieldsKeepsWhatTheWebFormSaved(): void
    {
        $this->post('vl', self::vlSample());

        $row = $this->row('SELECT * FROM form_vl WHERE vl_sample_id = 40');
        self::assertSame('CV-WEB', $row['cv_number']);
        self::assertSame(20, (int) $row['no_of_pregnancy_weeks']);
        self::assertSame('2026-06-01', $row['last_cd4_date']);
        self::assertSame('web-instrument', $row['instrument_id']);
    }

    /** An app build from before the server took these fields posts them anyway. */
    #[RunInSeparateProcess]
    public function testAClientThatDoesNotDeclareTheCapabilityIsAnsweredAsBefore(): void
    {
        $response = $this->post('vl', self::vlSample([
            'cvNumber' => '', 'noOfPregnancyWeeks' => '', 'cd4Result' => '350', 'instrumentId' => '',
        ]), declares: false);

        $row = $this->row('SELECT * FROM form_vl WHERE vl_sample_id = 40');
        self::assertSame('CV-WEB', $row['cv_number']);
        self::assertSame(20, (int) $row['no_of_pregnancy_weeks']);
        self::assertNull($row['last_cd4_result']);
        self::assertSame('web-instrument', $row['instrument_id']);
        self::assertArrayNotHasKey('capabilities', $response);
    }

    #[RunInSeparateProcess]
    public function testAnEidClientThatDoesNotDeclareTheCapabilityIsAnsweredAsBefore(): void
    {
        $this->post('eid', self::eidSample(['clinicianName' => '', 'pcr1TestDate' => '']), declares: false);

        $row = $this->row('SELECT * FROM form_eid WHERE eid_id = 50');
        self::assertSame('Dr Web', $row['clinician_name']);
        self::assertSame('2026-05-01', $row['pcr_1_test_date']);
    }

    /**
     * The instrument, corrective action and EID test blocks are the lab's result
     * entry, which SavedResultGuard does not cover: a request post, stale or not,
     * does not reach them even when it declares the capability.
     */
    #[RunInSeparateProcess]
    public function testARequestPostDoesNotReachTheLabsResultEntry(): void
    {
        $this->post('vl', self::vlSample(['instrumentId' => '', 'correctiveAction' => '']));
        $this->post('eid', self::eidSample(['instrumentId' => '', 'test1Result' => '', 'correctiveAction' => '']));

        $vl = $this->row('SELECT * FROM form_vl WHERE vl_sample_id = 40');
        self::assertSame('web-instrument', $vl['instrument_id']);
        self::assertSame(2, (int) $vl['recommended_corrective_action']);
        $eid = $this->row('SELECT * FROM form_eid WHERE eid_id = 50');
        self::assertSame('Detected', $eid['test_1_result']);
        self::assertSame('lab-instrument', $eid['instrument_id']);
    }

    #[RunInSeparateProcess]
    public function testTheResponseConfirmsTheCapability(): void
    {
        $response = $this->post('vl', self::vlSample());

        self::assertSame(['supports' => ['request-form-fields']], $response['capabilities'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testAVlPostSavesTheCountryFormFields(): void
    {
        $this->post('vl', self::vlSample([
            'cvNumber' => 'CV-77',
            'noOfPregnancyWeeks' => '14',
            'cd4Result' => '350',
            'cd4Date' => '2026-08-01',
            'activeTB' => 'no',
            'reasonForVLTestingOther' => 'Clinical suspicion',
            'confirmRecencyTestingLastVLDate' => '2026-07-15',
            'conservationTemperature' => '4',
        ]));

        $row = $this->row('SELECT * FROM form_vl WHERE vl_sample_id = 40');
        // The lab's CV number stands: a post only fills in a missing one.
        self::assertSame('CV-WEB', $row['cv_number']);
        self::assertSame(14, (int) $row['no_of_pregnancy_weeks']);
        self::assertSame('350', $row['last_cd4_result']);
        self::assertSame('2026-08-01', $row['last_cd4_date']);
        self::assertSame('no', $row['patient_has_active_tb']);
        self::assertSame('Clinical suspicion', $row['reason_for_vl_testing_other']);
        self::assertSame('2026-07-15', $row['last_vl_date_recency']);
        self::assertSame(4.0, (float) $row['plasma_conservation_temperature']);
    }

    #[RunInSeparateProcess]
    public function testAVlPostFillsInAMissingCvNumber(): void
    {
        LegacyAppHarness::db()->rawQuery('UPDATE form_vl SET cv_number = NULL WHERE vl_sample_id = 40');

        $this->post('vl', self::vlSample(['cvNumber' => 'CV-77']));

        self::assertSame('CV-77', $this->row('SELECT cv_number FROM form_vl WHERE vl_sample_id = 40')['cv_number']);
    }

    #[RunInSeparateProcess]
    public function testAVlFieldSentEmptyIsCleared(): void
    {
        $this->post('vl', self::vlSample([
            'cvNumber' => '', 'cd4Date' => '', 'noOfPregnancyWeeks' => 'twelve',
        ]));

        $row = $this->row('SELECT * FROM form_vl WHERE vl_sample_id = 40');
        self::assertNull($row['last_cd4_date']);
        // The CV number is the testing lab's code, kept like the lab assigned code.
        self::assertSame('CV-WEB', $row['cv_number']);
        // Neither 0, which is what MySQL makes of 'twelve' under an empty sql_mode,
        // nor cleared: the saved value stands.
        self::assertSame(20, (int) $row['no_of_pregnancy_weeks']);
    }

    #[RunInSeparateProcess]
    public function testAnEidPostWithoutTheFieldsKeepsWhatTheWebFormSaved(): void
    {
        $this->post('eid', self::eidSample());

        $row = $this->row('SELECT * FROM form_eid WHERE eid_id = 50');
        self::assertSame('Dr Web', $row['clinician_name']);
        self::assertSame('2026-05-01', $row['pcr_1_test_date']);
        self::assertSame(2, (int) $row['no_of_exposed_children']);
    }

    #[RunInSeparateProcess]
    public function testAnEidPostSavesTheCountryFormFields(): void
    {
        $this->post('eid', self::eidSample([
            'clinicianName' => 'Dr Mbala',
            'labTestingPoint' => 'other',
            'labTestingPointOther' => 'Ward 4',
            'mothersSurname' => 'Ngono',
            'motherRegimen' => 'tdf-3tc-dtg',
            'modeOfDelivery' => 'vaginal',
            'pcr1TestDate' => '2026-06-10',
            'pcr1TestResult' => 'negative',
            'noOfExposedChildren' => '3',
            'infantOnPMTCTProphylaxis' => 'yes',
            'infantPhone' => '+237 0690 12 34 56',
        ]));

        $row = $this->row('SELECT * FROM form_eid WHERE eid_id = 50');
        self::assertSame('Dr Mbala', $row['clinician_name']);
        self::assertSame('other', $row['lab_testing_point']);
        self::assertSame('Ward 4', $row['lab_testing_point_other']);
        self::assertSame('Ngono', $row['mother_surname']);
        // The stored spelling of a known regimen, as the web form resolves it.
        self::assertSame('TDF-3TC-DTG', $row['mother_regimen']);
        self::assertSame('vaginal', $row['mode_of_delivery']);
        self::assertSame('2026-06-10', $row['pcr_1_test_date']);
        self::assertSame('negative', $row['pcr_1_test_result']);
        self::assertSame(3, (int) $row['no_of_exposed_children']);
        self::assertSame('yes', $row['infant_on_pmtct_prophylaxis']);
        self::assertSame('+237 0690 12 34 56', $row['infant_phone']);
    }

    #[RunInSeparateProcess]
    public function testAnUnknownMotherRegimenIsRegistered(): void
    {
        $this->post('eid', self::eidSample(['motherRegimen' => 'AZT-3TC-LPV/r']));

        $row = $this->row('SELECT mother_regimen FROM form_eid WHERE eid_id = 50');
        self::assertSame('AZT-3TC-LPV/r', $row['mother_regimen']);
        self::assertNotEmpty($this->row("SELECT art_id FROM r_vl_art_regimen WHERE art_code = 'AZT-3TC-LPV/r'"));
    }

    /** South Sudan's form holds the child's whole name in one field; the app posts two. */
    #[RunInSeparateProcess]
    public function testSouthSudanJoinsTheChildsNames(): void
    {
        LegacyAppHarness::db()->insert('global_config', ['name' => 'vl_form', 'value' => '1']);

        $this->post('eid', self::eidSample(['childName' => 'Deng', 'childSurName' => 'Garang']), declares: false);

        $row = $this->row('SELECT child_name, child_surname FROM form_eid WHERE eid_id = 50');
        self::assertSame('Deng Garang', $row['child_name']);
        self::assertNull($row['child_surname']);
    }

    /** Installs upgraded from before 5.2.2 hold child_name as VARCHAR(100). */
    #[RunInSeparateProcess]
    public function testAJoinedNameTooLongForTheColumnStaysInItsParts(): void
    {
        LegacyAppHarness::db()->insert('global_config', ['name' => 'vl_form', 'value' => '1']);
        LegacyAppHarness::db()->rawQuery('ALTER TABLE form_eid MODIFY child_name VARCHAR(20) NULL');

        $this->post('eid', self::eidSample([
            'childName' => 'Achol Nyibol', 'childSurName' => 'Deng Garang Mabior',
        ]), declares: false);

        $row = $this->row('SELECT child_name, child_surname FROM form_eid WHERE eid_id = 50');
        self::assertSame('Achol Nyibol', $row['child_name']);
        self::assertSame('Deng Garang Mabior', $row['child_surname']);
    }

    #[RunInSeparateProcess]
    public function testAnotherFormKeepsTheChildsSurnameApart(): void
    {
        $this->post('eid', self::eidSample(['childName' => 'Deng', 'childSurName' => 'Garang']), declares: false);

        $row = $this->row('SELECT child_name, child_surname FROM form_eid WHERE eid_id = 50');
        self::assertSame('Deng', $row['child_name']);
        self::assertSame('Garang', $row['child_surname']);
    }

    private static function clearFileCache(): void
    {
        $dir = CACHE_PATH . DIRECTORY_SEPARATOR . 'file_cache';
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }
}
