<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\TestRequestsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\REFERRED;

/**
 * A session acting as one lab (cloud-LIS) prints only its own manifests.
 *
 * The prints read samples and manifests by id alone, so an operator of one lab
 * could print another lab's manifest, with patient names decrypted, and the
 * print recorded itself and marked the manifest dispatched. The helper methods
 * are tested elsewhere; this drives the prints, so a print that stops applying
 * them fails here.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can drive
 * it once: every test that drives a page runs in its own process.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ManifestPrintScopeTest extends TestCase
{
    private const DATABASE = 'intelis_manifest_print_scope_test';

    private const OWN_LAB = 5;
    private const OTHER_LAB = 6;
    private const THIRD_LAB = 7;

    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config', 'activity_log',
        'roles', 'user_details', 'facility_details', 'geographical_divisions',
        'batch_details', 'r_sample_status', 'r_funding_sources', 'r_implementation_partners',
        'form_vl', 'r_vl_sample_rejection_reasons', 'r_vl_sample_type', 'r_vl_test_reasons',
        'form_tb', 'tb_tests', 'r_tb_sample_type', 'specimen_manifests',
    ];

    /** The manifest prints, each of which must scope what it reads. */
    private const PRINTS = [
        'app/specimen-referral-manifest/generateVLManifest.php',
        'app/specimen-referral-manifest/generateEIDManifest.php',
        'app/specimen-referral-manifest/generateCovid19Manifest.php',
        'app/specimen-referral-manifest/generateHepatitisManifest.php',
        'app/specimen-referral-manifest/generateTBManifest.php',
        'app/specimen-referral-manifest/generateCD4Manifest.php',
        'app/specimen-referral-manifest/generateGenericManifest.php',
        'app/tb/results/pdf/generate-generic-manifest.php',
        'app/generic-tests/results/pdf/generate-generic-manifest.php',
    ];

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    /** @return iterable<string, array{string}> */
    public static function prints(): iterable
    {
        foreach (self::PRINTS as $path) {
            yield substr($path, strlen('app/')) => [$path];
        }
    }

    /**
     * Every print adds the scope to its sample query and checks the manifests
     * before printing, recording or marking anything. Only some are driven below;
     * this holds all nine to the same wiring.
     */
    #[DataProvider('prints')]
    public function testEveryPrintScopesItsSamplesAndChecksTheManifest(string $path): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        $referral = str_contains($path, '/results/pdf/');
        $referralArgument = $referral ? 'referral:\s*true' : '';

        self::assertMatchesRegularExpression(
            '/\$printScope\s*=\s*\$testRequestsService->manifestPrintScope\(\s*' . $referralArgument . '\s*\)/',
            $source
        );
        self::assertMatchesRegularExpression('/\$sQuery\s*=\s*"[^;]*"\s*\.\s*\$printScope/s', $source);
        self::assertMatchesRegularExpression(
            '/mayPrintManifests\((?:(?!referral)[^;])*?,\s*\'form_\w+\''
                . ($referral ? ',\s*' . $referralArgument : '')
                . '\s*\)\)\s*\{\s*\$bResult\s*=\s*\[\];/',
            $source
        );

        // The check comes before every write: recording the print on the manifest,
        // and marking it dispatched.
        $check = strpos($source, 'mayPrintManifests(');
        self::assertNotFalse($check, 'the print checks the manifests');
        preg_match_all(
            '/->update\(\s*[\'"]specimen_manifests[\'"]|markManifestDispatched\(/',
            $source,
            $writes,
            PREG_OFFSET_CAPTURE
        );
        self::assertNotEmpty($writes[0], 'the print records itself');
        foreach ($writes[0] as [$write, $at]) {
            self::assertLessThan($at, $check, "$write at offset $at comes before the manifest check");
        }
    }

    /**
     * The control for the refusals below: without it, a print that fails for any
     * other reason would pass them too.
     */
    #[RunInSeparateProcess]
    public function testALabPrintsItsOwnManifest(): void
    {
        $this->boot();
        $manifest = $this->manifest('M-OWN', self::OWN_LAB, 'vl');
        $this->vlSample('A1', self::OWN_LAB, $manifest, 'M-OWN');

        $output = $this->print('/specimen-referral-manifest/generateVLManifest.php', base64_encode((string) $manifest));

        self::assertNotSame('', $output);
        self::assertNotNull($this->manifestRow($manifest)['manifest_print_history']);
    }

    #[RunInSeparateProcess]
    public function testALabCannotPrintAnotherLabsManifest(): void
    {
        $this->boot();
        $manifest = $this->manifest('M-OTHER', self::OTHER_LAB, 'vl');
        $this->vlSample('B1', self::OTHER_LAB, $manifest, 'M-OTHER');

        $output = $this->print('/specimen-referral-manifest/generateVLManifest.php', base64_encode((string) $manifest));

        self::assertSame('', $output);
        $row = $this->manifestRow($manifest);
        self::assertSame(TestRequestsService::MANIFEST_PENDING, $row['manifest_status']);
        self::assertNull($row['manifest_print_history']);
    }

    #[RunInSeparateProcess]
    public function testALabCannotPrintAnUnboundManifestOfAnotherLabsSamples(): void
    {
        $this->boot();
        $manifest = $this->manifest('M-UNBOUND', null, 'vl');
        $this->vlSample('C1', self::OTHER_LAB, $manifest, 'M-UNBOUND');

        $output = $this->print('/specimen-referral-manifest/generateVLManifest.php', base64_encode((string) $manifest));

        self::assertSame('', $output);
        $row = $this->manifestRow($manifest);
        self::assertSame(TestRequestsService::MANIFEST_PENDING, $row['manifest_status']);
        self::assertNull($row['manifest_print_history']);
    }

    #[RunInSeparateProcess]
    public function testALabPrintsAnUnboundManifestHoldingItsOwnSample(): void
    {
        $this->boot();
        $manifest = $this->manifest('M-SHARED', null, 'vl');
        $this->vlSample('D1', self::OWN_LAB, $manifest, 'M-SHARED');
        $this->vlSample('D2', self::OTHER_LAB, $manifest, 'M-SHARED');

        $output = $this->print('/specimen-referral-manifest/generateVLManifest.php', base64_encode((string) $manifest));

        self::assertNotSame('', $output);
        self::assertNotNull($this->manifestRow($manifest)['manifest_print_history']);
    }

    #[RunInSeparateProcess]
    public function testListingAnotherLabsManifestBesideItsOwnDoesNotPrintIt(): void
    {
        $this->boot();
        $own = $this->manifest('M-OWN-2', self::OWN_LAB, 'vl');
        $this->vlSample('E1', self::OWN_LAB, $own, 'M-OWN-2');
        $other = $this->manifest('M-OTHER-2', self::OTHER_LAB, 'vl');
        $this->vlSample('E2', self::OTHER_LAB, $other, 'M-OTHER-2');

        $output = $this->print('/specimen-referral-manifest/generateVLManifest.php', base64_encode("$own,$other"));

        self::assertSame('', $output);
        self::assertNull($this->manifestRow($own)['manifest_print_history']);
    }

    #[RunInSeparateProcess]
    public function testALabCannotPrintAReferralItNeitherSentNorReceives(): void
    {
        $this->boot();
        $manifest = $this->manifest('REF-OTHER', self::THIRD_LAB, 'tb', 'referral');
        LegacyAppHarness::db()->insert('form_tb', [
            'unique_id' => 'uid-tb', 'sample_code' => 'TB-1', 'facility_id' => 11,
            'lab_id' => self::OTHER_LAB, 'referred_by_lab_id' => self::OTHER_LAB,
            'referred_to_lab_id' => self::THIRD_LAB, 'referral_manifest_code' => 'REF-OTHER',
            'result_status' => REFERRED, 'sample_collection_date' => '2026-09-01 10:00:00',
        ]);

        $output = $this->print('/tb/results/pdf/generate-generic-manifest.php', base64_encode('REF-OTHER'));

        self::assertSame('', $output);
        $row = $this->manifestRow($manifest);
        self::assertSame(TestRequestsService::MANIFEST_PENDING, $row['manifest_status']);
        self::assertNull($row['manifest_print_history']);
    }

    private function boot(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), self::TABLES);
        LegacyAppHarness::addMigrationColumns('5.7.80', ['specimen_manifests']);
        // The settings a print reads for its header.
        foreach (['logo' => '', 'header' => 'Test Lab', 'vl_form' => '0'] as $name => $value) {
            $db->insert('global_config', ['display_name' => $name, 'name' => $name, 'value' => $value]);
        }
        foreach ([RECEIVED_AT_CLINIC => 'Received at Clinic', REFERRED => 'Referred'] as $id => $name) {
            $db->insert('r_sample_status', ['status_id' => $id, 'status_name' => $name, 'status' => 'active']);
        }
        foreach ([self::OWN_LAB, self::OTHER_LAB, self::THIRD_LAB] as $lab) {
            $db->insert('facility_details', [
                'facility_id' => $lab, 'facility_name' => "Lab $lab", 'facility_type' => 2, 'status' => 'active',
            ]);
        }
        $db->insert('facility_details', [
            'facility_id' => 11, 'facility_name' => 'Clinic', 'facility_type' => 1, 'status' => 'active',
        ]);

        // A lab operator on a LIS: the session that is scoped to one lab.
        LegacyAppHarness::withSession(['labId' => self::OWN_LAB, 'instance' => ['type' => 'vluser']]);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    private function print(string $path, string $id): string
    {
        $request = LegacyAppHarness::withPost(['id' => $id], $path);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return trim((string) $handler->handle($request)->getBody());
    }

    private function manifest(string $code, ?int $lab, string $module, string $type = 'collection'): int
    {
        LegacyAppHarness::db()->insert('specimen_manifests', [
            'manifest_code' => $code,
            'manifest_status' => TestRequestsService::MANIFEST_PENDING,
            'manifest_type' => $type,
            'module' => $module,
            'lab_id' => $lab,
            'added_by' => '1',
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function vlSample(string $code, int $lab, int $manifestId, string $manifestCode): void
    {
        LegacyAppHarness::db()->insert('form_vl', [
            'unique_id' => 'uid-' . $code,
            'sample_code' => 'S-' . $code,
            'remote_sample_code' => 'R-' . $code,
            'facility_id' => 11,
            'lab_id' => $lab,
            'sample_package_id' => $manifestId,
            'sample_package_code' => $manifestCode,
            'result_status' => RECEIVED_AT_CLINIC,
            'sample_collection_date' => '2026-09-01 10:00:00',
        ]);
    }

    /** @return array<string, mixed> */
    private function manifestRow(int $id): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM specimen_manifests WHERE manifest_id = ?', [$id]);
    }
}
