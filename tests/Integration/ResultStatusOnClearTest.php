<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\RedirectException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use mysqli;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;
use Throwable;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;

/**
 * The EID, COVID-19, Hepatitis and CD4 request edit and result pages, driven as a
 * browser posts them.
 *
 * Undoing a rejection with no result left the sample Rejected on the request edit and
 * moved it to Awaiting Approval on the result page, with nothing to approve. Clearing
 * a result on the result page left it Awaiting Approval with the old approval standing.
 * The Hepatitis and CD4 result pages saved a rejection as Awaiting Approval.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ResultStatusOnClearTest extends TestCase
{
    private const DATABASE = 'intelis_result_status_on_clear';

    private const MODULES = [
        'eid' => [
            'table' => 'form_eid',
            'key' => 'eid_id',
            'idField' => 'eidSampleId',
            'edit' => '/eid/requests/eid-edit-request-helper.php',
            'result' => '/eid/results/eid-update-result-helper.php',
            'resultColumn' => 'result',
            'resultField' => 'result',
            'value' => 'negative',
        ],
        'covid19' => [
            'table' => 'form_covid19',
            'key' => 'covid19_id',
            'idField' => 'covid19SampleId',
            'edit' => '/covid-19/requests/covid-19-edit-request-helper.php',
            'result' => '/covid-19/results/covid-19-update-result-helper.php',
            'resultColumn' => 'result',
            'resultField' => 'result',
            'value' => 'negative',
        ],
        'hepatitis' => [
            'table' => 'form_hepatitis',
            'key' => 'hepatitis_id',
            'idField' => 'hepatitisSampleId',
            'edit' => '/hepatitis/requests/hepatitis-edit-request-helper.php',
            'result' => '/hepatitis/results/hepatitis-update-result-helper.php',
            'resultColumn' => 'hcv_vl_count',
            'resultField' => 'hcvCount',
            'value' => '1500',
        ],
        'cd4' => [
            'table' => 'form_cd4',
            'key' => 'cd4_id',
            'idField' => 'cd4SampleId',
            'edit' => '/cd4/requests/cd4-edit-request-helper.php',
            'result' => '/cd4/results/cd4-update-result-helper.php',
            'resultColumn' => 'cd4_result',
            'resultField' => 'cd4Result',
            'value' => '350',
        ],
    ];

    private static function booted(): bool
    {
        return getenv('INTELIS_TEST_DB_HOST') !== false
            && getenv('INTELIS_TEST_DB_HOST') !== ''
            && getenv('INTELIS_TEST_DB_USER') !== false
            && getenv('INTELIS_TEST_DB_USER') !== '';
    }

    private static function database(): string
    {
        return self::DATABASE . '_' . getmypid();
    }

    protected function setUp(): void
    {
        if (!self::booted()) {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }

        $db = LegacyAppHarness::boot(self::database(), [
            'r_sample_status', 'form_eid', 'form_covid19', 'covid19_tests', 'covid19_patient_symptoms',
            'covid19_reasons_for_testing', 'covid19_patient_comorbidities', 'facility_details',
            'geographical_divisions', 'r_eid_sample_rejection_reasons', 'r_covid19_sample_rejection_reasons',
            'r_vl_art_regimen', 'form_hepatitis', 'hepatitis_patient_comorbidities', 'hepatitis_risk_factors',
            'r_hepatitis_sample_rejection_reasons', 'form_cd4', 'r_cd4_sample_rejection_reasons', 'r_cd4_test_reasons',
            'lab_storage', 'activity_log', 'audit_log', 'system_config', 'global_config',
            's_vlsm_instance', 'log_result_updates', 'test_result_attempts',
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

    private function drive(string $path, array $post): void
    {
        // The Hepatitis result page error_log()s on every save; on stderr that fails an
        // isolated test however the save went
        ini_set('error_log', '/dev/null');
        $request = LegacyAppHarness::withPost($post, $path);
        $handler = new LegacyRequestHandler(LegacyAppHarness::db(), ContainerRegistry::get(CommonService::class));
        try {
            $handler->handle($request);
        } catch (Throwable $e) {
            for ($cause = $e; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
                if ($cause instanceof RedirectException) {
                    return;
                }
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $row */
    private function seed(string $module, array $row): int
    {
        $table = self::MODULES[$module]['table'];
        LegacyAppHarness::db()->insert($table, $row + [
            'unique_id' => 'U-' . bin2hex(random_bytes(4)),
            'vlsm_instance_id' => 'test',
            'sample_code' => 'S-1',
            'sample_code_key' => 1,
            'sample_collection_date' => '2026-09-25 09:00:00',
            'sample_received_at_lab_datetime' => '2026-09-26 10:00:00',
            'facility_id' => 1,
            'lab_id' => 1,
            'data_sync' => 1,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    private function rejectedSample(string $module): int
    {
        return $this->seed($module, [
            'result_status' => REJECTED,
            'is_sample_rejected' => 'yes',
            'reason_for_sample_rejection' => 1,
            'rejection_on' => '2026-09-27',
            'result_approved_by' => 'approver',
            'result_approved_datetime' => '2026-09-27 12:00:00',
        ]);
    }

    private function resultedSample(string $module, int $status): int
    {
        return $this->seed($module, [
            'result_status' => $status,
            'is_sample_rejected' => 'no',
            self::MODULES[$module]['resultColumn'] => self::MODULES[$module]['value'],
            'result_reviewed_by' => 'reviewer',
            'result_reviewed_datetime' => '2026-09-27 11:00:00',
            'result_approved_by' => 'approver',
            'result_approved_datetime' => '2026-09-27 12:00:00',
        ]);
    }

    /** @return array<string, mixed> */
    private function saved(string $module, int $id): array
    {
        $mysqli = new mysqli(
            (string) getenv('INTELIS_TEST_DB_HOST'),
            (string) getenv('INTELIS_TEST_DB_USER'),
            (string) (getenv('INTELIS_TEST_DB_PASS') ?: ''),
            self::database(),
            (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306)
        );
        $table = self::MODULES[$module]['table'];
        $key = self::MODULES[$module]['key'];
        $resultColumn = self::MODULES[$module]['resultColumn'];
        $statement = $mysqli->prepare(
            "SELECT $resultColumn AS result, result_status, is_sample_rejected, result_reviewed_by,
                    result_approved_by, result_approved_datetime, reason_for_sample_rejection, rejection_on
               FROM $table WHERE $key = ?"
        );
        $statement->bind_param('i', $id);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $mysqli->close();
        return $row;
    }

    /** What both the request edit and the result page post for these modules. */
    private static function post(string $module, int $id, string $page, array $post): array
    {
        $common = [
            self::MODULES[$module]['idField'] => (string) $id,
            'sampleCode' => 'S-1',
            'facilityId' => '1',
            'labId' => '1',
            'childId' => 'C-1',
            'patientId' => 'P-1',
            'sampleCollectionDate' => '2026-09-25 09:00:00',
            'sampleReceivedDate' => '2026-09-26 10:00:00',
            'oldStatus' => '',
            'status' => '',
            'testName' => [],
            'motherTreatment' => [],
            'province' => '',
            'facilityCode' => '',
            'reasonForCD4Testing' => '',
            'emailHf' => '',
        ];
        if ($page === 'edit') {
            $common['oldStatus'] = (string) LegacyAppHarness::db()->rawQueryOne(
                'SELECT result_status FROM ' . self::MODULES[$module]['table'] . ' WHERE '
                    . self::MODULES[$module]['key'] . ' = ?',
                [$id]
            )['result_status'];
        }
        return $post + $common;
    }

    /** @return array<string, array{string, string}> */
    public static function pages(): array
    {
        return [
            'EID request edit' => ['eid', 'edit'],
            'EID result page' => ['eid', 'result'],
            'COVID-19 request edit' => ['covid19', 'edit'],
            'COVID-19 result page' => ['covid19', 'result'],
            'Hepatitis request edit' => ['hepatitis', 'edit'],
            'Hepatitis result page' => ['hepatitis', 'result'],
            'CD4 request edit' => ['cd4', 'edit'],
            'CD4 result page' => ['cd4', 'result'],
        ];
    }

    #[DataProvider('pages')]
    #[RunInSeparateProcess]
    public function testUndoingARejectionWithNoResultIsWaitingAtTheLab(string $module, string $page): void
    {
        $id = $this->rejectedSample($module);

        $this->drive(self::MODULES[$module][$page], self::post($module, $id, $page, [
            'isSampleRejected' => 'no',
            self::MODULES[$module]['resultField'] => '',
            'approvedBy' => 'approver',
            // The hidden reason select still holds the old reason
            'sampleRejectionReason' => '1',
            'rejectionReason' => '1',
            'rejectionDate' => '2026-09-27',
        ]));

        $row = $this->saved($module, $id);
        self::assertSame('no', $row['is_sample_rejected']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
        self::assertNull($row['result_approved_by']);
        self::assertNull($row['reason_for_sample_rejection']);
        self::assertNull($row['rejection_on']);
    }

    #[DataProvider('pages')]
    #[RunInSeparateProcess]
    public function testClearingAResultIsWaitingAndDropsTheApproval(string $module, string $page): void
    {
        $id = $this->resultedSample($module, PENDING_APPROVAL);

        $this->drive(self::MODULES[$module][$page], self::post($module, $id, $page, [
            'isSampleRejected' => 'no',
            self::MODULES[$module]['resultField'] => '',
            'reviewedBy' => 'reviewer',
            'approvedBy' => 'approver',
        ]));

        $row = $this->saved($module, $id);
        self::assertSame('', (string) $row['result']);
        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $row['result_status']);
        self::assertNull($row['result_reviewed_by']);
        self::assertNull($row['result_approved_by']);
    }

    #[DataProvider('pages')]
    #[RunInSeparateProcess]
    public function testAResultEnteredAwaitsApproval(string $module, string $page): void
    {
        $id = $this->rejectedSample($module);

        $this->drive(self::MODULES[$module][$page], self::post($module, $id, $page, [
            'isSampleRejected' => 'no',
            self::MODULES[$module]['resultField'] => self::MODULES[$module]['value'],
            'oldStatus' => (string) PENDING_APPROVAL,
        ]));

        $row = $this->saved($module, $id);
        self::assertSame(self::MODULES[$module]['value'], (string) $row['result']);
        self::assertSame(PENDING_APPROVAL, (int) $row['result_status']);
    }

    /** @return array<string, array{string}> */
    public static function editPages(): array
    {
        return ['EID' => ['eid'], 'COVID-19' => ['covid19'], 'Hepatitis' => ['hepatitis'], 'CD4' => ['cd4']];
    }

    #[DataProvider('editPages')]
    #[RunInSeparateProcess]
    public function testAnEditWithoutTheResultKeepsAnAcceptedResult(string $module): void
    {
        $id = $this->resultedSample($module, ACCEPTED);

        // The result box is not sent; the request edit is not where a result is removed
        $this->drive(self::MODULES[$module]['edit'], self::post($module, $id, 'edit', ['isSampleRejected' => 'no']));

        $row = $this->saved($module, $id);
        self::assertSame(self::MODULES[$module]['value'], (string) $row['result']);
        self::assertSame(ACCEPTED, (int) $row['result_status']);
        self::assertSame('approver', $row['result_approved_by']);
    }

    #[DataProvider('editPages')]
    #[RunInSeparateProcess]
    public function testAnEditWithoutTheRejectionQuestionLeavesARejectionStanding(string $module): void
    {
        $id = $this->rejectedSample($module);

        // A collection site's edit form shows no rejection question, so none is posted
        $this->drive(self::MODULES[$module]['edit'], self::post($module, $id, 'edit', []));

        $row = $this->saved($module, $id);
        self::assertSame('yes', $row['is_sample_rejected']);
        self::assertSame(REJECTED, (int) $row['result_status']);
        self::assertSame('approver', $row['result_approved_by']);
    }

    /** @return array<string, array{string}> */
    public static function resultPages(): array
    {
        return ['EID' => ['eid'], 'COVID-19' => ['covid19'], 'Hepatitis' => ['hepatitis'], 'CD4' => ['cd4']];
    }

    #[DataProvider('resultPages')]
    #[RunInSeparateProcess]
    public function testTheResultPageSavedWithNoResultLeavesAWaitingSampleWaiting(string $module): void
    {
        $id = $this->seed($module, ['result_status' => RECEIVED_AT_TESTING_LAB]);

        $this->drive(self::MODULES[$module]['result'], self::post($module, $id, 'result', [
            'isSampleRejected' => 'no',
            self::MODULES[$module]['resultField'] => '',
        ]));

        self::assertSame(RECEIVED_AT_TESTING_LAB, (int) $this->saved($module, $id)['result_status']);
    }

    #[DataProvider('pages')]
    #[RunInSeparateProcess]
    public function testARejectionStaysRejected(string $module, string $page): void
    {
        $id = $this->resultedSample($module, ACCEPTED);

        // The result box still holds the result when the sample is rejected
        $this->drive(self::MODULES[$module][$page], self::post($module, $id, $page, [
            'isSampleRejected' => 'yes',
            'sampleRejectionReason' => '1',
            'rejectionReason' => '1',
            'rejectionDate' => '2026-09-27',
            self::MODULES[$module]['resultField'] => self::MODULES[$module]['value'],
        ]));

        $row = $this->saved($module, $id);
        self::assertSame(REJECTED, (int) $row['result_status']);
        self::assertSame('yes', $row['is_sample_rejected']);
        self::assertNull($row['result']);
        self::assertSame(1, (int) $row['reason_for_sample_rejection']);
    }
}
