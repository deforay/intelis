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

use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;

/**
 * A manifest holds the samples of its one testing lab, and editing it can change
 * that lab without moving any sample.
 *
 * The lab was locked on Edit Manifest, so a manifest made for the wrong lab could
 * not be corrected. Both saves also took whatever samples they were handed: Add
 * Manifest rewrote each one's lab to the manifest's (a silent move), Edit
 * Manifest put another lab's sample on the manifest as it was, a received
 * manifest could still be saved over, and samples taken off a manifest were
 * never re-sent to the STS. A missing lab redirected without stopping, so the
 * manifest was saved anyway. The sample picker dropped the manifest's own
 * samples outside the search filters, and saving then removed them.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can drive
 * it once: every test that drives a page runs in its own process.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ManifestTestingLabTest extends TestCase
{
    private const DATABASE = 'intelis_manifest_testing_lab_test';

    private const LAB_A = 5;
    private const LAB_B = 6;

    // form_vl's foreign keys need the tables they point at.
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

        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), self::TABLES);
        LegacyAppHarness::withSession();

        foreach ([self::LAB_A, self::LAB_B] as $lab) {
            $db->insert('facility_details', [
                'facility_id' => $lab, 'facility_name' => "Lab $lab", 'facility_type' => 2, 'status' => 'active',
            ]);
            $db->insert('testing_labs', ['test_type' => 'vl', 'facility_id' => $lab]);
        }
        foreach ([RECEIVED_AT_CLINIC => 'Received at Clinic', CANCELLED => 'Cancelled'] as $id => $name) {
            $db->insert('r_sample_status', ['status_id' => $id, 'status_name' => $name, 'status' => 'active']);
        }
        $db->insert('facility_details', [
            'facility_id' => 11, 'facility_name' => 'Riverside Clinic', 'facility_type' => 1, 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    #[RunInSeparateProcess]
    public function testChangingTheLabTakesTheOldLabsSamplesOffWithoutMovingThem(): void
    {
        $manifest = $this->manifest('M-1', self::LAB_A);
        $a = $this->sample('A', self::LAB_A, 'M-1', $manifest);
        $b = $this->sample('B', self::LAB_A, 'M-1', $manifest);
        // Older rows can carry only the manifest code, and a cancelled sample
        // stays linked until a save takes it off.
        $codeOnly = $this->sample('CODEONLY', self::LAB_A, 'M-1');
        $cancelled = $this->sample('CX', self::LAB_A, 'M-1', $manifest, CANCELLED);
        $c = $this->sample('C', self::LAB_B);

        $url = $this->edit($manifest, self::LAB_B, [$c]);

        self::assertStringContainsString('view-manifests.php', (string) $url);
        foreach ([$a, $b, $codeOnly, $cancelled] as $id) {
            $row = $this->row($id);
            self::assertNull($row['sample_package_id']);
            self::assertNull($row['sample_package_code']);
            self::assertSame(self::LAB_A, (int) $row['lab_id'], 'An edit must not move a sample to another lab.');
            self::assertSame(0, (int) $row['data_sync'], 'A sample taken off a manifest must be re-sent.');
        }
        $row = $this->row($c);
        self::assertSame($manifest, (int) $row['sample_package_id']);
        self::assertSame('M-1', $row['sample_package_code']);

        $saved = $this->manifestRow($manifest);
        self::assertSame(self::LAB_B, (int) $saved['lab_id']);
        self::assertSame(1, (int) $saved['number_of_samples']);
        $history = json_decode((string) $saved['manifest_change_history'], true);
        self::assertSame(self::LAB_A, $history[0]['previousLabId']);
        self::assertSame(self::LAB_B, $history[0]['labId']);
        self::assertSame('Wrong lab', $history[0]['reason']);
    }

    #[RunInSeparateProcess]
    public function testAnEditLeavesOutASampleOfAnotherLab(): void
    {
        $manifest = $this->manifest('M-2', self::LAB_A);
        $a = $this->sample('A', self::LAB_A, 'M-2', $manifest);
        $c = $this->sample('C', self::LAB_B);

        $this->edit($manifest, self::LAB_A, [$a, $c]);

        $row = $this->row($c);
        self::assertNull($row['sample_package_id']);
        self::assertSame(self::LAB_B, (int) $row['lab_id']);
        self::assertSame(1, (int) $this->manifestRow($manifest)['number_of_samples']);
    }

    #[RunInSeparateProcess]
    public function testAnEditLeavesASampleOnAnotherManifestWhereItIs(): void
    {
        $manifest = $this->manifest('M-3', self::LAB_A);
        $other = $this->manifest('M-OTHER', self::LAB_A);
        $a = $this->sample('A', self::LAB_A, 'M-3', $manifest);
        $d = $this->sample('D', self::LAB_A, 'M-OTHER', $other);

        $this->edit($manifest, self::LAB_A, [$a, $d]);

        self::assertSame($other, (int) $this->row($d)['sample_package_id']);
    }

    #[RunInSeparateProcess]
    public function testAReceivedManifestCannotBeEdited(): void
    {
        $manifest = $this->manifest('M-4', self::LAB_A, TestRequestsService::MANIFEST_RECEIVED);
        $a = $this->sample('A', self::LAB_A, 'M-4', $manifest);
        $c = $this->sample('C', self::LAB_B);

        $url = $this->edit($manifest, self::LAB_B, [$c]);

        self::assertStringContainsString('view-manifests.php', (string) $url);
        self::assertSame($manifest, (int) $this->row($a)['sample_package_id']);
        self::assertNull($this->row($c)['sample_package_id']);
        self::assertSame(self::LAB_A, (int) $this->manifestRow($manifest)['lab_id']);
    }

    #[RunInSeparateProcess]
    public function testAnEditWithNoSampleOfTheChosenLabChangesNothing(): void
    {
        $manifest = $this->manifest('M-5', self::LAB_A);
        $a = $this->sample('A', self::LAB_A, 'M-5', $manifest);

        $url = $this->edit($manifest, self::LAB_B, [$a]);

        self::assertStringContainsString('edit-manifest.php', (string) $url);
        self::assertSame($manifest, (int) $this->row($a)['sample_package_id']);
        self::assertSame(self::LAB_A, (int) $this->manifestRow($manifest)['lab_id']);
    }

    #[RunInSeparateProcess]
    public function testAddingAManifestTakesOnlyTheLabsSamplesAndMovesNone(): void
    {
        $a = $this->sample('A', self::LAB_A);
        $c = $this->sample('C', self::LAB_B);
        $x = $this->sample('X', self::LAB_A, null, null, CANCELLED);

        $this->drive('/specimen-referral-manifest/add-manifest-helper.php', [
            'module' => 'vl', 'testingLab' => self::LAB_A, 'packageCode' => 'M-NEW',
            'selectedSample' => (new Sqids())->encode([$a, $c, $x]),
        ]);

        $manifest = LegacyAppHarness::db()->rawQueryOne(
            "SELECT * FROM specimen_manifests WHERE manifest_code = 'M-NEW'"
        );
        self::assertNotEmpty($manifest);
        self::assertSame(1, (int) $manifest['number_of_samples']);
        self::assertSame((int) $manifest['manifest_id'], (int) $this->row($a)['sample_package_id']);
        $row = $this->row($c);
        self::assertNull($row['sample_package_id']);
        self::assertSame(self::LAB_B, (int) $row['lab_id'], 'Adding a manifest must not move a sample to its lab.');
        self::assertNull($this->row($x)['sample_package_id']);
    }

    #[RunInSeparateProcess]
    public function testAddingAManifestWithoutALabSavesNothing(): void
    {
        $a = $this->sample('A', self::LAB_A);

        $url = $this->drive('/specimen-referral-manifest/add-manifest-helper.php', [
            'module' => 'vl', 'testingLab' => '', 'packageCode' => 'M-NOLAB',
            'selectedSample' => (new Sqids())->encode([$a]),
        ]);

        self::assertStringContainsString('add-manifest.php', (string) $url);
        self::assertSame(0, (int) LegacyAppHarness::db()->getValue('specimen_manifests', 'COUNT(*)'));
        self::assertNull($this->row($a)['sample_package_id']);
    }

    #[RunInSeparateProcess]
    public function testThePickerKeepsTheManifestsSamplesOutsideTheSearchFilters(): void
    {
        $manifest = $this->manifest('M-6', self::LAB_A);
        $this->sample('OLD', self::LAB_A, 'M-6', $manifest, RECEIVED_AT_CLINIC, '2026-01-05 10:00:00');
        $this->sample('NEW', self::LAB_A, null, null, RECEIVED_AT_CLINIC, '2026-09-05 10:00:00');

        $html = $this->picker($manifest, self::LAB_A, '01-Sep-2026 to 30-Sep-2026');

        self::assertStringContainsString('R-OLD', $this->rightBox($html));
        self::assertStringContainsString('R-NEW', $html);
    }

    #[RunInSeparateProcess]
    public function testThePickerListsOnlyTheNewLabsSamplesAndNamesTheOnesLeaving(): void
    {
        $manifest = $this->manifest('M-7', self::LAB_A);
        $this->sample('A', self::LAB_A, 'M-7', $manifest);
        $this->sample('C', self::LAB_B);

        $html = $this->picker($manifest, self::LAB_B, '');

        self::assertStringContainsString('R-C', $html);
        self::assertStringNotContainsString('R-A -', $html, 'The old lab\'s sample must not be offered.');
        self::assertStringContainsString('Samples from another testing lab will be removed', $html);
        self::assertStringContainsString('<li>R-A</li>', $html);
    }

    #[RunInSeparateProcess]
    public function testASampleWithNoLabYetStaysOnTheManifestAndTakesItsLab(): void
    {
        $manifest = $this->manifest('M-8', self::LAB_A);
        $a = $this->sample('A', self::LAB_A, 'M-8', $manifest);
        $n = $this->sample('N', 0, 'M-8', $manifest);

        $this->edit($manifest, self::LAB_A, [$a, $n]);

        $row = $this->row($n);
        self::assertSame($manifest, (int) $row['sample_package_id']);
        self::assertSame(self::LAB_A, (int) $row['lab_id']);
        self::assertSame(2, (int) $this->manifestRow($manifest)['number_of_samples']);
    }

    #[RunInSeparateProcess]
    public function testThePickerKeepsASampleWithNoLabYetAndDoesNotWarnAboutIt(): void
    {
        $manifest = $this->manifest('M-9', self::LAB_A);
        $this->sample('N', 0, 'M-9', $manifest);

        $html = $this->picker($manifest, self::LAB_A, '');

        self::assertStringContainsString('R-N', $this->rightBox($html));
        self::assertStringNotContainsString('Samples from another testing lab', $html);
    }

    #[RunInSeparateProcess]
    public function testThePickerNamesNoSampleOfAnotherLab(): void
    {
        // Lab B's manifest, seen by an operator of lab A on a LIS. Same facility,
        // so only the lab scope can keep lab B's codes out of the response.
        $manifest = $this->manifest('M-10', self::LAB_B);
        $this->sample('B1', self::LAB_B, 'M-10', $manifest);
        $this->sample('BX', self::LAB_B, 'M-10', $manifest, CANCELLED);

        $html = $this->picker($manifest, self::LAB_A, '', ['type' => 'vluser'], self::LAB_A);

        self::assertStringNotContainsString('S-B1', $html);
        self::assertStringNotContainsString('S-BX', $html);
    }

    #[RunInSeparateProcess]
    public function testAnotherLabsManifestCannotBeEdited(): void
    {
        $manifest = $this->manifest('M-11', self::LAB_B);
        $b = $this->sample('B1', self::LAB_B, 'M-11', $manifest);
        $a = $this->sample('A', self::LAB_A);
        $_SESSION['instance']['type'] = 'vluser';
        $_SESSION['labId'] = self::LAB_A;

        $this->edit($manifest, self::LAB_A, [$a]);

        self::assertSame($manifest, (int) $this->row($b)['sample_package_id']);
        self::assertNull($this->row($a)['sample_package_id']);
        self::assertSame(self::LAB_B, (int) $this->manifestRow($manifest)['lab_id']);
    }

    #[RunInSeparateProcess]
    public function testAddingAManifestTakesNoSampleOfAnotherLab(): void
    {
        $b = $this->sample('B1', self::LAB_B);
        $_SESSION['instance']['type'] = 'vluser';
        $_SESSION['labId'] = self::LAB_A;

        $this->drive('/specimen-referral-manifest/add-manifest-helper.php', [
            'module' => 'vl', 'testingLab' => self::LAB_B, 'packageCode' => 'M-ALIEN',
            'selectedSample' => (new Sqids())->encode([$b]),
        ]);

        self::assertNull($this->row($b)['sample_package_id']);
        self::assertSame(0, (int) LegacyAppHarness::db()->getValue('specimen_manifests', 'COUNT(*)'));
    }

    /** @param list<int> $samples */
    private function edit(int $manifest, int $lab, array $samples): ?string
    {
        return $this->drive('/specimen-referral-manifest/edit-manifest-helper.php', [
            'module' => 'vl', 'packageId' => $manifest, 'testingLab' => $lab,
            'packageCode' => 'ignored', 'reasonForChange' => 'Wrong lab',
            'selectedSample' => (new Sqids())->encode($samples),
        ]);
    }

    /** @param array<string, string> $instance */
    private function picker(
        int $manifest,
        int $lab,
        string $dateRange,
        array $instance = ['type' => 'remoteuser'],
        int $ownLab = 0
    ): string {
        // An STS by default, where the picker shows remote_sample_code.
        $_SESSION['instance'] = $instance;
        if ($ownLab > 0) {
            $_SESSION['labId'] = $ownLab;
        }
        $request = LegacyAppHarness::withPost([
            'module' => 'vl', 'testingLab' => $lab, 'pkgId' => $manifest, 'daterange' => $dateRange,
            'facility' => '', 'sampleType' => '', 'testType' => '',
        ], '/specimen-referral-manifest/get-samples-for-manifest.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        return (string) $handler->handle($request)->getBody();
    }

    private function rightBox(string $html): string
    {
        $start = strpos($html, 'id="search_to"');
        self::assertNotFalse($start);
        return substr($html, $start, (int) strpos($html, '</select>', $start) - $start);
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

    private function manifest(string $code, int $lab, string $status = TestRequestsService::MANIFEST_PENDING): int
    {
        LegacyAppHarness::db()->insert('specimen_manifests', [
            'manifest_code' => $code,
            'manifest_status' => $status,
            'module' => 'vl',
            'lab_id' => $lab,
            'added_by' => '1',
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function sample(
        string $code,
        int $lab,
        ?string $manifestCode = null,
        ?int $manifestId = null,
        int $status = RECEIVED_AT_CLINIC,
        string $collectedOn = '2026-09-01 10:00:00'
    ): int {
        LegacyAppHarness::db()->insert('form_vl', [
            'unique_id' => 'uid-' . $code,
            'sample_code' => 'S-' . $code,
            'remote_sample_code' => 'R-' . $code,
            'remote_sample' => 'yes',
            'facility_id' => 11,
            'lab_id' => $lab > 0 ? $lab : null,
            'sample_package_id' => $manifestId,
            'sample_package_code' => $manifestCode,
            'result_status' => $status,
            'sample_collection_date' => $collectedOn,
            'data_sync' => 1,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /** @return array<string, mixed> */
    private function row(int $id): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM form_vl WHERE vl_sample_id = ?', [$id]);
    }

    /** @return array<string, mixed> */
    private function manifestRow(int $id): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM specimen_manifests WHERE manifest_id = ?', [$id]);
    }
}
