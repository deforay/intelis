<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The Sample Flow endpoint, driven the way the page drives it, against rows
 * seeded at known points in the pipeline.
 *
 * The stage a sample lands in is read from its milestone timestamps, so each
 * seeded row sets exactly the milestones a real sample would have at that
 * point and nothing else; the test then asserts the row is counted once, in
 * that stage, in the age bucket its most recent milestone puts it in.
 *
 * LegacyRequestHandler requires a page with require_once, so a process can
 * drive it once: every test runs in its own process and drives exactly once.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class SampleFlowEndpointTest extends TestCase
{
    private const DATABASE = 'intelis_sample_flow_test';

    private const LAB_ID = 501;
    private const FACILITY_ID = 502;

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

        // system_config and global_config are read by the scoping checks the
        // service applies to every query; empty is what a plain install has.
        $db = LegacyAppHarness::boot(self::DATABASE, [
            'form_eid', 'facility_details', 'r_implementation_partners', 'system_config', 'global_config',
        ]);
        // Superadmin: the endpoint's privilege guard passes, and no lab or
        // facility scoping narrows what it counts.
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db->rawQuery(
            "INSERT INTO facility_details
                (facility_id, facility_name, facility_state, facility_district, facility_type, vlsm_instance_id, status)
             VALUES (" . self::LAB_ID . ", 'Central Lab', 'North', 'Capital', 2, 'test', 'active'),
                    (" . self::FACILITY_ID . ", 'Riverside Clinic', 'North', 'Capital', 1, 'test', 'active')"
        );
        $db->rawQuery(
            "INSERT INTO r_implementation_partners (i_partner_id, i_partner_name) VALUES (7, 'Partner Seven')"
        );

        $this->seedOneOfEverything();
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /**
     * One form_eid row with the given milestones. Every row is collected 100
     * days ago so the collection date never decides the age; the most recent
     * milestone does.
     *
     * @param array<string, mixed> $columns
     */
    private function seed(array $columns): void
    {
        static $sequence = 0;
        $sequence++;

        $row = $columns + [
            'vlsm_instance_id' => 'test',
            'sample_code' => 'S' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'facility_id' => self::FACILITY_ID,
            'sample_collection_date' => self::daysAgo(100),
            'result_status' => 6,
        ];
        $names = [];
        $values = [];
        foreach ($row as $name => $value) {
            $names[] = "`$name`";
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (is_string($value) && str_starts_with($value, 'DATE_SUB(')) {
                $values[] = $value;
            } else {
                $values[] = "'" . LegacyAppHarness::db()->escape((string) $value) . "'";
            }
        }
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO form_eid (" . implode(', ', $names) . ") VALUES (" . implode(', ', $values) . ")"
        );
    }

    private static function daysAgo(int $days): string
    {
        return "DATE_SUB(NOW(), INTERVAL $days DAY)";
    }

    /** @return array<string, mixed> decoded JSON */
    private function drive(array $post): array
    {
        $request = LegacyAppHarness::withPost($post, '/sample-flow/get-sample-flow.php');
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        $body = (string) $handler->handle($request)->getBody();
        $json = json_decode($body, true);
        self::assertIsArray($json, "Endpoint did not return JSON: $body");
        return $json;
    }

    /** @return array<string, mixed> */
    private function breakdown(string $stage, string $groupBy, array $extra = []): array
    {
        return $this->drive($extra + [
            'section' => 'breakdown', 'testType' => 'eid', 'dateRange' => '', 'stage' => $stage, 'groupBy' => $groupBy,
        ]);
    }

    private function seedOneOfEverything(): void
    {
        // At facility: nothing beyond registration, 100 days ago.
        $this->seed([]);
        // In transit: dispatched 20 days ago, never received.
        $this->seed(['sample_dispatched_datetime' => self::daysAgo(20)]);
        // At lab: received 40 days ago, not tested.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_received_at_lab_datetime' => self::daysAgo(40),
        ]);
        // Back on the bench: tested 3 days ago, failed. Status puts it at the lab.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_received_at_lab_datetime' => self::daysAgo(10),
            'sample_tested_datetime' => self::daysAgo(3),
            'result_status' => 5,
        ]);
        // Awaiting approval: tested 5 days ago with a result, not approved.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_received_at_lab_datetime' => self::daysAgo(9),
            'sample_tested_datetime' => self::daysAgo(5),
            'result' => 'negative',
            'result_status' => 8,
        ]);
        // Awaiting release: approved 70 days ago, no delivery recorded.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_received_at_lab_datetime' => self::daysAgo(80),
            'sample_tested_datetime' => self::daysAgo(75),
            'result' => 'negative',
            'result_status' => 7,
            'result_approved_datetime' => self::daysAgo(70),
        ]);
        // Released by e-mail: no print or dispatch, mail sent.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_tested_datetime' => self::daysAgo(12),
            'result' => 'positive',
            'result_status' => 7,
            'result_approved_datetime' => self::daysAgo(11),
            'result_mail_datetime' => self::daysAgo(10),
        ]);
        // Released by the sent-to-source flag alone, no datetime written.
        $this->seed([
            'lab_id' => self::LAB_ID,
            'sample_tested_datetime' => self::daysAgo(12),
            'result' => 'positive',
            'result_status' => 7,
            'result_sent_to_source' => 'sent',
        ]);
        // Exits: rejected by status, rejected by flag alone, expired, lost, cancelled.
        $this->seed(['lab_id' => self::LAB_ID, 'result_status' => 4, 'is_sample_rejected' => 'yes']);
        $this->seed(['is_sample_rejected' => 'yes']);
        $this->seed(['result_status' => 10]);
        $this->seed(['result_status' => 2]);
        $this->seed(['result_status' => 12, 'implementing_partner' => 7]);
    }

    #[RunInSeparateProcess]
    public function testEverySeededSampleLandsInExactlyOneStageWithTheRightAge(): void
    {
        $json = $this->drive(['section' => 'flow', 'testType' => 'eid', 'dateRange' => '']);
        self::assertArrayHasKey('flow', $json, json_encode($json));
        $flow = $json['flow'];

        self::assertSame(1, $flow['atFacility']['total']);
        self::assertSame(1, $flow['atFacility']['b4'], 'collected 100 days ago, nothing since');

        self::assertSame(1, $flow['inTransit']['total']);
        self::assertSame(1, $flow['inTransit']['b2'], 'dispatched 20 days ago');

        self::assertSame(2, $flow['atLab']['total'], 'received-not-tested plus the failed one');
        self::assertSame(1, $flow['atLab']['b3'], 'received 40 days ago');
        self::assertSame(1, $flow['atLab']['b0'], 'failed 3 days ago counts from the failure');

        self::assertSame(1, $flow['awaitingApproval']['total']);
        self::assertSame(1, $flow['awaitingApproval']['b0']);

        self::assertSame(1, $flow['awaitingRelease']['total']);
        self::assertSame(1, $flow['awaitingRelease']['b4'], 'approved 70 days ago, nothing delivered');

        self::assertSame(2, $flow['released']['total'], 'e-mailed, and flagged sent to source');

        self::assertSame(2, $flow['rejected']['total'], 'by status and by flag alone');
        self::assertSame(1, $flow['expired']['total']);
        self::assertSame(1, $flow['lost']['total']);
        self::assertSame(1, $flow['cancelled']['total']);

        $counted = 0;
        foreach ($flow as $counts) {
            $counted += $counts['total'];
        }
        self::assertSame(13, $counted, 'stages and exits together account for every row once');
    }

    #[RunInSeparateProcess]
    public function testTheRegistrationWindowIsAppliedToTheCollectionDate(): void
    {
        // Every row was collected 100 days ago, so a 30-day window sees none.
        $json = $this->drive([
            'section' => 'flow',
            'testType' => 'eid',
            'dateRange' => date('d-M-Y', strtotime('-30 days')) . ' to ' . date('d-M-Y'),
        ]);
        $total = 0;
        foreach ($json['flow'] as $counts) {
            $total += $counts['total'];
        }
        self::assertSame(0, $total);
    }

    #[RunInSeparateProcess]
    public function testTheBreakdownNamesTheLab(): void
    {
        $json = $this->breakdown('atLab', 'lab');
        self::assertCount(1, $json['rows']);
        self::assertSame('Central Lab', $json['rows'][0]['label']);
        self::assertSame(2, $json['rows'][0]['total']);
        self::assertSame(1, $json['rows'][0]['b3']);
        self::assertSame(1, $json['rows'][0]['b0']);
    }

    #[RunInSeparateProcess]
    public function testTheBreakdownKeepsUnassignedSamplesAsARowOfTheirOwn(): void
    {
        $json = $this->breakdown('rejected', 'lab');
        $labels = array_column($json['rows'], 'label');
        sort($labels);
        self::assertSame(['Central Lab', 'Not assigned to a lab'], $labels);
    }

    #[RunInSeparateProcess]
    public function testTheBreakdownByPartnerNamesThePartner(): void
    {
        $json = $this->breakdown('cancelled', 'partner');
        self::assertSame('Partner Seven', $json['rows'][0]['label']);
        self::assertSame(1, $json['rows'][0]['total']);
    }

    #[RunInSeparateProcess]
    public function testTheBreakdownByProvinceReadsTheCollectionFacility(): void
    {
        $json = $this->breakdown('atFacility', 'province');
        self::assertSame('North', $json['rows'][0]['label']);
    }

    #[RunInSeparateProcess]
    public function testTheLabFilterNarrowsEveryStage(): void
    {
        $json = $this->drive(['section' => 'flow', 'testType' => 'eid', 'dateRange' => '', 'labId' => self::LAB_ID]);
        self::assertSame(0, $json['flow']['atFacility']['total'], 'never assigned to that lab');
        self::assertSame(2, $json['flow']['atLab']['total']);
    }

    #[RunInSeparateProcess]
    public function testAnUnknownTestTypeIsRefusedNotQueried(): void
    {
        $json = $this->drive(['section' => 'flow', 'testType' => 'form_eid; DROP TABLE form_eid', 'dateRange' => '']);
        self::assertArrayHasKey('error', $json);
    }

    #[RunInSeparateProcess]
    public function testAnUnknownStageIsRefused(): void
    {
        $json = $this->breakdown('x', 'lab');
        self::assertArrayHasKey('error', $json);
    }

    #[RunInSeparateProcess]
    public function testAnUnknownGroupingIsRefused(): void
    {
        $json = $this->breakdown('atLab', 'facility_id');
        self::assertArrayHasKey('error', $json);
    }

    #[RunInSeparateProcess]
    public function testACallerWithoutThePagePrivilegeIsRefused(): void
    {
        LegacyAppHarness::withSession(['roleId' => 5, 'privileges' => []]);

        $json = $this->drive(['section' => 'flow', 'testType' => 'eid', 'dateRange' => '']);
        self::assertArrayHasKey('error', $json);
        self::assertArrayNotHasKey('flow', $json);
    }
}
