<?php

declare(strict_types=1);

namespace Tests\Support;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DownloadTokenUtility;
use App\Utilities\FileCacheUtility;
use App\Utilities\MemoUtility;
use App\Utilities\MiscUtility;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

use const COUNTRY\BURKINA_FASO;
use const COUNTRY\CAMEROON;
use const COUNTRY\DRC;
use const COUNTRY\PNG;
use const COUNTRY\RWANDA;
use const COUNTRY\SIERRA_LEONE;
use const COUNTRY\SOUTH_SUDAN;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\REJECTED;

/**
 * Each PDF the app prints, rendered from fixed data through its real page and
 * compared with a snapshot of what a reader sees: pages, words and their
 * positions, and images. A refactor of the PDF code, or a change of PDF
 * engine, must leave every snapshot unchanged unless the change is intended.
 *
 * See Tests\Support\PdfFingerprint for what is compared and how to update a
 * snapshot.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can drive
 * it once: every test that drives a page runs in its own process.
 *
 * Each case takes about a second, so the cases are dealt out to several test
 * classes in tests/Integration, one per kind of PDF. Parallel workers run a
 * file at a time, and one class holding every case was the slowest file in the
 * suite. A subclass lists its cases in pdfProvider(); the names are the
 * snapshot names, and a name no subclass lists is never checked.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them, and
 * without poppler's pdfinfo, pdftotext and pdfimages.
 */
abstract class PdfOutputTestCase extends TestCase
{
    private const DATABASE = 'intelis_pdf_output_test';

