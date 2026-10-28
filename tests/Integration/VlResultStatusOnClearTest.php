<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\RedirectException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use mysqli;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Tests\Support\TestRun;
use Throwable;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;

/**
 * The VL request edit and result pages, driven as a browser posts them.
 *
 * Undoing a rejection with no result left the sample Rejected while is_sample_rejected
 * said no, and clearing a result left it Accepted with its approval standing over an
 * empty result. A result could also be saved with no testing platform.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class VlResultStatusOnClearTest extends TestCase
{
    private const DATABASE = 'intelis_vl_result_status_on_clear';

    private const EDIT = '/vl/requests/editVlRequestHelper.php';

    private const RESULT = '/vl/results/updateVlTestResultHelper.php';

    private const ADD = '/vl/requests/addVlRequestHelper.php';

    private const PLATFORM = 'Abbott##20##10000000##1';

    private const REFUSED = 'Nothing was saved. Please choose the testing platform for the result.';

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    private static function database(): string
    {
        return TestRun::database(self::DATABASE);
    }

    protected function setUp(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        $db = LegacyAppHarness::boot(self::database(), [
            'r_sample_status', 'form_vl', 'facility_details', 'geographical_divisions', 'r_vl_test_reasons',
            'r_vl_sample_rejection_reasons', 'r_vl_art_regimen', 'lab_storage', 'activity_log', 'audit_log',
            'system_config', 'global_config', 's_vlsm_instance', 'log_result_updates', 'test_result_attempts',
            'instruments',
        ]);
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name) VALUES
                (4, 'Rejected'), (6, 'Received at lab'), (7, 'Accepted'), (8, 'Awaiting Approval'),
                (9, 'Received at clinic')"
        );
        LegacyAppHarness::withSession();
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** Runs a helper; returns where it redirected to. */
    private function drive(string $path, array $post): ?string
    {
        $request = LegacyAppHarness::withPost($post, $path);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        try {
            $handler->handle($request);
        } catch (Throwable $e) {
            for ($cause = $e; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
                if ($cause instanceof RedirectException) {
                    return $cause->getMessage();
                }
            }
            throw $e;
        }
        return null;
    }

    /** @param array<string, mixed> $row */
    private function seed(array $row): int
    {
        LegacyAppHarness::db()->insert('form_vl', $row + [
            'unique_id' => 'U-' . bin2hex(random_bytes(4)),
            'vlsm_instance_id' => 'test',
            'vlsm_country_id' => 1,
            'sample_code' => 'VL-1',
            'sample_code_key' => 1,
            'sample_collection_date' => '2026-09-25 09:00:00',
            'facility_id' => 1,
            'lab_id' => 1,
            'patient_art_no' => 'ART-1',
            'sample_received_at_lab_datetime' => '2026-09-26 10:00:00',
            'data_sync' => 1,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function rejectedSample(): int
    {
        return $this->seed([
            'result_status' => REJECTED,
            'is_sample_rejected' => 'yes',
            'reason_for_sample_rejection' => 1,
            'rejection_on' => '2026-09-27',
            'result_reviewed_by' => 'reviewer',
            'result_reviewed_datetime' => '2026-09-27 11:00:00',
            'result_approved_by' => 'approver',
            'result_approved_datetime' => '2026-09-27 12:00:00',
        ]);
    }

    private function resultedSample(int $status = ACCEPTED): int
    {
        return $this->seed([
            'result_status' => $status,
            'is_sample_rejected' => 'no',
            'vl_test_platform' => 'Abbott',
            'result' => '1200',
            'result_value_absolute' => '1200',
            'result_value_absolute_decimal' => 1200,
            'result_value_log' => 3.08,
            'tested_by' => 'tester',
            'sample_tested_datetime' => '2026-09-27 09:00:00',
            'result_reviewed_by' => 'reviewer',
            'result_reviewed_datetime' => '2026-09-27 11:00:00',
            'result_approved_by' => 'approver',
            'result_approved_datetime' => '2026-09-27 12:00:00',
        ]);
    }

    /**
     * Read back over a connection of its own, so nothing the helper's connection still
     * holds can stand in for what was committed.
     *
     * @return array<string, mixed>
     */
    private function saved(int $id): array
    {
        $mysqli = new mysqli(
            (string) getenv('INTELIS_TEST_DB_HOST'),
            (string) getenv('INTELIS_TEST_DB_USER'),
            (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            self::database(),
            (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
        );
        $statement = $mysqli->prepare(
            'SELECT result, result_status, is_sample_rejected, reason_for_sample_rejection, rejection_on,
                    vl_test_platform, tested_by, sample_tested_datetime, result_reviewed_by,
                    result_reviewed_datetime, result_approved_by, result_approved_datetime, data_sync
               FROM form_vl WHERE vl_sample_id = ?'
        );
        $statement->bind_param('i', $id);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $mysqli->close();
        return $row;
    }

    /** The VL request edit form as the country forms post it, lab section shown. */
    private static function editPost(int $id, array $post): array
    {
        return $post + [
            'vlSampleId' => (string) $id,
            'sampleCode' => 'VL-1',
            'sampleCollectionDate' => '2026-09-25 09:00:00',
            'province' => '',
            'facilityId' => '1',
            'facilityCode' => '',
            'labId' => '1',
            'artNo' => 'ART-1',
            'reasonForVLTesting' => '1',
            'sampleReceivedDate' => '2026-09-26 10:00:00',
        ];
    }

    /** The VL result form as the country forms post it. */
    private static function resultPost(int $id, array $post): array
    {
        return $post + [
            'vlSampleId' => (string) $id,
            'sampleCode' => 'VL-1',
            'labId' => '1',
            'artNo' => 'ART-1',
            'sampleReceivedDate' => '2026-09-26 10:00:00',
            'rejectionDate' => '',
        ];
    }

    private static function assertSignoffCleared(array $row): void
    {
        self::assertNull($row['result_reviewed_by']);
        self::assertNull($row['result_reviewed_datetime']);
        self::assertNull($row['result_approved_by']);
        self::assertNull($row['result_approved_datetime']);
    }

    #[RunInSeparateProcess]
    public function testEditUndoingARejectionWithNoResultIsWaitingAtTheLab(): void
    {
        $id = $this->rejectedSample();

        // No platform chosen: the result box is disabled and not sent. The reviewer and
        // approver of the rejection are still selected in the form.
        $this->drive(self::EDIT, self::editPost($id, [
            'isSampleRejected' => 'no',
            'reviewedBy' => 'reviewer',
            'reviewedOn' => '2026-09-27 11:00:00',
            'approvedBy' => 'approver',
            'approvedOnDateTime' => '2026-09-27 12:00:00',
        ]));

        $row = $this->saved($id);
        self::assertSame('no', $row['is_sample_rejected']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
        self::assertSignoffCleared($row);
    }

    #[RunInSeparateProcess]
    public function testEditUndoingARejectionOnACollectionSiteWithNoReceiptIsAtTheClinic(): void
    {
        LegacyAppHarness::withSession(['accessType' => 'collection-site', 'instance' => ['type' => 'stsmode']]);
        $id = $this->rejectedSample();
        LegacyAppHarness::db()->rawQuery(
            'UPDATE form_vl SET sample_received_at_lab_datetime = NULL WHERE vl_sample_id = ?',
            [$id]
        );

        $this->drive(self::EDIT, self::editPost($id, ['isSampleRejected' => 'no', 'sampleReceivedDate' => '']));

        self::assertSame(RECEIVED_AT_CLINIC, (int) $this->saved($id)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testEditWithoutTheLabSectionLeavesARejectionStanding(): void
    {
        // A collection site's edit form renders no rejection field and no result box
        LegacyAppHarness::withSession(['accessType' => 'collection-site', 'instance' => ['type' => 'stsmode']]);
        $id = $this->rejectedSample();

        $this->drive(self::EDIT, self::editPost($id, []));

        $row = $this->saved($id);
        self::assertSame(REJECTED, (int) $row['result_status']);
        self::assertSame('approver', $row['result_approved_by']);
        // The flag, reason and date it never showed are kept too, so the row stays one rejection
        self::assertSame('yes', $row['is_sample_rejected']);
        self::assertSame(1, (int) $row['reason_for_sample_rejection']);
        self::assertSame('2026-09-27', substr((string) $row['rejection_on'], 0, 10));
    }

    #[RunInSeparateProcess]
    public function testEditUndoingARejectionWithAResultAwaitsApproval(): void
    {
        $id = $this->rejectedSample();

        $this->drive(self::EDIT, self::editPost($id, [
            'isSampleRejected' => 'no',
            'testingPlatform' => self::PLATFORM,
            'vlResult' => '1200',
        ]));

        $row = $this->saved($id);
        self::assertSame(PENDING_APPROVAL, (int) $row['result_status']);
        self::assertSame('1200', $row['result']);
    }

    #[RunInSeparateProcess]
    public function testEditClearingAResultIsWaitingAndDropsTheApproval(): void
    {
        $id = $this->resultedSample();

        $this->drive(self::EDIT, self::editPost($id, [
            'isSampleRejected' => 'no',
            'testingPlatform' => self::PLATFORM,
            'vlResult' => '',
            'vlLog' => '',
            'testedBy' => 'tester',
            'sampleTestingDateAtLab' => '2026-09-27 09:00:00',
            'reviewedBy' => 'reviewer',
            'reviewedOn' => '2026-09-27 11:00:00',
            'approvedBy' => 'approver',
            'approvedOnDateTime' => '2026-09-27 12:00:00',
        ]));

        $row = $this->saved($id);
        self::assertNull($row['result']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
        self::assertSignoffCleared($row);
        // What the form sent about the run stands
        self::assertSame('tester', $row['tested_by']);
        self::assertSame('2026-09-27 09:00:00', $row['sample_tested_datetime']);
    }

    #[RunInSeparateProcess]
    public function testEditClearingThePlatformKeepsTheResult(): void
    {
        $id = $this->resultedSample();

        // With no platform the result box is disabled, so the result is not sent
        $this->drive(self::EDIT, self::editPost($id, ['isSampleRejected' => 'no', 'testingPlatform' => '']));

        $row = $this->saved($id);
        self::assertSame('1200', $row['result']);
        self::assertSame(ACCEPTED, (int) $row['result_status']);
        self::assertSame('approver', $row['result_approved_by']);
    }

    #[RunInSeparateProcess]
    public function testEditRefusesAResultWithoutAPlatform(): void
    {
        $id = $this->resultedSample();

        $redirect = $this->drive(self::EDIT, self::editPost($id, [
            'isSampleRejected' => 'no',
            'testingPlatform' => '',
            'vlResult' => '5000',
            'artNo' => 'ART-CHANGED',
        ]));

        self::assertStringContainsString('/vl/requests/editVlRequest.php?id=', (string) $redirect);
        self::assertSame(self::REFUSED, $_SESSION['alertMsg'] ?? null);
        $row = $this->saved($id);
        self::assertSame('1200', $row['result']);
        self::assertSame('Abbott', $row['vl_test_platform']);
        self::assertSame(ACCEPTED, (int) $row['result_status']);
    }

    #[RunInSeparateProcess]
    public function testEditRejectsWithoutAPlatform(): void
    {
        $id = $this->seed(['result_status' => RECEIVED_AT_TESTING_LAB]);

        $this->drive(self::EDIT, self::editPost($id, [
            'isSampleRejected' => 'yes',
            'rejectionReason' => '1',
            'rejectionDate' => '2026-09-27',
        ]));

        $row = $this->saved($id);
        self::assertSame(REJECTED, (int) $row['result_status']);
        self::assertSame('yes', $row['is_sample_rejected']);
        // The seeded row was already sent. Left at 1, the rejection never reached the
        // STS and the clinic kept seeing the sample as pending.
        self::assertSame(0, (int) $row['data_sync']);
    }

    #[RunInSeparateProcess]
    public function testResultPageClearingAResultIsWaitingAndDropsTheApproval(): void
    {
        $id = $this->resultedSample(PENDING_APPROVAL);

        $this->drive(self::RESULT, self::resultPost($id, [
            'isSampleRejected' => 'no',
            'testingPlatform' => self::PLATFORM,
            'vlResult' => '',
            'vlLog' => '',
            'testedBy' => 'tester',
            'reviewedBy' => 'reviewer',
            'reviewedOn' => '2026-09-27 11:00:00',
            'approvedBy' => 'approver',
            'approvedOnDateTime' => '2026-09-27 12:00:00',
        ]));

        $row = $this->saved($id);
        self::assertNull($row['result']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
        self::assertSignoffCleared($row);
    }

    #[RunInSeparateProcess]
    public function testResultPageUndoingARejectionWithNoResultIsWaitingAtTheLab(): void
    {
        $id = $this->rejectedSample();

        // The hidden reason select still holds the old reason
        $this->drive(self::RESULT, self::resultPost($id, [
            'isSampleRejected' => 'no',
            'rejectionReason' => '1',
            'rejectionDate' => '2026-09-27',
        ]));

        $row = $this->saved($id);
        self::assertSame('no', $row['is_sample_rejected']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
        self::assertNull($row['reason_for_sample_rejection']);
        self::assertNull($row['rejection_on']);
    }

    #[RunInSeparateProcess]
    public function testResultPageRefusesAResultWithoutAPlatform(): void
    {
        $id = $this->seed(['result_status' => RECEIVED_AT_TESTING_LAB]);

        $redirect = $this->drive(self::RESULT, self::resultPost($id, [
            'isSampleRejected' => 'no',
            'vlResult' => '1200',
        ]));

        self::assertStringContainsString('/vl/results/updateVlTestResult.php?id=', (string) $redirect);
        self::assertSame(self::REFUSED, $_SESSION['alertMsg'] ?? null);
        $row = $this->saved($id);
        self::assertNull($row['result']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
    }

    #[RunInSeparateProcess]
    public function testAddRefusesAResultWithoutAPlatform(): void
    {
        $id = $this->seed(['result_status' => RECEIVED_AT_TESTING_LAB]);

        $redirect = $this->drive(self::ADD, self::editPost($id, [
            'isSampleRejected' => 'no',
            'vlResult' => '1200',
            'artNo' => 'ART-CHANGED',
        ]));

        self::assertStringEndsWith('/vl/requests/addVlRequest.php', (string) $redirect);
        self::assertSame(self::REFUSED, $_SESSION['alertMsg'] ?? null);
        $row = $this->saved($id);
        self::assertNull($row['result']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
    }
}
