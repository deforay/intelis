<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\TestRun;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use App\Services\CommonService;
use Tests\Support\LegacyAppHarness;
use App\Registries\ContainerRegistry;
use App\HttpHandlers\LegacyRequestHandler;

use const SAMPLE_STATUS\ACCEPTED;

/**
 * Each test's Testing Target Report endpoint is a few lines that set the test
 * type and include app/reports/_testing-target-data.php. This drives one of
 * them through the request handler, to pin the scope it applies and the two
 * answers it gives: the grid's rows and the dashboard's count of labs.
 *
 * One drive per test: the handler uses require_once. Set INTELIS_TEST_DB_HOST/
 * _PORT/_USER/_PASS to run; skipped without them.
 */
final class TestingTargetEndpointTest extends TestCase
{
    private const DATABASE = 'intelis_testing_target_endpoint';
    private const TABLES = [
        'r_sample_status', 'form_eid', 'form_vl', 'testing_labs', 'facility_details', 'system_config', 'global_config',
    ];
    private const ENDPOINT = '/eid/management/getEidMonthlyThresholdReport.php';

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
        require_once ROOT_PATH . '/app/system/version.php';
        LegacyAppHarness::boot(TestRun::database(self::DATABASE), self::TABLES);

        $db = LegacyAppHarness::db();
        // form_vl.result_status is a foreign key.
        $db->insert('r_sample_status', ['status_id' => ACCEPTED, 'status_name' => 'Accepted', 'status' => 'active']);
        foreach ([[10, 'Lab A'], [30, 'Lab C'], [101, 'Clinic 1'], [102, 'Clinic 2']] as [$id, $name]) {
            $db->insert('facility_details', ['facility_id' => $id, 'facility_name' => $name, 'status' => 'active']);
        }
        $db->insert('testing_labs', ['test_type' => 'eid', 'facility_id' => 10, 'monthly_target' => '2']);
        $db->insert('testing_labs', ['test_type' => 'eid', 'facility_id' => 30, 'monthly_target' => '5']);
        // Lab A tests two in September (target met), one from each clinic.
        foreach ([101, 102] as $facility) {
            $db->insert('form_eid', [
                'lab_id' => 10,
                'facility_id' => $facility,
                'result_status' => ACCEPTED,
                'is_sample_rejected' => 'no',
                'sample_received_at_lab_datetime' => '2026-09-02 09:00:00',
                'sample_tested_datetime' => '2026-09-03 09:00:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
        $_SESSION = [];
    }

    /** @return array<string, mixed> */
    private function drive(array $post): array
    {
        return $this->driveAt(self::ENDPOINT, $post);
    }

    /** @return array<string, mixed> */
    private function driveAt(string $endpoint, array $post): array
    {
        $request = LegacyAppHarness::withPost($post, $endpoint);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        ob_start();
        try {
            $response = $handler->handle($request);
        } finally {
            $printed = (string) ob_get_clean();
        }
        $body = $printed . $response->getBody();
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<string> "lab|month|tested|target|met" per row */
    private static function cells(array $answer): array
    {
        return array_map(
            static fn(array $r): string => implode('|', [$r[0], $r[1], $r[4], $r[5], $r[6]]),
            $answer['aaData']
        );
    }

    #[RunInSeparateProcess]
    public function testTheStsGridHasOneRowPerLabMonthWithZeroFilledTargetLabs(): void
    {
        LegacyAppHarness::withSession(['roleId' => 1, 'instance' => ['type' => 'remoteuser']]);

        $answer = $this->drive(['dateRange' => '01-Sep-2026 to 30-Sep-2026', 'targetType' => '3']);

        $this->assertSame(['Lab A|Sep-2026|2|2|Yes', 'Lab C|Sep-2026|0|5|No'], self::cells($answer));
    }

    #[RunInSeparateProcess]
    public function testAFacilityUserSeesOnlyTheirSamplesAndNoZeroFilledLabs(): void
    {
        LegacyAppHarness::withSession([
            'roleId' => 1,
            'instance' => ['type' => 'remoteuser'],
            'facilityMap' => '101',
        ]);

        $answer = $this->drive(['dateRange' => '01-Sep-2026 to 30-Sep-2026', 'targetType' => '3']);

        $this->assertSame(['Lab A|Sep-2026|1|2|No'], self::cells($answer));
    }

    #[RunInSeparateProcess]
    public function testALabInstallSeesItsOwnLabOnly(): void
    {
        LegacyAppHarness::withSession(['roleId' => 1, 'instance' => ['type' => 'vluser'], 'labId' => 30]);

        $answer = $this->drive(['dateRange' => '01-Sep-2026 to 30-Sep-2026', 'targetType' => '3']);

        $this->assertSame(['Lab C|Sep-2026|0|5|No'], self::cells($answer));
    }

    #[RunInSeparateProcess]
    public function testTheDashboardGetsTheNumberOfLabsBelowTarget(): void
    {
        LegacyAppHarness::withSession(['roleId' => 1, 'instance' => ['type' => 'remoteuser']]);

        // September and October are complete by now. Lab A misses October,
        // Lab C misses both: two labs.
        $answer = $this->drive(['labsBelowTarget' => 'yes', 'sampleTestDate' => '01-Sep-2026 to 31-Oct-2026']);

        $this->assertSame(['labsBelowTarget' => 2], $answer);
    }

    #[RunInSeparateProcess]
    public function testAFacilityUsersDashboardCountLeavesOutLabsTheySentNothingTo(): void
    {
        LegacyAppHarness::withSession([
            'roleId' => 1,
            'instance' => ['type' => 'remoteuser'],
            'facilityMap' => '101',
        ]);

        // Lab A tested one of Clinic 1's samples in September, short of 2. Lab C
        // had nothing from Clinic 1, so this user is not told about it.
        $answer = $this->drive(['labsBelowTarget' => 'yes', 'sampleTestDate' => '01-Sep-2026 to 30-Sep-2026']);

        $this->assertSame(['labsBelowTarget' => 1], $answer);
    }

    #[RunInSeparateProcess]
    public function testTheSearchBoxNarrowsTheRowsBeforeTheyAreCounted(): void
    {
        LegacyAppHarness::withSession(['roleId' => 1, 'instance' => ['type' => 'remoteuser']]);

        $answer = $this->drive([
            'dateRange' => '01-Sep-2026 to 30-Sep-2026',
            'targetType' => '3',
            'sSearch' => 'lab c',
        ]);

        $this->assertSame(['Lab C|Sep-2026|0|5|No'], self::cells($answer));
        $this->assertSame(1, $answer['iTotalDisplayRecords']);
    }

    #[RunInSeparateProcess]
    public function testASearchWithAnAmpersandFindsTheLab(): void
    {
        LegacyAppHarness::db()->rawQuery(
            "UPDATE facility_details SET facility_name = 'Lab C & Annex' WHERE facility_id = 30"
        );
        LegacyAppHarness::withSession(['roleId' => 1, 'instance' => ['type' => 'remoteuser']]);

        // A served request's sanitizer turns "c & annex" into "c &amp; annex".
        // The test harness does not, so the search goes in as the endpoint gets it.
        $answer = $this->drive([
            'dateRange' => '01-Sep-2026 to 30-Sep-2026',
            'targetType' => '3',
            'sSearch' => 'c &amp; annex',
        ]);

        $this->assertSame(['Lab C &amp; Annex|Sep-2026|0|5|No'], self::cells($answer));
    }

    #[RunInSeparateProcess]
    public function testARoleWithoutTheReportIsRefused(): void
    {
        LegacyAppHarness::withSession([
            'roleId' => 4,
            'instance' => ['type' => 'remoteuser'],
            'privileges' => ['/eid/requests/eid-requests.php' => true],
        ]);

        $request = LegacyAppHarness::withPost(['labsBelowTarget' => 'yes'], self::ENDPOINT);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $refused = null;
        ob_start();
        try {
            $response = $handler->handle($request);
            $refused = $response->getStatusCode();
        } catch (\Throwable $e) {
            $refused = $e->getCode();
        } finally {
            $printed = (string) ob_get_clean();
        }

        $this->assertSame(403, $refused);
        $this->assertStringNotContainsString('labsBelowTarget', $printed);
    }

    #[RunInSeparateProcess]
    public function testTheSuppressedEndpointShowsSuppressedResultsAgainstTheSuppressedTarget(): void
    {
        $db = LegacyAppHarness::db();
        $db->rawQuery("UPDATE testing_labs SET test_type = 'vl', suppressed_monthly_target = '1'");
        $db->insert('form_vl', [
            'lab_id' => 10,
            'facility_id' => 101,
            'result_status' => ACCEPTED,
            'is_sample_rejected' => 'no',
            'vl_result_category' => 'suppressed',
            'sample_received_at_lab_datetime' => '2026-09-02 09:00:00',
            'sample_tested_datetime' => '2026-09-03 09:00:00',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1, 'instance' => ['type' => 'remoteuser']]);

        $answer = $this->driveAt('/vl/program-management/getSuppressedTargetReport.php', [
            'dateRange' => '01-Sep-2026 to 30-Sep-2026',
            'targetType' => '3',
        ]);

        // Testing Lab, Month, Tested, Suppressed, Suppressed Monthly Target, Target Met.
        $this->assertSame(
            [['Lab A', 'Sep-2026', 1, 1, 1, 'Yes'], ['Lab C', 'Sep-2026', 0, 0, 1, 'No']],
            $answer['aaData']
        );
    }

    #[RunInSeparateProcess]
    public function testEveryExportWritesAFileOfItsOwn(): void
    {
        LegacyAppHarness::db()->rawQuery("UPDATE testing_labs SET test_type = 'vl', suppressed_monthly_target = '1'");
        LegacyAppHarness::withSession(['roleId' => 1, 'instance' => ['type' => 'remoteuser']]);
        $before = glob(TEMP_PATH . '/InteLIS-VL-*-Target-Report-*.xlsx') ?: [];

        $exports = [
            '/vl/program-management/vlTestingTargetInExcel.php',
            '/vl/program-management/vlSuppressedTargetInExcel.php',
        ];
        foreach ($exports as $export) {
            $request = LegacyAppHarness::withPost(['dateRange' => '01-Sep-2026 to 30-Sep-2026'], $export);
            $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
            ob_start();
            try {
                $handler->handle($request);
            } finally {
                ob_end_clean();
            }
        }

        // The same report again, at once. The handler requires each script once,
        // so this runs the shared export directly, as the wrapper would.
        (static function (): void {
            $testType = 'vl';
            $targetKind = 'suppressed';
            $reportPage = '/vl/program-management/vlSuppressedTargetReport.php';
            $reportTitle = 'VL Suppression';
            ob_start();
            try {
                require APPLICATION_PATH . '/reports/_testing-target-export.php';
            } finally {
                ob_end_clean();
            }
        })();

        $written = array_values(array_diff(glob(TEMP_PATH . '/InteLIS-VL-*-Target-Report-*.xlsx') ?: [], $before));
        foreach ($written as $file) {
            unlink($file);
        }
        $this->assertCount(3, $written);
        $this->assertCount(2, preg_grep('/-Suppression-Target-Report-/', $written));
        // The two runs may fall in different seconds, so the count alone cannot
        // show the random part. Every name must carry it.
        $stampAndRandom = '/-\d{2}-[A-Za-z]{3}-\d{4}-\d{2}-\d{2}-\d{2}-[0-9a-f]{8}\.xlsx$/';
        foreach ($written as $file) {
            $this->assertMatchesRegularExpression($stampAndRandom, $file);
        }
    }
}
