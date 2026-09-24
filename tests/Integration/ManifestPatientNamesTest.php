<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\RedirectException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\TestRequestsService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Sqids\Sqids;
use Tests\Support\LegacyAppHarness;
use Throwable;

use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;

/**
 * Patient names on a printed manifest: chosen per manifest on Add/Edit Manifest,
 * starting from the module's "Show participant name in manifest" setting.
 *
 * Only the global setting existed, and the modules read it differently. A
 * request saved with patient data encryption on also printed its patient id
 * and names as ciphertext, because no manifest decrypted them.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can drive
 * it once: every test runs in its own process.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ManifestPatientNamesTest extends TestCase
{
    private const DATABASE = 'intelis_manifest_patient_names_test';

    private const LAB = 5;

    // A secretbox key is 32 bytes.
    private const KEY = 'abcdefghijklmnopqrstuvwxyz012345';

    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config', 'activity_log',
        'roles', 'user_details', 'facility_details', 'geographical_divisions', 'testing_labs',
        'batch_details', 'r_sample_status', 'r_funding_sources', 'r_implementation_partners',
        'form_vl', 'r_vl_sample_rejection_reasons', 'r_vl_sample_type', 'r_vl_test_reasons',
        'specimen_manifests',
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

        // Global config is file-cached across processes; a value another run left
        // there would stand in for the settings seeded here.
        self::clearFileCache();

        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), self::TABLES);
        LegacyAppHarness::withSession();
        // sql/init.sql predates the column; 5.7.80 adds it.
        $db->rawQuery(
            "ALTER TABLE `specimen_manifests`
                ADD COLUMN `show_patient_names` ENUM('yes','no') NULL DEFAULT NULL AFTER `lab_id`"
        );
        $db->insert('r_sample_status', [
            'status_id' => RECEIVED_AT_CLINIC, 'status_name' => 'Received at Clinic', 'status' => 'active',
        ]);
        $db->insert('facility_details', [
            'facility_id' => self::LAB, 'facility_name' => 'Lab', 'facility_type' => 2, 'status' => 'active',
        ]);
        $db->insert('testing_labs', ['test_type' => 'vl', 'facility_id' => self::LAB]);
        $db->insert('global_config', ['name' => 'key', 'value' => self::KEY]);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testAManifestsOwnChoiceWinsOverTheSetting(): void
    {
        $this->setting('tb_show_participant_name_in_manifest', 'no');

        self::assertTrue($this->service()->showsPatientNamesOnManifest('tb', ['show_patient_names' => 'yes']));
        self::assertFalse($this->service()->showsPatientNamesOnManifest('tb', ['show_patient_names' => 'no']));
    }

    #[RunInSeparateProcess]
    public function testAManifestWithNoChoiceFollowsTheSettingAsEachModuleAlwaysReadIt(): void
    {
        // Unset: VL and CD4 have always printed names, the others never.
        self::assertTrue($this->service()->showsPatientNamesOnManifest('vl', ['show_patient_names' => null]));
        self::assertTrue($this->service()->showsPatientNamesOnManifest('cd4'));
        self::assertFalse($this->service()->showsPatientNamesOnManifest('tb'));
        self::assertFalse($this->service()->showsPatientNamesOnManifest('generic-tests'));
    }

    #[RunInSeparateProcess]
    public function testTheSettingIsReadUnderTheModulesOwnName(): void
    {
        $this->setting('vl_show_participant_name_in_manifest', 'no');
        $this->setting('generic_show_participant_name_in_manifest', 'yes');

        self::assertFalse($this->service()->showsPatientNamesOnManifest('vl'));
        self::assertTrue($this->service()->showsPatientNamesOnManifest('generic-tests'));
    }

    #[RunInSeparateProcess]
    public function testEncryptedPatientDetailsAreDecryptedAndTheNameJoinedWithASpace(): void
    {
        $rows = $this->service()->decryptManifestRows([
            [
                'is_encrypted' => 'yes',
                'patient_id' => CommonService::crypto('encrypt', 'ART-7', self::KEY),
                'patient_name' => CommonService::crypto('encrypt', 'Ada', self::KEY),
                'patient_surname' => CommonService::crypto('encrypt', 'Lovelace', self::KEY),
            ],
            ['is_encrypted' => 'no', 'patient_id' => 'ART-8', 'patient_name' => 'Ben', 'patient_surname' => ''],
        ], ['patient_id', 'patient_name', 'patient_surname'], ['patient_name', 'patient_surname']);

        self::assertSame('ART-7', $rows[0]['patient_id']);
        self::assertSame('Ada Lovelace', $rows[0]['patient_fullname']);
        self::assertSame('Ben', $rows[1]['patient_fullname']);
    }

    #[RunInSeparateProcess]
    public function testAddingAManifestKeepsTheChoice(): void
    {
        $sample = $this->sample('A');

        $this->drive('/specimen-referral-manifest/add-manifest-helper.php', [
            'module' => 'vl', 'testingLab' => self::LAB, 'packageCode' => 'M-1', 'showPatientNames' => 'no',
            'selectedSample' => (new Sqids())->encode([$sample]),
        ]);

        self::assertSame('no', $this->choice('M-1'));
    }

    #[RunInSeparateProcess]
    public function testEditingAManifestChangesTheChoice(): void
    {
        $manifest = $this->manifest('M-2');
        $sample = $this->sample('A', 'M-2', $manifest);

        $this->drive('/specimen-referral-manifest/edit-manifest-helper.php', [
            'module' => 'vl', 'packageId' => $manifest, 'testingLab' => self::LAB,
            'reasonForChange' => 'Names', 'showPatientNames' => 'yes',
            'selectedSample' => (new Sqids())->encode([$sample]),
        ]);

        self::assertSame('yes', $this->choice('M-2'));
    }

    #[RunInSeparateProcess]
    public function testAnEditFromAnotherLabsSampleListChangesNothing(): void
    {
        $manifest = $this->manifest('M-3');
        $sample = $this->sample('A', 'M-3', $manifest);
        $other = $this->sample('B');

        $url = $this->drive('/specimen-referral-manifest/edit-manifest-helper.php', [
            'module' => 'vl', 'packageId' => $manifest, 'testingLab' => self::LAB,
            'reasonForChange' => 'Late list', 'samplesListedForLab' => '99',
            'selectedSample' => (new Sqids())->encode([$other]),
        ]);

        self::assertStringContainsString('edit-manifest.php', (string) $url);
        $row = LegacyAppHarness::db()->rawQueryOne(
            'SELECT sample_package_id FROM form_vl WHERE vl_sample_id = ?',
            [$sample]
        );
        self::assertSame($manifest, (int) $row['sample_package_id']);
    }

    #[RunInSeparateProcess]
    public function testALabPrintsOnlyManifestsBoundForIt(): void
    {
        $this->actAsLab(self::LAB);

        self::assertFalse($this->service()->mayPrintManifests([['lab_id' => 99]], [['x' => 1]]));
        self::assertTrue($this->service()->mayPrintManifests([['lab_id' => self::LAB]], []));
        self::assertTrue($this->service()->mayPrintManifests([['lab_id' => null]], []));
        self::assertStringContainsString('vl.lab_id = ' . self::LAB, $this->service()->manifestPrintScope());
    }

    #[RunInSeparateProcess]
    public function testAReferralManifestPrintsForTheLabThatSentOrReceivesIt(): void
    {
        $this->actAsLab(self::LAB);

        $scope = $this->service()->manifestPrintScope(referral: true);
        self::assertStringContainsString('vl.referred_by_lab_id = ' . self::LAB, $scope);
        self::assertStringContainsString('vl.referred_to_lab_id = ' . self::LAB, $scope);
        self::assertFalse($this->service()->mayPrintManifests([['lab_id' => 99]], [], referral: true));
        self::assertTrue($this->service()->mayPrintManifests([['lab_id' => 99]], [['x' => 1]], referral: true));
    }

    #[RunInSeparateProcess]
    public function testASessionNotActingAsALabPrintsAsBefore(): void
    {
        self::assertSame('', $this->service()->manifestPrintScope());
        self::assertTrue($this->service()->mayPrintManifests([['lab_id' => 99]], []));
    }

    #[RunInSeparateProcess]
    public function testALabCannotMoveItsManifestToAnotherLab(): void
    {
        LegacyAppHarness::db()->insert('facility_details', [
            'facility_id' => 6, 'facility_name' => 'Other lab', 'facility_type' => 2, 'status' => 'active',
        ]);
        LegacyAppHarness::db()->insert('testing_labs', ['test_type' => 'vl', 'facility_id' => 6]);
        $manifest = $this->manifest('M-4');
        $sample = $this->sample('N', 'M-4', $manifest);
        LegacyAppHarness::db()->rawQuery('UPDATE form_vl SET lab_id = NULL WHERE vl_sample_id = ?', [$sample]);
        $this->actAsLab(self::LAB);

        $this->drive('/specimen-referral-manifest/edit-manifest-helper.php', [
            'module' => 'vl', 'packageId' => $manifest, 'testingLab' => 6, 'reasonForChange' => 'Away',
            'selectedSample' => (new Sqids())->encode([$sample]),
        ]);

        $db = LegacyAppHarness::db();
        $row = $db->rawQueryOne('SELECT lab_id FROM specimen_manifests WHERE manifest_id = ?', [$manifest]);
        self::assertSame(self::LAB, (int) $row['lab_id']);
        self::assertNull($db->rawQueryOne('SELECT lab_id FROM form_vl WHERE vl_sample_id = ?', [$sample])['lab_id']);
    }

    private function actAsLab(int $lab): void
    {
        $_SESSION['instance']['type'] = 'vluser';
        $_SESSION['labId'] = $lab;
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

    private function service(): TestRequestsService
    {
        /** @var TestRequestsService $service */
        $service = ContainerRegistry::get(TestRequestsService::class);
        return $service;
    }

    private function setting(string $name, string $value): void
    {
        LegacyAppHarness::db()->insert('global_config', ['name' => $name, 'value' => $value]);
    }

    /** @param array<string, mixed> $post */
    private function drive(string $path, array $post): ?string
    {
        $request = LegacyAppHarness::withPost($post, $path);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        try {
            $handler->handle($request);
        } catch (Throwable $e) {
            for ($cause = $e; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
                if ($cause instanceof RedirectException) {
                    return $cause->getUrl();
                }
            }
            throw $e;
        }
        return null;
    }

    private function manifest(string $code): int
    {
        LegacyAppHarness::db()->insert('specimen_manifests', [
            'manifest_code' => $code,
            'manifest_status' => TestRequestsService::MANIFEST_PENDING,
            'module' => 'vl',
            'lab_id' => self::LAB,
            'added_by' => '1',
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function sample(string $code, ?string $manifestCode = null, ?int $manifestId = null): int
    {
        LegacyAppHarness::db()->insert('form_vl', [
            'unique_id' => 'uid-' . $code,
            'sample_code' => 'S-' . $code,
            'remote_sample_code' => 'R-' . $code,
            'remote_sample' => 'yes',
            'lab_id' => self::LAB,
            'sample_package_id' => $manifestId,
            'sample_package_code' => $manifestCode,
            'result_status' => RECEIVED_AT_CLINIC,
            'sample_collection_date' => '2026-09-01 10:00:00',
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function choice(string $code): ?string
    {
        $row = LegacyAppHarness::db()->rawQueryOne(
            'SELECT show_patient_names FROM specimen_manifests WHERE manifest_code = ?',
            [$code]
        );
        return $row['show_patient_names'] ?? null;
    }
}
