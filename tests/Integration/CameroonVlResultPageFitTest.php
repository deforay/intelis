<?php

declare(strict_types=1);

namespace Tests\Integration;

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
use Tests\Support\LegacyAppHarness;
use Tests\Support\PdfFingerprint;
use Tests\Support\TestRun;

use const COUNTRY\CAMEROON;
use const SAMPLE_STATUS\ACCEPTED;

/**
 * A Cameroon VL result is one page. A lab reported results printing on two
 * pages, the second one blank: whenever a sample's content ended near the
 * bottom margin, the trailing spacer rows and the bottom text spilled onto a
 * page of their own. The lab had chosen to put the bottom text above the
 * footer, and the form ignored that setting.
 *
 * Each sample here is printed on its own, with every optional block the form
 * has filled in, under each place a lab can put the bottom text.
 */
final class CameroonVlResultPageFitTest extends TestCase
{
    private const LAB = 5;
    private const CLINIC = 11;

    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config', 'activity_log',
        'roles', 'user_details', 'facility_details', 'geographical_divisions', 'instruments',
        'r_sample_status', 'r_funding_sources', 'r_implementation_partners',
        'form_vl', 'r_vl_sample_rejection_reasons', 'r_vl_sample_type', 'r_vl_test_reasons',
        'specimen_manifests', 'batch_details', 'r_recommended_corrective_actions',
    ];

    /** Lab comments as long as the ones labs write on a repeat test. */
    private const COMMENTS = 'Sample retested after a first run was invalid. Patient on second line since '
        . 'March, adherence counselling recommended before the next viral load. Please contact the laboratory '
        . 'for any question about this result.';

    /** @return array<string, array{string, int}> */
    public static function layoutProvider(): array
    {
        return [
            'bottom text above the footer, 1000 copies' => ['aboveFooter', 1],
            'bottom text above the footer, female, modified, comments' => ['aboveFooter', 2],
            'bottom text above the footer, everything' => ['aboveFooter', 3],
            'bottom text below the platform, 1000 copies' => ['belowPlatformName', 1],
            'bottom text below the platform, female, modified, comments' => ['belowPlatformName', 2],
            'bottom text below the platform, everything' => ['belowPlatformName', 3],
        ];
    }

    #[RunInSeparateProcess]
    #[DataProvider('layoutProvider')]
    public function testEachResultPrintsOnOnePage(string $bottomTextLocation, int $sampleId): void
    {
        $this->boot($bottomTextLocation);

        $request = LegacyAppHarness::withPost(['id' => (string) $sampleId], '/vl/results/generate-result-pdf.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $token = trim((string) $handler->handle($request)->getBody());
        $path = DownloadTokenUtility::resolve($token, $reason);
        self::assertNotNull($path, "The result print answered with '$token' ($reason)");
        $pdf = (string) file_get_contents($path);
        unlink($path);

        $text = PdfFingerprint::plainText($pdf);
        self::assertStringContainsString("VL-FIT-000$sampleId", $text, 'The print left out the sample');
        self::assertStringContainsString('Detection Limit', $text, 'The print left out the bottom text');
        $fingerprint = PdfFingerprint::of($pdf);
        self::assertSame(1, $fingerprint['pages'], 'The result spilled onto a second page');

        // The lab's setting decides whether the bottom text prints above or
        // below the approval
        $top = [];
        foreach ($fingerprint['words'] as [, $word, , $y]) {
            $top[$word] ??= $y;
        }
        self::assertArrayHasKey('NB', $top);
        self::assertArrayHasKey('APPROVED', $top);
        if ($bottomTextLocation === 'aboveFooter') {
            self::assertGreaterThan($top['APPROVED'], $top['NB'], 'The bottom text is not above the footer');
        } else {
            self::assertLessThan($top['APPROVED'], $top['NB'], 'The bottom text is not below the platform');
        }
    }

    private function boot(string $bottomTextLocation): void
    {
        if (!LegacyAppHarness::configured()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        if (!PdfFingerprint::available()) {
            self::markTestSkipped('Install poppler (pdfinfo, pdftotext, pdfimages) to read PDFs.');
        }
        Carbon::setTestNow('2026-10-01 12:00:00');

        if (!defined('UPLOAD_PATH')) {
            define('UPLOAD_PATH', WEB_ROOT . '/uploads');
        }
        $logo = UPLOAD_PATH . '/logo/test-logo.png';
        MiscUtility::makeDirectory(dirname($logo));
        copy(ROOT_PATH . '/public/assets/img/approved.png', $logo);
        // The shape of the signatures labs upload: 250 x 188, printed 100px wide
        $signature = UPLOAD_PATH . '/users-signature/page-fit-signature.png';
        MiscUtility::makeDirectory(dirname($signature));
        $canvas = imagecreatetruecolor(250, 188);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imageline($canvas, 10, 150, 240, 40, (int) imagecolorallocate($canvas, 0, 0, 120));
        imagepng($canvas, $signature);

        $db = LegacyAppHarness::boot(
            TestRun::database('intelis_cameroon_page_fit_test'),
            self::TABLES,
            ['remoteURL' => 'https://sts.example.org', 'tryCrypt' => 'page-fit-test-key']
        );
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ContainerRegistry::get('applicationConfig'));
        }
        LegacyAppHarness::addMigrationColumns('5.7.80', ['specimen_manifests']);
        LegacyAppHarness::seedDefaults('global_config');
        // As the Cameroon network runs it: log values printed, no smileys
        $config = [
            'logo' => 'test-logo.png', 'header' => 'Ministry of Health', 'vl_form' => (string) CAMEROON,
            'vl_display_log_result' => 'yes', 'show_smiley' => 'no',
        ];
        foreach ($config as $name => $value) {
            $db->rawQuery('UPDATE global_config SET value = ? WHERE name = ?', [$value, $name]);
        }
        $db->insert('r_sample_status', ['status_id' => ACCEPTED, 'status_name' => 'Accepted', 'status' => 'active']);
        $db->insert('r_vl_sample_type', ['sample_id' => 1, 'sample_name' => 'Plasma', 'status' => 'active']);
        // The settings of the lab that reported the blank pages
        $db->insert('facility_details', [
            'facility_id' => self::LAB, 'facility_name' => 'Centre Dream Test', 'facility_type' => 2,
            'status' => 'active',
            'facility_attributes' => json_encode([
                'report_top_margin' => '23',
                'bottom_text_location' => $bottomTextLocation,
            ]),
        ]);
        $db->insert('facility_details', [
            'facility_id' => self::CLINIC, 'facility_name' => "HOPITAL CATHOLIQUE NOTRE DAME DE L SANTÉ DE BATSINGLA'A",
            'facility_type' => 1, 'facility_code' => 'HDT-001', 'facility_state' => 'Ouest',
            'facility_district' => 'Menoua', 'facility_emails' => 'labo@example.org', 'status' => 'active',
        ]);
        $db->insert('roles', ['role_id' => 1, 'role_name' => 'Admin', 'role_code' => 'AD', 'status' => 'active']);
        $db->insert('user_details', [
            'user_id' => '1', 'user_name' => 'Approving Biologist', 'login_id' => 'test', 'status' => 'active',
            'role_id' => 1, 'user_signature' => 'page-fit-signature.png',
        ]);
        $db->insert('user_details', [
            'user_id' => '2', 'user_name' => 'Reviewing Technician', 'login_id' => 'test2', 'status' => 'active',
            'role_id' => 1,
        ]);

        $common = [
            'facility_id' => self::CLINIC, 'lab_id' => self::LAB, 'result_status' => ACCEPTED,
            'vlsm_country_id' => CAMEROON, 'specimen_type' => 1,
            'patient_art_no' => 'OU01/02/16/035', 'patient_first_name' => 'Marie-Claire',
            'patient_last_name' => 'Nguemo Tchinda', 'patient_dob' => '1986-04-12',
            'patient_mobile_number' => '+237 650 000 000', 'current_regimen' => 'TDF+3TC+DTG',
            'request_clinician_name' => 'Dr Fotso', 'request_clinician_phone_number' => '+237 670 000 000',
            'sample_collection_date' => '2026-09-24 09:00:00',
            'sample_received_at_lab_datetime' => '2026-09-26 08:00:00',
            'sample_tested_datetime' => '2026-09-30 11:00:00', 'vl_test_platform' => 'Abbott m2000',
            'tested_by' => '2', 'result_reviewed_by' => '2', 'result_reviewed_datetime' => '2026-09-30 14:00:00',
            'result_approved_by' => '1', 'result_approved_datetime' => '2026-09-30 15:00:00',
        ];
        $modified = [
            'result_modified' => 'yes',
            'reason_for_result_changes' => json_encode([[
                'usr' => '1', 'msg' => 'Transcription error', 'dtime' => '2026-09-30 16:00:00',
                'previousResult' => '150',
            ]]),
            'lab_tech_comments' => self::COMMENTS,
        ];
        $samples = [
            1 => ['patient_gender' => 'male', 'result' => '1000', 'result_value_absolute' => '1000'],
            2 => ['patient_gender' => 'female', 'is_patient_pregnant' => 'no', 'is_patient_breastfeeding' => 'yes',
                'result' => '1530', 'result_value_absolute' => '1530'] + $modified,
            3 => ['patient_gender' => 'female', 'is_patient_pregnant' => 'no', 'is_patient_breastfeeding' => 'yes',
                'health_insurance_code' => 'CNPS-0042-7781', 'lab_assigned_code' => 'DSC-2026-0918',
                // As long as the longest the lab's samples carry
                'patient_first_name' => 'Marie-Claire Josephine', 'patient_middle_name' => 'Ngo Bassong',
                'patient_last_name' => 'Tchinda Nguemo Epse Kamga',
                'current_regimen' => 'TDF+3TC+DTG then AZT+3TC+LPV/r (second line, 2024)',
                'request_clinician_name' => 'Dr Fotso Kamdem Jean-Baptiste',
                'result' => '2450000', 'result_value_absolute' => '2450000'] + $modified,
        ];
        foreach ($samples as $id => $sample) {
            $db->insert('form_vl', ['vl_sample_id' => $id, 'unique_id' => "page-fit-$id",
                'sample_code' => "VL-FIT-000$id"] + $sample + $common);
        }

        MemoUtility::clear();
        ContainerRegistry::get(FileCacheUtility::class)->clear();
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
}