    private const LAB = 5;
    private const CLINIC = 11;
    private const BATCH_ID = 3;
    private const MANIFEST_ID = 4;

    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config', 'activity_log',
        'roles', 'user_details', 'facility_details', 'geographical_divisions', 'instruments',
        'batch_details', 'r_sample_status', 'r_funding_sources', 'r_implementation_partners',
        'form_vl', 'r_vl_sample_rejection_reasons', 'r_vl_sample_type', 'r_vl_test_reasons',
        'specimen_manifests', 'r_recommended_corrective_actions',
        'form_eid', 'r_eid_sample_rejection_reasons', 'r_eid_sample_type', 'r_eid_results',
        'form_tb', 'tb_tests', 'facility_type', 'lab_report_signatories', 'r_tb_results',
        'r_tb_sample_rejection_reasons', 'r_tb_sample_type', 'r_tb_test_reasons',
        'form_cd4', 'r_cd4_sample_rejection_reasons', 'r_cd4_sample_types', 'r_cd4_test_reasons',
        'form_hepatitis', 'r_countries', 'r_hepatitis_test_reasons', 'r_hepatitis_sample_rejection_reasons',
        'r_hepatitis_sample_type',
        'r_test_types', 'form_generic', 'generic_test_results', 'r_generic_test_reasons', 'r_generic_sample_types',
        'r_generic_sample_rejection_reasons', 'r_generic_test_methods', 'r_generic_test_result_units',
        'generic_test_result_units_map',
        'form_covid19', 'covid19_tests', 'r_covid19_results', 'r_covid19_test_reasons',
        'r_covid19_sample_rejection_reasons', 'r_covid19_sample_type',
    ];

    /** The one Custom Test type: a malaria test with a qualitative result. */
    private const CUSTOM_TEST_RESULTS_CONFIG = [
        'result_type' => ['1' => 'qualitative'],
        'sub_test_name' => ['1' => 'Malaria'],
        'methods' => ['1' => ['RDT']],
        'qualitative' => [
            'expectedResult' => ['1' => ['1' => 'Positive', '2' => 'Negative']],
            'resultCode' => ['1' => ['1' => 'POS', '2' => 'NEG']],
            'sortOrder' => ['1' => ['1' => '1', '2' => '2']],
        ],
    ];

    /** The key a lab encrypts patient details with, 32 bytes for libsodium. */
    private const KEY = 'pdf-output-test-key-32-bytes-ok!';

    /**
     * Three TB samples: the result, the Xpert result id in r_tb_results, and the
     * result of the one test row. The third has its patient details encrypted,
     * and every form must print them decrypted.
     */
    private const TB_SAMPLES = [
        ['TB-TEST-0001', 'TBP-0001', 'Amina', 'Kamara', 'positive', '7', 'MTB detected', false],
        ['TB-TEST-0002', 'TBP-0002', 'Jean', 'Mukendi', 'negative', '6', 'MTB not detected', false],
        ['TB-TEST-0003', 'TBP-0003', 'Grace', 'Okafor', 'negative', '6', 'MTB not detected', true],
    ];

    /** Two EID samples: a positive and a negative result. */
    private const EID_SAMPLES = [
        ['EID-TEST-0001', 'INF-0001', 'Baby', 'Kamara', 'male', 'Positive'],
        ['EID-TEST-0002', 'INF-0002', 'Infant', 'Mukendi', 'female', 'Negative'],
    ];

    /**
     * The result form each country prints, by its vl_form setting, per module.
     * A third value is the form a lab picks with its report_format setting.
     */
    private const RESULT_FORMS = [
        'vl-result-south-sudan' => ['vl', SOUTH_SUDAN],
        'vl-result-sierra-leone' => ['vl', SIERRA_LEONE],
        'vl-result-drc' => ['vl', DRC],
        'vl-result-cameroon' => ['vl', CAMEROON],
        'vl-result-png' => ['vl', PNG],
        'vl-result-rwanda' => ['vl', RWANDA],
        'vl-result-burkina-faso' => ['vl', BURKINA_FASO],
        'vl-result-cameroon-cresar' => ['vl', CAMEROON, 'pdf/result-pdf-cameroon-cresar.php'],
        'eid-result-south-sudan' => ['eid', SOUTH_SUDAN],
        'eid-result-sierra-leone' => ['eid', SIERRA_LEONE],
        'eid-result-drc' => ['eid', DRC],
        'eid-result-cameroon' => ['eid', CAMEROON],
        'eid-result-rwanda' => ['eid', RWANDA],
        'eid-result-burkina-faso' => ['eid', BURKINA_FASO],
        'tb-result-south-sudan' => ['tb', SOUTH_SUDAN],
        'tb-result-sierra-leone' => ['tb', SIERRA_LEONE],
        'tb-result-rwanda' => ['tb', RWANDA],
        'tb-result-burkina-faso' => ['tb', BURKINA_FASO],
        'cd4-result-rwanda' => ['cd4', RWANDA],
        'hepatitis-result-rwanda' => ['hepatitis', RWANDA],
        'generic-tests-result' => ['generic-tests', RWANDA],
        'covid-19-result-south-sudan' => ['covid-19', SOUTH_SUDAN],
        'covid-19-result-sierra-leone' => ['covid-19', SIERRA_LEONE],
        'covid-19-result-drc-1' => ['covid-19', DRC],
        'covid-19-result-cameroon' => ['covid-19', CAMEROON],
        'covid-19-result-png' => ['covid-19', PNG],
        'covid-19-result-rwanda' => ['covid-19', RWANDA],
        'covid-19-result-drc' => ['covid-19', DRC, 'pdf/result-pdf-drc.php'],
        'covid-19-result-drc-2' => ['covid-19', DRC, 'pdf/result-pdf-drc-2.php'],
        'covid-19-result-drc-3' => ['covid-19', DRC, 'pdf/result-pdf-drc-3.php'],
        'covid-19-result-drc-4' => ['covid-19', DRC, 'pdf/result-pdf-drc-4.php'],
        'covid-19-result-drc-5' => ['covid-19', DRC, 'pdf/result-pdf-drc-5.php'],
        'covid-19-result-drc-6' => ['covid-19', DRC, 'pdf/result-pdf-drc-6.php'],
        'covid-19-result-drc-7' => ['covid-19', DRC, 'pdf/result-pdf-drc-7.php'],
        'covid-19-result-drc-8' => ['covid-19', DRC, 'pdf/result-pdf-drc-8.php'],
        'covid-19-result-ssudan-default' => ['covid-19', SOUTH_SUDAN, 'pdf/result-pdf-ssudan-default.php'],
    ];

    /**
     * The manifest each module prints: its id, the print script, the sample
     * table and its key, the samples on it, and the text each sample must print.
     * A referral manifest is printed by its code, and lists the samples a lab
     * sent to another lab. Not every form prints patient names, so the
     * encrypted TB sample is found by its decrypted patient ID. A referral
     * manifest names the lab the samples go to.
     */
    private const MANIFESTS = [
        'vl-manifest' => [4, 'specimen-referral-manifest/generateVLManifest.php', null, null, [],
            ['R-VL-TEST-0001', 'R-VL-TEST-0002', 'R-VL-TEST-0003']],
        'eid-manifest' => [5, 'specimen-referral-manifest/generateEIDManifest.php', 'form_eid', 'eid_id', [1, 2],
            ['R-EID-TEST-0001', 'R-EID-TEST-0002']],
        'tb-manifest' => [6, 'specimen-referral-manifest/generateTBManifest.php', 'form_tb', 'tb_id', [1, 2, 3],
            ['R-TB-TEST-0001', 'R-TB-TEST-0002', 'R-TB-TEST-0003', 'TBP-0003']],
        'cd4-manifest' => [7, 'specimen-referral-manifest/generateCD4Manifest.php', 'form_cd4', 'cd4_id', [1, 2],
            ['R-CD4-TEST-0001', 'R-CD4-TEST-0002']],
        'hepatitis-manifest' => [8, 'specimen-referral-manifest/generateHepatitisManifest.php', 'form_hepatitis',
            'hepatitis_id', [1, 2, 3], ['R-HEP-TEST-0001', 'R-HEP-TEST-0002', 'R-HEP-TEST-0003']],
        'generic-tests-manifest' => [9, 'specimen-referral-manifest/generateGenericManifest.php', 'form_generic',
            'sample_id', [1, 2], ['R-GEN-TEST-0001', 'R-GEN-TEST-0002']],
        'covid-19-manifest' => [10, 'specimen-referral-manifest/generateCovid19Manifest.php', 'form_covid19',
            'covid19_id', [1, 2], ['R-COV-TEST-0001', 'R-COV-TEST-0002']],
        'tb-referral-manifest' => [11, 'tb/results/pdf/generate-generic-manifest.php', 'form_tb', 'tb_id', [1, 2, 3],
            ['R-TB-TEST-0001', 'R-TB-TEST-0002', 'R-TB-TEST-0003', 'TBP-0003', 'MTB detected',
                'Reference Lab']],
        'generic-tests-referral-manifest' => [12, 'generic-tests/results/pdf/generate-generic-manifest.php',
            'form_generic', 'sample_id', [1, 2], ['R-GEN-TEST-0001', 'R-GEN-TEST-0002', 'Reference Lab']],
    ];

    /** The lab a referral manifest sends samples to. */
    private const REFERENCE_LAB = 6;

    /** Three VL samples: a numeric result, a target-not-detected result and no result yet. */
    private const SAMPLES = [
        ['VL-TEST-0001', 'PT-0001', 'Amina', 'Kamara', '1250', 'LAB-0001'],
        ['VL-TEST-0002', 'PT-0002', 'Jean', 'Mukendi', 'Target Not Detected', 'LAB-0002'],
        ['VL-TEST-0003', 'PT-0003', 'Grace', 'Okafor', null, null],
    ];

    /**
     * This class's cases, keyed by description, each holding a snapshot name.
     *
     * @return array<string, array{string}>
     */
    abstract public static function pdfProvider(): array;

    #[RunInSeparateProcess]
    #[DataProvider('pdfProvider')]
    public function testPdfMatchesItsSnapshot(string $name): void
    {
        $this->boot();

        $pdf = match ($name) {
            'vl-batch-worksheet' => $this->body($this->get(
                '/batch/generate-batch-pdf.php',
                ['type' => 'vl', 'id' => MiscUtility::sqid(self::BATCH_ID)]
            )),
            'vl-batch-worksheet-compact' => $this->body($this->get(
                '/batch/generate-compact-batch-pdf.php',
                ['type' => 'vl', 'id' => MiscUtility::sqid(self::BATCH_ID)]
            )),
            default => isset(self::MANIFESTS[$name])
                ? $this->manifestPdf($name)
                : $this->resultPdf(...self::RESULT_FORMS[$name]),
        };

        self::assertStringStartsWith('%PDF-', $pdf);
        PdfFingerprint::assertMatchesSnapshot($name, $pdf);
    }

    private function boot(): void
    {
        if (!LegacyAppHarness::configured()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        if (!PdfFingerprint::available()) {
            self::markTestSkipped('Install poppler (pdfinfo, pdftotext, pdfimages) to compare PDFs.');
        }

        // Every print date that goes through Carbon. Raw date() calls are masked.
        Carbon::setTestNow('2026-10-01 12:00:00');

        // A logo and a signature, so the result PDFs print the images a lab sees.
        // The app defines UPLOAD_PATH in its bootstrap; the test bootstrap does not.
        if (!defined('UPLOAD_PATH')) {
            define('UPLOAD_PATH', WEB_ROOT . '/uploads');
        }
        $image = ROOT_PATH . '/public/assets/img/approved.png';
        foreach (['logo/test-logo.png', 'users-signature/test-signature.png'] as $file) {
            MiscUtility::makeDirectory(dirname(UPLOAD_PATH . '/' . $file));
            copy($image, UPLOAD_PATH . '/' . $file);
        }

        // Result PDFs print a QR code that links to the STS, encrypted with tryCrypt
        $db = LegacyAppHarness::boot(
            self::DATABASE . '_' . getmypid(),
            self::TABLES,
            ['remoteURL' => 'https://sts.example.org', 'tryCrypt' => 'pdf-output-test-key']
        );
        // The app's bootstrap defines this from the same config
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ContainerRegistry::get('applicationConfig'));
        }
        LegacyAppHarness::addMigrationColumns('5.7.80', ['specimen_manifests']);
        LegacyAppHarness::seedDefaults('global_config');
        $config = ['logo' => 'test-logo.png', 'header' => 'Test Lab', 'vl_form' => '0', 'key' => self::KEY];
        foreach ($config as $name => $value) {
            $db->rawQuery('UPDATE global_config SET value = ? WHERE name = ?', [$value, $name]);
        }
        $statuses = [ACCEPTED => 'Accepted', RECEIVED_AT_CLINIC => 'Received at Clinic', REJECTED => 'Rejected'];
        foreach ($statuses as $id => $status) {
            $db->insert('r_sample_status', ['status_id' => $id, 'status_name' => $status, 'status' => 'active']);
        }
        $db->insert('facility_details', [
            'facility_id' => self::LAB, 'facility_name' => 'Central Lab', 'facility_type' => 2, 'status' => 'active',
        ]);
        $db->insert('facility_details', [
            'facility_id' => self::CLINIC, 'facility_name' => 'Hillside Clinic', 'facility_type' => 1,
            'facility_district' => 'Riverside District', 'status' => 'active',
        ]);
        // Some forms find the user through their role
        $db->insert('roles', ['role_id' => 1, 'role_name' => 'Admin', 'role_code' => 'AD', 'status' => 'active']);
        $db->insert('user_details', [
            'user_id' => '1', 'user_name' => 'Test User', 'login_id' => 'test', 'status' => 'active', 'role_id' => 1,
            'user_signature' => 'test-signature.png',
        ]);
        $db->insert('batch_details', [
            'batch_id' => self::BATCH_ID, 'machine' => '1', 'batch_code' => 'BATCH-TEST-0003',
            'test_type' => 'vl', 'position_type' => 'numeric', 'created_by' => '1',
            'label_order' => json_encode(['s_1', 's_2', 's_3', 'no_of_in_house_controls_1']),
            'request_created_datetime' => '2026-09-30 09:00:00',
        ]);
        $db->insert('specimen_manifests', [
            'manifest_id' => self::MANIFEST_ID, 'manifest_code' => 'MANIFEST-TEST-0004',
            'manifest_status' => 'pending', 'manifest_type' => 'collection', 'module' => 'vl',
            'lab_id' => self::LAB, 'added_by' => '1',
        ]);
        foreach (self::SAMPLES as $index => [$code, $patient, $first, $last, $result, $labCode]) {
            $db->insert('form_vl', [
                'vl_sample_id' => $index + 1, 'unique_id' => 'pdf-output-' . ($index + 1),
                'sample_code' => $code, 'remote_sample_code' => 'R-' . $code, 'lab_assigned_code' => $labCode,
                'patient_art_no' => $patient, 'patient_first_name' => $first, 'patient_last_name' => $last,
                'patient_gender' => $index % 2 === 0 ? 'female' : 'male', 'patient_dob' => '1990-05-1' . $index,
                'facility_id' => self::CLINIC, 'lab_id' => self::LAB, 'result' => $result,
                'result_status' => $result === null ? RECEIVED_AT_CLINIC : ACCEPTED,
                'sample_batch_id' => self::BATCH_ID,
                'sample_package_id' => self::MANIFEST_ID, 'sample_package_code' => 'MANIFEST-TEST-0004',
                'sample_collection_date' => '2026-09-2' . $index . ' 10:00:00',
                'sample_tested_datetime' => $result === null ? null : '2026-09-30 11:00:00',
                'sample_received_at_lab_datetime' => '2026-09-29 08:00:00',
                'tested_by' => $result === null ? null : '1',
                'result_reviewed_by' => $result === null ? null : '1',
                'result_reviewed_datetime' => $result === null ? null : '2026-09-30 14:00:00',
                'result_approved_by' => $result === null ? null : '1',
                'result_approved_datetime' => $result === null ? null : '2026-09-30 15:00:00',
            ]);
        }

        // A result of 0 with no stored log value. log10(0) is -INF, which the forms
        // must not print. Not in the batch or the manifest.
        $db->insert('form_vl', [
            'vl_sample_id' => 4, 'unique_id' => 'pdf-output-4', 'sample_code' => 'VL-TEST-0004',
            'remote_sample_code' => 'R-VL-TEST-0004', 'patient_art_no' => 'PT-0004',
            'patient_first_name' => 'Esther', 'patient_last_name' => 'Banda', 'patient_gender' => 'female',
            'patient_dob' => '1985-03-03', 'facility_id' => self::CLINIC, 'lab_id' => self::LAB,
            'result' => '0', 'result_value_log' => null, 'result_status' => ACCEPTED,
            'sample_collection_date' => '2026-09-23 10:00:00',
            'sample_received_at_lab_datetime' => '2026-09-29 08:00:00',
            'sample_tested_datetime' => '2026-09-30 11:00:00', 'tested_by' => '1',
            'result_reviewed_by' => '1', 'result_reviewed_datetime' => '2026-09-30 14:00:00',
            'result_approved_by' => '1', 'result_approved_datetime' => '2026-09-30 15:00:00',
        ]);

        // A rejected sample, with no result. Every form prints it, and its
        // reason. The "<" must print as text, not open a tag TCPDF drops.
        $db->insert('r_vl_sample_rejection_reasons', [
            'rejection_reason_id' => 1, 'rejection_reason_name' => 'Volume <1ml',
            'rejection_type' => 'general', 'rejection_reason_status' => 'active', 'rejection_reason_code' => 'HAEM',
        ]);
        $db->insert('form_vl', [
            'vl_sample_id' => 5, 'unique_id' => 'pdf-output-5', 'sample_code' => 'VL-TEST-0005',
            'remote_sample_code' => 'R-VL-TEST-0005', 'patient_art_no' => 'PT-0005',
            'patient_first_name' => 'Joseph', 'patient_last_name' => 'Phiri', 'patient_gender' => 'male',
            'patient_dob' => '1979-07-07', 'facility_id' => self::CLINIC, 'lab_id' => self::LAB,
            'result' => null, 'result_status' => REJECTED, 'is_sample_rejected' => 'yes',
            'reason_for_sample_rejection' => 1, 'rejection_on' => '2026-09-29',
            'sample_collection_date' => '2026-09-24 10:00:00',
            'sample_received_at_lab_datetime' => '2026-09-29 08:00:00',
            'result_approved_by' => '1', 'result_approved_datetime' => '2026-09-30 15:00:00',
        ]);

        foreach (self::EID_SAMPLES as $index => [$code, $child, $first, $last, $sex, $result]) {
            $db->insert('form_eid', [
                'eid_id' => $index + 1, 'unique_id' => 'pdf-output-eid-' . ($index + 1),
                'sample_code' => $code, 'remote_sample_code' => 'R-' . $code,
                'child_id' => $child, 'child_name' => $first, 'child_surname' => $last, 'child_gender' => $sex,
                'child_dob' => '2026-0' . ($index + 3) . '-15', 'mother_name' => 'Mother ' . $last,
                'facility_id' => self::CLINIC, 'lab_id' => self::LAB, 'result' => $result,
                'result_status' => ACCEPTED,
                'sample_collection_date' => '2026-09-2' . $index . ' 10:00:00',
                'sample_received_at_lab_datetime' => '2026-09-29 08:00:00',
                'sample_tested_datetime' => '2026-09-30 11:00:00',
                'tested_by' => '1', 'result_reviewed_by' => '1', 'result_reviewed_datetime' => '2026-09-30 14:00:00',
                'result_approved_by' => '1', 'result_approved_datetime' => '2026-09-30 15:00:00',
            ]);
        }

        foreach (self::TB_SAMPLES as $index => [$code, $patient, $first, $last, $result, $xpert, $test, $encrypted]) {
            $crypt = static fn(string $value): string
                => $encrypted ? CommonService::encrypt($value, self::KEY) : $value;
            $db->insert('form_tb', [
                'tb_id' => $index + 1, 'unique_id' => 'pdf-output-tb-' . ($index + 1),
                'sample_code' => $code, 'remote_sample_code' => 'R-' . $code,
                'is_encrypted' => $encrypted ? 'yes' : 'no', 'patient_id' => $crypt($patient),
                'patient_name' => $crypt($first), 'patient_surname' => $crypt($last),
                'patient_gender' => $index % 2 === 0 ? 'female' : 'male', 'patient_dob' => '1990-05-1' . $index,
                'facility_id' => self::CLINIC, 'lab_id' => self::LAB, 'result' => $result,
                'xpert_mtb_result' => $xpert, 'result_status' => ACCEPTED,
                'patient_type' => $index === 0 ? '["new"]' : null,
                'tests_requested' => $index === 0 ? '["xpert"]' : null,
                'sample_collection_date' => '2026-09-2' . $index . ' 10:00:00',
                'sample_received_at_lab_datetime' => '2026-09-29 08:00:00',
                'sample_tested_datetime' => '2026-09-30 11:00:00',
                'tested_by' => '1', 'result_reviewed_by' => '1', 'result_reviewed_datetime' => '2026-09-30 14:00:00',
                'result_approved_by' => '1', 'result_approved_datetime' => '2026-09-30 15:00:00',
            ]);
            $db->insert('tb_tests', [
                'tb_id' => $index + 1, 'lab_id' => self::LAB, 'test_type' => 'xpert', 'test_result' => $test,
                'sample_received_at_lab_datetime' => '2026-09-29 08:00:00',
                'sample_tested_datetime' => '2026-09-30 11:00:00', 'tested_by' => '1',
                'result_reviewed_by' => '1', 'result_reviewed_datetime' => '2026-09-30 14:00:00',
                'result_approved_by' => '1', 'result_approved_datetime' => '2026-09-30 15:00:00',
            ]);
        }
        LegacyAppHarness::seedDefaults('r_tb_results');
        $this->seedCd4AndHepatitis();
        $this->seedCustomTests();
        $this->seedCovid19();
        $this->seedManifests();

        // A file cache shared by every test process holds global config for an hour
        MemoUtility::clear();
        ContainerRegistry::get(FileCacheUtility::class)->clear();

        // SystemService sets APP_TIMEZONE on every app request
        LegacyAppHarness::withSession([
            'labId' => self::LAB, 'instance' => ['type' => 'vluser'], 'APP_TIMEZONE' => date_default_timezone_get(),
        ]);
    }

    /**
     * The result print merges a PDF per sample and answers with a download token
     * for the merged file. Prints every sample with a result or a rejection:
     * VL 1, 2, 4 and 5, TB and Hepatitis 1 to 3, and 1 and 2 of every other module.
     */
    private function resultPdf(string $module, int $form, ?string $reportFormat = null): string
    {
        $db = LegacyAppHarness::db();
        $db->rawQuery("UPDATE global_config SET value = ? WHERE name = 'vl_form'", [$form]);
        if ($reportFormat !== null) {
            $db->rawQuery('UPDATE facility_details SET report_format = ? WHERE facility_id = ?', [
                json_encode([str_replace('-', '', $module) => $reportFormat]), self::LAB,
            ]);
        }
        MemoUtility::clear();
        ContainerRegistry::get(FileCacheUtility::class)->clear();

        // Sample ids, then the text each sample must print. Not every TB form
        // prints the sample code, so TB samples are found by patient ID. For the
        // encrypted sample that and the name are the decrypted values. The VL
        // CRESAR report format prints no sample code either, so its samples are
        // found by ART number.
        $samples = match ($module) {
            'vl' => ['1,2,4,5', $reportFormat === null
                ? ['VL-TEST-0001', 'VL-TEST-0002', 'VL-TEST-0004', 'VL-TEST-0005', 'Volume <1ml']
                : ['PT-0001', 'PT-0002', 'PT-0004', 'PT-0005', 'Volume <1ml', 'Amina Kamara']],
            'eid' => ['1,2', ['EID-TEST-0001', 'EID-TEST-0002']],
            'tb' => ['1,2,3', ['TBP-0001', 'TBP-0002', 'TBP-0003', 'Okafor']],
            'cd4' => ['1,2', ['CD4-TEST-0001', 'CD4-TEST-0002']],
            'hepatitis' => ['1,2,3', ['HEP-TEST-0001', 'HEP-TEST-0002', 'HEP-TEST-0003', 'HBV VL RESULTS : 310',
                'TEST REQUESTED : HBV', 'Riverside District']],
            'generic-tests' => ['1,2', ['GEN-TEST-0001', 'GEN-TEST-0002']],
            'covid-19' => ['1,2', ['Kamara', 'Mukendi']],
        };
        $request = LegacyAppHarness::withPost(['id' => $samples[0]], "/$module/results/generate-result-pdf.php");
        $token = trim($this->body($request));
        $path = DownloadTokenUtility::resolve($token, $reason);
        self::assertNotNull($path, "The result print answered with '$token' ($reason)");
        $pdf = (string) file_get_contents($path);
        unlink($path);

        // A snapshot taken while a form drops a sample would accept the drop
        $text = PdfFingerprint::plainText($pdf);
        foreach ($samples[1] as $code) {
            self::assertStringContainsString($code, $text, "The $module result print left out '$code'");
        }
        return $pdf;
    }

    /** Two CD4 samples, and three Hepatitis samples: an HCV, an HBV and a sample with both. */
    private function seedCd4AndHepatitis(): void
    {
        $db = LegacyAppHarness::db();
        $common = [
            'facility_id' => self::CLINIC, 'lab_id' => self::LAB, 'result_status' => ACCEPTED,
            'sample_received_at_lab_datetime' => '2026-09-29 08:00:00',
            'sample_tested_datetime' => '2026-09-30 11:00:00', 'tested_by' => '1',
            'result_reviewed_by' => '1', 'result_reviewed_datetime' => '2026-09-30 14:00:00',
            'result_approved_by' => '1', 'result_approved_datetime' => '2026-09-30 15:00:00',
        ];
        foreach ([[1, 'Amina', 'Kamara', 'female', '350'], [2, 'Jean', 'Mukendi', 'male', '820']] as $sample) {
            [$id, $first, $last, $sex, $count] = $sample;
            $db->insert('form_cd4', $common + [
                'cd4_id' => $id, 'unique_id' => 'pdf-output-cd4-' . $id, 'sample_code' => 'CD4-TEST-000' . $id,
                'remote_sample_code' => 'R-CD4-TEST-000' . $id, 'patient_art_no' => 'CD4P-000' . $id,
                'patient_first_name' => $first, 'patient_last_name' => $last, 'patient_gender' => $sex,
                'patient_dob' => '1990-05-1' . $id, 'cd4_result' => $count,
                'sample_collection_date' => '2026-09-2' . $id . ' 10:00:00',
            ]);
        }
        $hepatitis = [
            [1, 'Grace', 'Okafor', 'female', ['hcv_vl_count' => '1500']],
            [2, 'Joseph', 'Phiri', 'male', ['hbv_vl_count' => '1500']],
            [3, 'Esther', 'Banda', 'female', ['hcv_vl_count' => '2400', 'hbv_vl_count' => '310']],
        ];
        foreach ($hepatitis as [$id, $first, $last, $sex, $counts]) {
            $db->insert('form_hepatitis', $common + $counts + [
                'hepatitis_id' => $id, 'unique_id' => 'pdf-output-hep-' . $id, 'sample_code' => 'HEP-TEST-000' . $id,
                'remote_sample_code' => 'R-HEP-TEST-000' . $id, 'patient_id' => 'HEPP-000' . $id,
                'patient_name' => $first, 'patient_surname' => $last, 'patient_gender' => $sex,
                'patient_dob' => '1985-03-0' . $id,
                'hepatitis_test_type' => isset($counts['hcv_vl_count']) ? 'HCV' : 'hbv',
                'sample_collection_date' => '2026-09-2' . $id . ' 10:00:00',
            ]);
        }
    }

    /**
     * Two Custom Tests samples of one test type. The first has one test, the
     * second has two, which prints the layout with a card per test.
     */
    private function seedCustomTests(): void
    {
        $db = LegacyAppHarness::db();
        $db->insert('r_test_types', [
            'test_type_id' => 1, 'test_standard_name' => 'Malaria', 'test_generic_name' => 'Malaria',
            'test_short_code' => 'MAL', 'test_status' => 'active', 'test_form_config' => '{}',
            'test_results_config' => json_encode(self::CUSTOM_TEST_RESULTS_CONFIG),
        ]);
        $tested = [
            'lab_id' => self::LAB, 'facility_id' => self::CLINIC, 'test_name' => 'Malaria', 'tested_by' => '1',
            'sample_received_at_lab_datetime' => '2026-09-29 08:00:00',
            'sample_tested_datetime' => '2026-09-30 11:00:00',
            'result_reviewed_by' => '1', 'result_reviewed_datetime' => '2026-09-30 14:00:00',
            'result_approved_by' => '1', 'result_approved_datetime' => '2026-09-30 15:00:00',
        ];
        $samples = [
            [1, 'Amina', 'Kamara', 'female', ['Positive']],
            [2, 'Jean', 'Mukendi', 'male', ['Negative', 'Positive']],
        ];
        foreach ($samples as [$id, $first, $last, $sex, $results]) {
            $db->insert('form_generic', [
                'sample_id' => $id, 'unique_id' => 'pdf-output-gen-' . $id, 'test_type' => 1,
                'sample_code' => 'GEN-TEST-000' . $id, 'remote_sample_code' => 'R-GEN-TEST-000' . $id,
                'patient_id' => 'GENP-000' . $id, 'patient_first_name' => $first, 'patient_last_name' => $last,
                'patient_gender' => $sex, 'patient_dob' => '1992-07-0' . $id,
                'facility_id' => self::CLINIC, 'lab_id' => self::LAB, 'result_status' => ACCEPTED,
                'result' => end($results), 'sample_collection_date' => '2026-09-2' . $id . ' 10:00:00',
                'sample_received_at_lab_datetime' => '2026-09-29 08:00:00',
                'sample_tested_datetime' => '2026-09-30 11:00:00', 'tested_by' => '1',
                'result_reviewed_by' => '1', 'result_reviewed_datetime' => '2026-09-30 14:00:00',
                'result_approved_by' => '1', 'result_approved_datetime' => '2026-09-30 15:00:00',
            ]);
            foreach ($results as $result) {
                $db->insert('generic_test_results', $tested + [
                    'generic_id' => $id, 'result' => $result, 'final_result' => $result,
                ]);
            }
        }
    }

    /** Two COVID-19 samples, a positive and a negative, with one test each. */
    private function seedCovid19(): void
    {
        $db = LegacyAppHarness::db();
        LegacyAppHarness::seedDefaults('r_covid19_results');
        $samples = [[1, 'Amina', 'Kamara', 'female', 'positive'], [2, 'Jean', 'Mukendi', 'male', 'negative']];
        foreach ($samples as [$id, $first, $last, $sex, $result]) {
            $db->insert('form_covid19', [
                'covid19_id' => $id, 'unique_id' => 'pdf-output-cov-' . $id, 'vlsm_instance_id' => 'pdf-output',
                'sample_code' => 'COV-TEST-000' . $id, 'remote_sample_code' => 'R-COV-TEST-000' . $id,
                'patient_id' => 'COVP-000' . $id, 'patient_name' => $first, 'patient_surname' => $last,
                'patient_gender' => $sex, 'patient_dob' => '1988-04-0' . $id,
                'facility_id' => self::CLINIC, 'lab_id' => self::LAB, 'result' => $result,
                'result_status' => ACCEPTED, 'sample_collection_date' => '2026-09-2' . $id . ' 10:00:00',
                'sample_received_at_lab_datetime' => '2026-09-29 08:00:00',
                'sample_tested_datetime' => '2026-09-30 11:00:00', 'tested_by' => '1',
                'result_reviewed_by' => '1', 'result_reviewed_datetime' => '2026-09-30 14:00:00',
                'result_approved_by' => '1', 'result_approved_datetime' => '2026-09-30 15:00:00',
            ]);
            $db->insert('covid19_tests', [
                'covid19_id' => $id, 'facility_id' => self::LAB, 'test_name' => 'Real Time RT-PCR',
                'tested_by' => '1', 'sample_tested_datetime' => '2026-09-30 11:00:00',
                'testing_platform' => 'GeneXpert', 'result' => $result,
            ]);
        }
    }

    /**
     * Puts the samples of each module on its manifest. A referral moves the
     * samples from this lab to the reference lab, and the manifest is bound
     * for the reference lab.
     */
    private function seedManifests(): void
    {
        $db = LegacyAppHarness::db();
        $db->insert('facility_details', [
            'facility_id' => self::REFERENCE_LAB, 'facility_name' => 'Reference Lab', 'facility_type' => 2,
            'status' => 'active',
        ]);
        foreach (self::MANIFESTS as $name => [$id, , $table, $key, $samples]) {
            if ($table === null) {
                continue;
            }
            $referral = str_contains($name, 'referral');
            $code = sprintf($referral ? 'REFERRAL-TEST-%04d' : 'MANIFEST-TEST-%04d', $id);
            $module = explode('-manifest', str_replace('-referral', '', $name))[0];
            $db->insert('specimen_manifests', [
                'manifest_id' => $id, 'manifest_code' => $code, 'manifest_status' => 'pending',
                'manifest_type' => $referral ? 'referral' : 'collection', 'module' => $module,
                'lab_id' => $referral ? self::REFERENCE_LAB : self::LAB, 'number_of_samples' => count($samples),
                'added_by' => '1', 'request_created_datetime' => '2026-09-28 09:00:00',
            ]);
            $sample = $referral
                ? ['referral_manifest_code' => $code, 'referred_by_lab_id' => self::LAB,
                    'referred_to_lab_id' => self::REFERENCE_LAB]
                : ['sample_package_id' => $id, 'sample_package_code' => $code];
            $db->where($key, $samples, 'IN');
            $db->update($table, $sample);
        }
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        Carbon::setTestNow();
        if (LegacyAppHarness::configured()) {
            LegacyAppHarness::shutdown();
        }
    }

    /**
     * The manifest print saves the PDF and answers with its file name. A
     * referral manifest is printed by its code, every other one by its id.
     */
    private function manifestPdf(string $name): string
    {
        [$id, $script, , , , $expected] = self::MANIFESTS[$name];
        $key = str_contains($name, 'referral') ? sprintf('REFERRAL-TEST-%04d', $id) : (string) $id;
        $request = LegacyAppHarness::withPost(['id' => base64_encode($key)], '/' . $script);
        $file = trim($this->body($request));
        $path = VAR_TEMP_PATH . '/sample-manifests/' . basename($file);
        self::assertFileExists($path, 'The manifest print answered with ' . $file);
        $pdf = (string) file_get_contents($path);
        unlink($path);

        $text = PdfFingerprint::plainText($pdf);
        foreach ($expected as $sample) {
            self::assertStringContainsString($sample, $text, "The $name print left out '$sample'");
        }
        return $pdf;
    }

    /** @param array<string, string> $query */
    private function get(string $path, array $query): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('GET', $path)
            ->withQueryParams($query)
            ->withHeader('User-Agent', 'intelis-tests')
            ->withHeader('X-Forwarded-For', '127.0.0.1');
    }

    private function body(ServerRequestInterface $request): string
    {
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return (string) $handler->handle($request)->getBody();
    }
}
