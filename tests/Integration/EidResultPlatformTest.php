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
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;

/**
 * The EID request add, request edit and result pages, driven as a browser posts
 * them. A result could be saved with no testing platform from the request pages.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class EidResultPlatformTest extends TestCase
{
    private const DATABASE = 'intelis_eid_result_platform';

    private const ADD = '/eid/requests/eid-add-request-helper.php';

    private const EDIT = '/eid/requests/eid-edit-request-helper.php';

    private const RESULT = '/eid/results/eid-update-result-helper.php';

    private const PLATFORM = 'Abbott##20';

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
            'r_sample_status', 'form_eid', 'facility_details', 'geographical_divisions',
            'r_eid_sample_rejection_reasons', 'r_vl_art_regimen', 'lab_storage', 'activity_log', 'audit_log',
            'system_config', 'global_config', 's_vlsm_instance', 'log_result_updates', 'test_result_attempts',
        ]);
        $db->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name) VALUES
                (4, 'Rejected'), (6, 'Received at lab'), (7, 'Accepted'), (8, 'Awaiting Approval'),
                (9, 'Received at clinic')"
        );
        LegacyAppHarness::withSession(['instanceId' => 'test']);
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    /** Runs a helper; returns where it redirected to, when it went through MiscUtility::redirect(). */
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

    /** @param array<string, mixed> $row */
    private function seed(array $row): int
    {
        LegacyAppHarness::db()->insert('form_eid', $row + [
            'unique_id' => 'U-' . bin2hex(random_bytes(4)),
            'vlsm_instance_id' => 'test',
            'sample_code' => 'EID-1',
            'sample_code_key' => 1,
            'sample_collection_date' => '2026-09-25 09:00:00',
            'sample_received_at_lab_datetime' => '2026-09-26 10:00:00',
            'facility_id' => 1,
            'lab_id' => 1,
            'child_id' => 'C-1',
            'result_status' => RECEIVED_AT_TESTING_LAB,
            'data_sync' => 1,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function resultedSample(int $status = ACCEPTED): int
    {
        return $this->seed([
            'result_status' => $status,
            'is_sample_rejected' => 'no',
            'eid_test_platform' => 'Abbott',
            'instrument_id' => '20',
            'result' => 'negative',
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
            'SELECT result, result_status, is_sample_rejected, eid_test_platform, child_id, result_approved_by
               FROM form_eid WHERE eid_id = ?'
        );
        $statement->bind_param('i', $id);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $mysqli->close();
        return $row;
    }

    /** What the request add and edit forms post, lab section shown. */
    private static function requestPost(int $id, array $post): array
    {
        $oldStatus = (string) LegacyAppHarness::db()->rawQueryOne(
            'SELECT result_status FROM form_eid WHERE eid_id = ?',
            [$id]
        )['result_status'];
        return $post + [
            'eidSampleId' => (string) $id,
            'sampleCode' => 'EID-1',
            'facilityId' => '1',
            'labId' => '1',
            'childId' => 'C-1',
            'sampleCollectionDate' => '2026-09-25 09:00:00',
            'sampleReceivedDate' => '2026-09-26 10:00:00',
            'oldStatus' => $oldStatus,
            'status' => '',
            'motherTreatment' => [],
            'formId' => '',
        ];
    }

    /** What the result page posts. */
    private static function resultPost(int $id, array $post): array
    {
        return $post + [
            'eidSampleId' => (string) $id,
            'sampleCode' => 'EID-1',
            'labId' => '1',
            'childId' => 'C-1',
            'sampleReceivedDate' => '2026-09-26 10:00:00',
        ];
    }

    #[RunInSeparateProcess]
    public function testEditRefusesAResultWithoutAPlatform(): void
    {
        $id = $this->resultedSample();

        $redirect = $this->drive(self::EDIT, self::requestPost($id, [
            'isSampleRejected' => 'no',
            'eidPlatform' => '',
            'result' => 'positive',
            'childId' => 'C-CHANGED',
        ]));

        self::assertStringStartsWith('/eid/requests/eid-edit-request.php?id=', (string) $redirect);
        self::assertSame(self::REFUSED, $_SESSION['alertMsg'] ?? null);
        $row = $this->saved($id);
        self::assertSame('negative', $row['result']);
        self::assertSame('Abbott', $row['eid_test_platform']);
        self::assertSame('C-1', $row['child_id']);
        self::assertSame(ACCEPTED, (int) $row['result_status']);
        self::assertSame('approver', $row['result_approved_by']);
    }

    #[RunInSeparateProcess]
    public function testAddRefusesAResultWithoutAPlatform(): void
    {
        // The row insert-sample.php made when the sample code was generated
        $id = $this->seed(['child_id' => null]);

        $redirect = $this->drive(self::ADD, self::requestPost($id, [
            'isSampleRejected' => 'no',
            'result' => 'positive',
        ]));

        self::assertSame('/eid/requests/eid-add-request.php', $redirect);
        self::assertSame(self::REFUSED, $_SESSION['alertMsg'] ?? null);
        $row = $this->saved($id);
        self::assertNull($row['result']);
        self::assertNull($row['eid_test_platform']);
        self::assertNull($row['child_id']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
    }

    #[RunInSeparateProcess]
    public function testResultPageRefusesAResultWithoutAPlatform(): void
    {
        $id = $this->seed([]);

        $redirect = $this->drive(self::RESULT, self::resultPost($id, [
            'isSampleRejected' => 'no',
            'eidPlatform' => '',
            'result' => 'positive',
        ]));

        self::assertSame('/eid/results/eid-update-result.php?id=' . base64_encode((string) $id), $redirect);
        self::assertSame(self::REFUSED, $_SESSION['alertMsg'] ?? null);
        $row = $this->saved($id);
        self::assertNull($row['result']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
    }

    #[RunInSeparateProcess]
    public function testEditSavesAResultWithAPlatform(): void
    {
        $id = $this->seed([]);

        $this->drive(self::EDIT, self::requestPost($id, [
            'isSampleRejected' => 'no',
            'eidPlatform' => self::PLATFORM,
            'result' => 'positive',
        ]));

        $row = $this->saved($id);
        self::assertSame('positive', $row['result']);
        self::assertSame('Abbott', $row['eid_test_platform']);
    }

    #[RunInSeparateProcess]
    public function testAddSavesAResultWithAPlatform(): void
    {
        $id = $this->seed(['child_id' => null]);

        $this->drive(self::ADD, self::requestPost($id, [
            'isSampleRejected' => 'no',
            'eidPlatform' => self::PLATFORM,
            'result' => 'positive',
        ]));

        $row = $this->saved($id);
        self::assertSame('positive', $row['result']);
        self::assertSame('Abbott', $row['eid_test_platform']);
        self::assertSame('C-1', $row['child_id']);
    }

    #[RunInSeparateProcess]
    public function testEditRejectsWithoutAPlatform(): void
    {
        $id = $this->seed([]);

        // The result field can still hold a value when the sample is rejected
        $this->drive(self::EDIT, self::requestPost($id, [
            'isSampleRejected' => 'yes',
            'sampleRejectionReason' => '1',
            'rejectionDate' => '2026-09-27',
            'eidPlatform' => '',
            'result' => 'positive',
        ]));

        $row = $this->saved($id);
        self::assertSame(REJECTED, (int) $row['result_status']);
        self::assertSame('yes', $row['is_sample_rejected']);
        self::assertNull($row['result']);
    }

    #[RunInSeparateProcess]
    public function testResultPageRejectsWithoutAPlatform(): void
    {
        $id = $this->seed([]);

        $this->drive(self::RESULT, self::resultPost($id, [
            'isSampleRejected' => 'yes',
            'sampleRejectionReason' => '1',
            'rejectionDate' => '2026-09-27',
            'result' => '',
        ]));

        $row = $this->saved($id);
        self::assertSame(REJECTED, (int) $row['result_status']);
        self::assertSame('yes', $row['is_sample_rejected']);
    }

    #[RunInSeparateProcess]
    public function testEditNotSendingTheResultSavesWithoutAPlatform(): void
    {
        $id = $this->resultedSample();

        // A form that did not send the result keeps the stored one
        $this->drive(self::EDIT, self::requestPost($id, [
            'isSampleRejected' => 'no',
            'eidPlatform' => '',
            'childId' => 'C-CHANGED',
        ]));

        $row = $this->saved($id);
        self::assertSame('C-CHANGED', $row['child_id']);
        self::assertSame('negative', $row['result']);
        self::assertSame(ACCEPTED, (int) $row['result_status']);
    }

    #[RunInSeparateProcess]
    public function testEditClearingAResultWithoutAPlatformSaves(): void
    {
        // Awaiting Approval: an Accepted result is not removed from the request edit
        $id = $this->resultedSample(PENDING_APPROVAL);

        $this->drive(self::EDIT, self::requestPost($id, [
            'isSampleRejected' => 'no',
            'eidPlatform' => '',
            'result' => '',
            'approvedBy' => 'approver',
        ]));

        $row = $this->saved($id);
        self::assertSame('', (string) $row['result']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
        self::assertNull($row['result_approved_by']);
    }

    #[RunInSeparateProcess]
    public function testResultPageSavesAResultWithAPlatform(): void
    {
        $id = $this->seed([]);

        $this->drive(self::RESULT, self::resultPost($id, [
            'isSampleRejected' => 'no',
            'eidPlatform' => self::PLATFORM,
            'result' => 'positive',
        ]));

        $row = $this->saved($id);
        self::assertSame('positive', $row['result']);
        self::assertSame(PENDING_APPROVAL, (int) $row['result_status']);
    }
}
