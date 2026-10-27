<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\MiscUtility;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\EXPIRED;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;

/**
 * The Reject button on the VL Requests and Enter VL Result grids, driven against
 * a real table.
 *
 * The dialog only rejects a sample with no result yet. A sample with a result is
 * rejected from its result screen, which records the result change. Each refusal
 * here leaves the row exactly as it was.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class VlRejectSampleTest extends TestCase
{
    private string $database = '';

    /** form_vl and the tables its foreign keys name */
    private const TABLES = [
        's_vlsm_instance', 'system_config', 'global_config',
        'roles', 'user_details', 'facility_details', 'geographical_divisions',
        'batch_details', 'r_sample_status', 'r_funding_sources', 'r_implementation_partners',
        'form_vl', 'r_vl_sample_rejection_reasons', 'r_vl_sample_type', 'r_vl_test_reasons',
        'activity_log',
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

        // Named per process, so a second run against the same server cannot drop it
        $this->database = 'intelis_vl_reject_sample_test_' . getmypid();
        LegacyAppHarness::boot($this->database, self::TABLES);
        LegacyAppHarness::withSession(['roleId' => 1]);

        $db = LegacyAppHarness::db();
        $statuses = [
            ACCEPTED => 'Accepted', REJECTED => 'Rejected', EXPIRED => 'Expired', RECEIVED_AT_TESTING_LAB => 'Received',
        ];
        foreach ($statuses as $id => $name) {
            $db->insert('r_sample_status', ['status_id' => $id, 'status_name' => $name, 'status' => 'active']);
        }
        foreach (['user-2' => 5, 'user-9' => 9] as $userId => $labId) {
            $db->insert('user_details', [
                'user_id' => $userId,
                'user_name' => "Lab $labId user",
                'status' => 'active',
                'testing_lab_id' => $labId,
            ]);
        }
        $db->insert('r_vl_sample_rejection_reasons', [
            'rejection_reason_id' => 1,
            'rejection_reason_name' => 'Hemolyzed',
            'rejection_type' => 'general',
            'rejection_reason_status' => 'active',
        ]);
        $db->insert('r_vl_sample_rejection_reasons', [
            'rejection_reason_id' => 2,
            'rejection_reason_name' => 'Retired reason',
            'rejection_type' => 'general',
            'rejection_reason_status' => 'inactive',
        ]);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /**
     * @param array<string, mixed> $columns
     * @return int the new row's vl_sample_id
     */
    private function seedSample(array $columns = []): int
    {
        $db = LegacyAppHarness::db();
        $db->insert('form_vl', $columns + [
            'sample_code' => 'VL0001',
            'sample_collection_date' => '2026-10-01 09:00:00',
            'result' => null,
            'result_status' => RECEIVED_AT_TESTING_LAB,
            'is_sample_rejected' => 'no',
            'data_sync' => 1,
        ]);

        return (int) $db->getInsertId();
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed> the endpoint's decoded reply
     */
    private function reject(int $id, array $post = []): array
    {
        $request = LegacyAppHarness::withPost($post + [
            'id' => MiscUtility::sqid($id),
            'rejectionReason' => '1',
            'rejectionDate' => '2026-10-02',
            'approvedBy' => 'user-2',
            'approvedOnDateTime' => '2026-10-03 10:30',
            'labComments' => 'Sample arrived hemolyzed',
        ], '/vl/requests/reject-sample.php');
        $handler = new LegacyRequestHandler(
            LegacyAppHarness::db(),
            ContainerRegistry::get(CommonService::class)
        );

        return json_decode((string) $handler->handle($request)->getBody(), true) ?? [];
    }

    /** @return array<string, mixed> */
    private function row(int $id): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM form_vl WHERE vl_sample_id = ?', [$id]);
    }

    #[RunInSeparateProcess]
    public function testRejectsASampleWithNoResult(): void
    {
        $id = $this->seedSample();

        $reply = $this->reject($id);

        self::assertSame('success', $reply['status'] ?? null, (string) ($reply['message'] ?? ''));
        $row = $this->row($id);
        self::assertSame(REJECTED, (int) $row['result_status']);
        self::assertSame('yes', $row['is_sample_rejected']);
        self::assertSame(1, (int) $row['reason_for_sample_rejection']);
        self::assertSame('2026-10-02', $row['rejection_on']);
        self::assertSame('user-2', $row['result_approved_by']);
        self::assertSame('2026-10-03 10:30:00', $row['result_approved_datetime']);
        self::assertSame('Sample arrived hemolyzed', $row['lab_tech_comments']);
        self::assertSame('rejected', $row['vl_result_category']);
        self::assertSame('yes', $row['result_modified']);
        // Flagged for the next sync, or the rejection never leaves this install
        self::assertSame(0, (int) $row['data_sync']);
    }

    #[RunInSeparateProcess]
    public function testClearsTheTestDetailsOfTheRejectedSample(): void
    {
        // Every column the rejection clears holds a value first. The result stays
        // empty, or the sample would not be eligible.
        $testDetails = [
            'result_value_log' => '3.08',
            'result_value_absolute' => '1200',
            'result_value_absolute_decimal' => '1200.00',
            'result_value_text' => 'Detected',
            'result_value_hiv_detection' => 'HIV-1 Detected',
            'reason_for_failure' => '3',
            'sample_tested_datetime' => '2026-10-02 08:00:00',
            'tested_by' => 'user-2',
            'vl_test_platform' => 'Abbott',
            'instrument_id' => 'instrument-1',
            'result_reviewed_by' => 'user-2',
            'result_reviewed_datetime' => '2026-10-02 09:00:00',
            'result_dispatched_datetime' => '2026-10-02 10:00:00',
        ];
        $id = $this->seedSample($testDetails);

        $reply = $this->reject($id);

        self::assertSame('success', $reply['status'] ?? null, (string) ($reply['message'] ?? ''));
        $row = $this->row($id);
        self::assertNull($row['result']);
        foreach (array_keys($testDetails) as $column) {
            self::assertNull($row[$column], $column);
        }
    }

    /** A LIS session acting as lab 5 */
    private function actAsLabFive(): void
    {
        LegacyAppHarness::withSession(['roleId' => 1, 'labId' => 5, 'instance' => ['type' => 'vluser']]);
    }

    #[RunInSeparateProcess]
    public function testRejectsASampleOfTheOperatorsOwnLab(): void
    {
        $this->actAsLabFive();
        $id = $this->seedSample(['lab_id' => 5]);

        $reply = $this->reject($id);

        self::assertSame('success', $reply['status'] ?? null, (string) ($reply['message'] ?? ''));
        self::assertSame(REJECTED, (int) $this->row($id)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testRefusesAnotherLabsSample(): void
    {
        $this->actAsLabFive();
        $id = $this->seedSample(['lab_id' => 9]);

        $reply = $this->reject($id);

        self::assertSame('error', $reply['status'] ?? null);
        $row = $this->row($id);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
        self::assertSame(1, (int) $row['data_sync']);
    }

    #[RunInSeparateProcess]
    public function testRefusesAnApproverFromAnotherLab(): void
    {
        $this->actAsLabFive();
        $id = $this->seedSample(['lab_id' => 5]);

        $reply = $this->reject($id, ['approvedBy' => 'user-9']);

        self::assertSame('error', $reply['status'] ?? null);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $this->row($id)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testRefusesALockedSampleForANonAdmin(): void
    {
        LegacyAppHarness::withSession(['roleId' => 2, 'privileges' => ['/vl/requests/editVlRequest.php' => 'yes']]);
        $id = $this->seedSample(['locked' => 'yes']);

        $reply = $this->reject($id);

        self::assertSame('error', $reply['status'] ?? null);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $this->row($id)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testAnAdminRejectsALockedSample(): void
    {
        $id = $this->seedSample(['locked' => 'yes']);

        $reply = $this->reject($id);

        self::assertSame('success', $reply['status'] ?? null, (string) ($reply['message'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function testABlankCommentKeepsTheExistingComment(): void
    {
        $id = $this->seedSample(['lab_tech_comments' => 'Earlier note']);

        $this->reject($id, ['labComments' => '']);

        self::assertSame('Earlier note', $this->row($id)['lab_tech_comments']);
    }

    #[RunInSeparateProcess]
    public function testRefusesASampleThatHasAResult(): void
    {
        $id = $this->seedSample(['result' => '1200', 'result_status' => ACCEPTED]);

        $reply = $this->reject($id);

        self::assertSame('error', $reply['status'] ?? null);
        $row = $this->row($id);
        self::assertSame(ACCEPTED, (int) $row['result_status']);
        self::assertSame('1200', $row['result']);
        self::assertSame(1, (int) $row['data_sync']);
    }

    #[RunInSeparateProcess]
    public function testRefusesASampleAlreadyRejected(): void
    {
        $id = $this->seedSample([
            'result_status' => REJECTED,
            'is_sample_rejected' => 'yes',
            'reason_for_sample_rejection' => '1',
            'rejection_on' => '2026-10-01',
        ]);

        $reply = $this->reject($id, ['rejectionDate' => '2026-10-05']);

        self::assertSame('error', $reply['status'] ?? null);
        self::assertSame('2026-10-01', $this->row($id)['rejection_on']);
    }

    #[RunInSeparateProcess]
    public function testRefusesAnExpiredSample(): void
    {
        $id = $this->seedSample(['result_status' => EXPIRED]);

        $reply = $this->reject($id);

        self::assertSame('error', $reply['status'] ?? null);
        self::assertSame(EXPIRED, (int) $this->row($id)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testRefusesACollectionSite(): void
    {
        LegacyAppHarness::withSession(['roleId' => 1, 'accessType' => 'collection-site']);
        $id = $this->seedSample();

        $reply = $this->reject($id);

        self::assertSame('error', $reply['status'] ?? null);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $this->row($id)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testRefusesAUserWithoutTheEditOrResultPrivilege(): void
    {
        LegacyAppHarness::withSession(['roleId' => 2, 'privileges' => []]);
        $id = $this->seedSample();

        $reply = $this->reject($id);

        self::assertSame('error', $reply['status'] ?? null);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $this->row($id)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testRefusesAnInactiveRejectionReason(): void
    {
        $id = $this->seedSample();

        $reply = $this->reject($id, ['rejectionReason' => '2']);

        self::assertSame('error', $reply['status'] ?? null);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $this->row($id)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testRefusesARejectionDateBeforeCollection(): void
    {
        $id = $this->seedSample();

        $reply = $this->reject($id, ['rejectionDate' => '2026-09-30']);

        self::assertSame('error', $reply['status'] ?? null);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $this->row($id)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testRefusesAnApprovalBeforeTheRejectionDate(): void
    {
        $id = $this->seedSample();

        $reply = $this->reject($id, ['approvedOnDateTime' => '2026-10-01 10:00']);

        self::assertSame('error', $reply['status'] ?? null);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $this->row($id)['result_status']);
    }
}
