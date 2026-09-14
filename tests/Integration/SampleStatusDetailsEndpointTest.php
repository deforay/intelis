<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Services\SampleStatusDetailsService;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The drilldown behind a VL Sample Status Report slice, driven the way the
 * page drives it.
 *
 * The listing has to hold exactly the samples the slice counted, so the
 * filters the pie is drawn with are carried across and applied the same way;
 * and it has to show the columns that matter for that status.
 *
 * Every test runs in its own process and drives the handler once.
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SampleStatusDetailsEndpointTest extends TestCase
{
    private const DATABASE = 'intelis_sample_status_details_test';

    private const LAB_ID = 601;
    private const OTHER_LAB_ID = 602;
    private const FACILITY_ID = 603;

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

        $db = LegacyAppHarness::boot(self::DATABASE, [
            // Lookup tables first: form_vl holds foreign keys to some of them.
            'r_sample_status', 'facility_details', 'r_vl_sample_type', 'batch_details',
            'r_vl_sample_rejection_reasons', 'r_test_failure_reasons', 'r_vl_test_failure_reasons',
            'roles', 'user_details',
            'form_vl',
            'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_state, facility_district, facility_type, vlsm_instance_id, status)
             VALUES (" . self::LAB_ID . ", 'Central Lab', 'North', 'Capital', 2, 'test', 'active'),
                    (" . self::OTHER_LAB_ID . ", 'Other Lab', 'South', 'Coast', 2, 'test', 'active'),
                    (" . self::FACILITY_ID . ", 'Riverside Clinic', 'North', 'Capital', 1, 'test', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name, status) VALUES
                (4, 'Rejected', 'active'), (6, 'Sample Registered at Testing Lab', 'active'),
                (7, 'Accepted', 'active'), (8, 'Awaiting Approval', 'active'), (12, 'Cancelled', 'active')"
        );
        $db->rawQuery("INSERT INTO r_vl_sample_type (sample_id, sample_name, status) VALUES (1, 'Plasma', 'active')");
        $db->rawQuery(
            "INSERT INTO r_vl_sample_rejection_reasons
                (rejection_reason_id, rejection_reason_name, rejection_type, rejection_reason_status)
             VALUES (9, 'Haemolysed', 'general', 'active')"
        );
        $db->rawQuery("INSERT INTO roles (role_id, role_name, role_code, status) VALUES (1, 'Admin', 'AD', 'active')");
        $db->rawQuery(
            "INSERT INTO user_details (user_id, user_name, login_id, password, role_id, status)
             VALUES ('u-approver', 'Ada Approver', 'ada', 'x', 1, 'active')"
        );
        $db->rawQuery(
            "INSERT INTO batch_details (batch_id, batch_code, test_type, batch_status)
             VALUES (1, 'B-001', 'vl', 'completed')"
        );

        // Two waiting at Central Lab, received 10 and 3 days ago; one at the other lab.
        $this->seed(['result_status' => 6, 'lab_id' => self::LAB_ID, 'sample_batch_id' => 1,
            'sample_received_at_lab_datetime' => self::daysAgo(10)]);
        $this->seed(['result_status' => 6, 'lab_id' => self::LAB_ID,
            'sample_received_at_lab_datetime' => self::daysAgo(3)]);
        $this->seed(['result_status' => 6, 'lab_id' => self::OTHER_LAB_ID,
            'sample_received_at_lab_datetime' => self::daysAgo(1)]);
        // A recency sample in the same state, which the VL listing must not show.
        $this->seed(['result_status' => 6, 'lab_id' => self::LAB_ID, 'reason_for_vl_testing' => 9999,
            'sample_received_at_lab_datetime' => self::daysAgo(2)]);
        // Rejected, with a reason, and one whose reason id this install never had.
        $this->seed(['result_status' => 4, 'lab_id' => self::LAB_ID, 'is_sample_rejected' => 'yes',
            'reason_for_sample_rejection' => 9, 'rejection_on' => date('Y-m-d'),
            'sample_rejection_facility' => self::LAB_ID]);
        $this->seed(['result_status' => 4, 'lab_id' => self::LAB_ID, 'is_sample_rejected' => 'yes',
            'reason_for_sample_rejection' => 4040]);
        // Accepted and approved.
        $this->seed(['result_status' => 7, 'lab_id' => self::LAB_ID, 'result' => '<20',
            'sample_tested_datetime' => self::daysAgo(20), 'result_approved_by' => 'u-approver',
            'result_approved_datetime' => self::daysAgo(19)]);
        // Cancelled: counts nowhere, so no slice and no listing.
        $this->seed(['result_status' => 12, 'lab_id' => self::LAB_ID]);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** @param array<string, mixed> $columns */
    private function seed(array $columns): void
    {
        static $sequence = 0;
        $sequence++;

        $row = $columns + [
            'unique_id' => 'uid-' . $sequence,
            'vlsm_instance_id' => 'test',
            'vlsm_country_id' => 1,
            'sample_code' => 'VL' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
            'facility_id' => self::FACILITY_ID,
            'specimen_type' => 1,
            'sample_collection_date' => self::daysAgo(30),
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            if (is_string($value) && str_starts_with($value, 'DATE_SUB(')) {
                $values[] = $value;
            } else {
                $values[] = "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
            }
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_vl (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
        );
    }

    private static function daysAgo(int $days): string
    {
        return "DATE_SUB(NOW(), INTERVAL $days DAY)";
    }

    /** @return array<string, mixed> */
    private function drive(array $post): array
    {
        $request = LegacyAppHarness::withPost($post + [
            'testType' => 'vl', 'draw' => 1, 'start' => 0, 'length' => 25,
        ], '/reports/get-sample-status-details.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();
        $json = json_decode($body, true);
        self::assertIsArray($json, "Endpoint did not return JSON: $body");
        return $json;
    }

    /** @return array<string, string> one listing row keyed by column */
    private static function keyed(int $status, array $row): array
    {
        return array_combine(array_keys(SampleStatusDetailsService::columns($status)), $row);
    }

    #[RunInSeparateProcess]
    public function testTheWaitingListShowsOnlyVlSamplesInThatStatusLongestWaitingFirst(): void
    {
        $json = $this->drive(['status' => 6]);
        self::assertSame(3, $json['recordsTotal'], json_encode($json));
        self::assertSame(1, $json['draw']);

        $first = self::keyed(6, $json['data'][0]);
        self::assertSame('10', $first['daysWaiting'], 'received 10 days ago, so it waits longest');
        self::assertSame('B-001', $first['batchCode']);
        self::assertSame('Central Lab', $first['lab']);
        self::assertSame('Plasma', $first['sampleType']);
        self::assertArrayNotHasKey('result', $first, 'nothing has been tested yet');
    }

    #[RunInSeparateProcess]
    public function testTheLabFilterCarriesAcrossToTheListing(): void
    {
        $json = $this->drive(['status' => 6, 'labName' => self::LAB_ID]);
        self::assertSame(2, $json['recordsTotal'], 'the other lab and the recency sample are left out');
    }

    #[RunInSeparateProcess]
    public function testTheBatchFilterCarriesAcrossToTheListing(): void
    {
        $json = $this->drive(['status' => 6, 'batchCode' => 'B-001']);
        self::assertSame(1, $json['recordsTotal']);
    }

    #[RunInSeparateProcess]
    public function testTheRecencyListingReadsOnlyRecencySamples(): void
    {
        $json = $this->drive(['status' => 6, 'testType' => 'recency']);
        self::assertSame(1, $json['recordsTotal']);
    }

    #[RunInSeparateProcess]
    public function testRejectedSamplesShowTheirReasonAndNameAnUnknownOne(): void
    {
        $json = $this->drive(['status' => 4, 'order' => [['column' => 0, 'dir' => 'asc']]]);
        self::assertSame(2, $json['recordsTotal']);

        $rows = array_map(static fn(array $row): array => self::keyed(4, $row), $json['data']);
        $reasons = array_column($rows, 'rejectionReason');
        sort($reasons);
        self::assertSame(['Haemolysed', 'Unknown or unreported reason'], $reasons);
        self::assertContains('Central Lab', array_column($rows, 'rejectedBy'));
    }

    #[RunInSeparateProcess]
    public function testAcceptedSamplesShowWhoApprovedAndTheTurnaround(): void
    {
        $json = $this->drive(['status' => 7]);
        $row = self::keyed(7, $json['data'][0]);
        self::assertSame('Ada Approver', $row['approvedBy']);
        self::assertSame('&lt;20', $row['result'], 'values are escaped for the grid');
        self::assertSame('10', $row['turnaround'], 'collected 30 days ago, tested 20 days ago');
    }

    #[RunInSeparateProcess]
    public function testCancelledSamplesAreNeverListed(): void
    {
        $json = $this->drive(['status' => 12]);
        self::assertSame(0, $json['recordsTotal']);
    }

    #[RunInSeparateProcess]
    public function testSearchNarrowsTheFilteredCountButNotTheTotal(): void
    {
        $json = $this->drive(['status' => 6, 'search' => ['value' => 'VL00002']]);
        self::assertSame(3, $json['recordsTotal']);
        self::assertSame(1, $json['recordsFiltered']);
    }

    #[RunInSeparateProcess]
    public function testAnUnknownTestTypeIsRefused(): void
    {
        $json = $this->drive(['status' => 6, 'testType' => 'form_vl; DROP TABLE form_vl']);
        self::assertArrayHasKey('error', $json);
    }

    #[RunInSeparateProcess]
    public function testAnUnknownStatusIsRefused(): void
    {
        $json = $this->drive(['status' => 99]);
        self::assertArrayHasKey('error', $json);
    }

    #[RunInSeparateProcess]
    public function testACallerWithoutVlAccessIsRefused(): void
    {
        LegacyAppHarness::withSession(['roleId' => 5, 'privileges' => [], 'modules' => ['eid' => 'eid']]);

        $json = $this->drive(['status' => 6]);
        self::assertArrayHasKey('error', $json);
        self::assertArrayNotHasKey('data', $json);
    }

    #[RunInSeparateProcess]
    public function testThePieSliceCountsWhatItsDrilldownLinkLists(): void
    {
        $request = LegacyAppHarness::withPost(
            ['type' => 'vl', 'labName' => self::LAB_ID, 'sampleCollectionDate' => ''],
            '/vl/program-management/getSampleStatus.php'
        );
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();

        // Each slice is printed as a y count followed by its colour and link.
        self::assertSame(
            1,
            preg_match('/y:\s*(\d+),\s*color:\s*\'#[0-9a-f]{6}\',\s*url:\s*"([^"]*status=6[^"]*)"/', $body, $slice),
            $body
        );
        self::assertSame('2', $slice[1], 'Central Lab holds two VL samples waiting; the recency one is not VL');
        $link = json_decode('"' . $slice[2] . '"');
        self::assertStringStartsWith('/reports/sample-status-details.php?testType=vl&status=6&', $link);
        self::assertStringContainsString('labName=' . self::LAB_ID, $link, 'the lab filter travels with the link');
        self::assertStringNotContainsString('status=12', $body, 'cancelled samples get no slice');
    }

    #[RunInSeparateProcess]
    public function testTheDrilldownLinkPutsTheTestTypeFirstForTheAccessCheck(): void
    {
        $url = SampleStatusDetailsService::pageUrl('vl', 4, [
            'sampleCollectionDate' => '01-Jan-2026 to 31-Jan-2026', 'labName' => '7', 'ignored' => 'x',
        ]);
        self::assertStringStartsWith('/reports/sample-status-details.php?testType=vl&status=4&', $url);
        self::assertStringContainsString('labName=7', $url);
        self::assertStringNotContainsString('ignored', $url);
    }
}
