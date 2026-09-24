<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\RedirectException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\TbTestsService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Throwable;
use Tests\Support\LegacyAppHarness;

/**
 * The TB forms that keep one result on the sample and a few microscopy rows
 * (testResult[], actualNo[]) instead of per-test cards.
 *
 * The request add lost the received date and every microscopy row, the add, edit
 * and result pages threw away a final interpretation from a form that does not ask
 * whether the result is final, and the add lost the Xpert and identification result
 * dates to misspelt keys. The microscopy rows are now saved slot by slot: an
 * unchanged row keeps its id, and an unchanged row the API client sent stays the
 * client's.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class TbSingleResultFormsTest extends TestCase
{
    private const DATABASE = 'intelis_tb_single_result_forms';

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

        LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), [
            'r_sample_status', 'form_tb', 'tb_tests', 'audit_log', 'test_result_attempts', 'activity_log',
            'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession();
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name)
                VALUES (4, 'Rejected'), (6, 'Received at lab'), (8, 'Awaiting approval')"
        );
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

    private function drive(string $path, array $post): void
    {
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

    private function seedTb(): int
    {
        LegacyAppHarness::db()->insert('form_tb', [
            'vlsm_instance_id' => 'test',
            'sample_code' => 'TB-1',
            'sample_code_key' => 1,
            'sample_collection_date' => '2026-09-15 09:00:00',
            'facility_id' => 1,
            'lab_id' => 1,
            'result_status' => 6,
            'data_sync' => 1,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /** @param array<string, mixed> $columns */
    private function seedTbTest(int $tbId, array $columns): int
    {
        LegacyAppHarness::db()->insert('tb_tests', $columns + ['tb_id' => $tbId, 'lab_id' => 1]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /** A request form as the single-result country forms post it. */
    private static function requestPost(int $tbId, array $post): array
    {
        return $post + [
            'tbSampleId' => (string) $tbId,
            'sampleCode' => 'TB-1',
            'instanceId' => 'test',
            'facilityId' => '1',
            'labId' => '1',
            'sampleCollectionDate' => '15-Sep-2026 09:00',
            'typeOfPatient' => [],
            'formId' => '',
            'specimenType' => '',
            'isSampleRejected' => 'no',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function tbTests(int $tbId): array
    {
        return LegacyAppHarness::db()->rawQuery(
            'SELECT tb_test_id, lab_id, actual_no, test_result FROM tb_tests WHERE tb_id = ? ORDER BY tb_test_id',
            [$tbId]
        );
    }

    /** @return array<string, mixed> */
    private function formTb(int $tbId): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM form_tb WHERE tb_id = ?', [$tbId]);
    }

    #[RunInSeparateProcess]
    public function testTheRequestAddKeepsTheReceivedDateAndTheMicroscopyRows(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/requests/tb-add-request-helper.php', self::requestPost($tbId, [
            'sampleReceivedDate' => '16-Sep-2026 10:00',
            'testResult' => ['Negative', '', '1+'],
            'actualNo' => ['', '', '7'],
        ]));

        self::assertSame('2026-09-16 10:00:00', $this->formTb($tbId)['sample_received_at_lab_datetime']);
        $tests = $this->tbTests($tbId);
        self::assertSame(
            [['1', null, 'Negative'], ['1', '7', '1+']],
            array_map(static fn($t) => [(string) $t['lab_id'], $t['actual_no'], $t['test_result']], $tests)
        );
    }

    /** A form that never asks whether the result is final keeps its final interpretation and its dates. */
    #[RunInSeparateProcess]
    public function testTheRequestAddKeepsAFinalInterpretationAndTheResultDates(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/requests/tb-add-request-helper.php', self::requestPost($tbId, [
            'finalResult' => 'MTB detected',
            'xpertDateOfResult' => '17-Sep-2026 08:00',
            'identificationDateOfResult' => '18-Sep-2026 08:00',
        ]));

        $row = $this->formTb($tbId);
        self::assertSame('MTB detected', $row['result']);
        self::assertSame(8, (int) $row['result_status']);
        self::assertSame('2026-09-17', $row['xpert_result_date']);
        self::assertSame('2026-09-18', $row['identification_result_date']);
    }

    /** A final result of "0" is a result: it stays and goes for approval. */
    #[RunInSeparateProcess]
    public function testAFinalResultOfZeroGoesForApproval(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, ['finalResult' => '0']));

        $row = $this->formTb($tbId);
        self::assertSame('0', $row['result']);
        self::assertSame(8, (int) $row['result_status']);
        self::assertSame('pending', $row['result_sent_to_source']);
    }

    /** A form that asks, and was told no, still keeps no result. */
    #[RunInSeparateProcess]
    public function testAResultNotMarkedFinalIsNotKept(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/requests/tb-add-request-helper.php', self::requestPost($tbId, [
            'isResultFinalized' => 'no',
            'finalResult' => 'MTB detected',
        ]));

        $row = $this->formTb($tbId);
        self::assertNull($row['result']);
        self::assertSame(6, (int) $row['result_status']);
    }

    #[RunInSeparateProcess]
    public function testTheRequestEditSavesTheMicroscopyRowsSlotBySlot(): void
    {
        $tbId = $this->seedTb();
        $fromClient = $this->seedTbTest($tbId, ['lab_id' => null, 'actual_no' => '1', 'test_result' => 'Negative']);
        $changed = $this->seedTbTest($tbId, ['actual_no' => '2', 'test_result' => 'Negative']);
        $cleared = $this->seedTbTest($tbId, ['actual_no' => '3', 'test_result' => '1+']);
        $notShown = $this->seedTbTest($tbId, ['actual_no' => '4', 'test_result' => '2+']);

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'testResult' => ['Negative', 'Scanty', ''],
            'actualNo' => ['1', '2', ''],
        ]));

        self::assertSame(
            [
                [$fromClient, null, '1', 'Negative'],
                [$changed, '1', '2', 'Scanty'],
                [$notShown, '1', '4', '2+'],
            ],
            array_map(
                static fn($t) => [
                    (int) $t['tb_test_id'],
                    $t['lab_id'] === null ? null : (string) $t['lab_id'],
                    $t['actual_no'],
                    $t['test_result'],
                ],
                $this->tbTests($tbId)
            )
        );
        self::assertNotContains($cleared, array_map(static fn($t) => (int) $t['tb_test_id'], $this->tbTests($tbId)));
    }

    /**
     * The form lists only No AFB and 1+ to 3+. A result the API stored outside that
     * list is shown as an extra option, and a page that still posts it blank keeps it.
     */
    #[RunInSeparateProcess]
    public function testAResultTheFormDoesNotListIsNotLost(): void
    {
        $tbId = $this->seedTb();
        $numbered = $this->seedTbTest($tbId, ['lab_id' => null, 'actual_no' => '1', 'test_result' => 'Negative']);
        $unnumbered = $this->seedTbTest($tbId, ['lab_id' => null, 'actual_no' => null, 'test_result' => 'Scanty']);

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'testResult' => ['', '', ''],
            'actualNo' => ['1', '', ''],
        ]));

        self::assertSame(
            [[$numbered, 'Negative'], [$unnumbered, 'Scanty']],
            array_map(static fn($t) => [(int) $t['tb_test_id'], $t['test_result']], $this->tbTests($tbId))
        );
        self::assertSame(
            ['No AFB' => 'No AFB', 'Negative' => 'Negative'],
            TbTestsService::microscopyOptions(['No AFB' => 'No AFB'], 'Negative')
        );
    }

    /** A row the client sent and the lab then changed becomes the lab's. */
    #[RunInSeparateProcess]
    public function testAClientRowTheLabChangesTakesTheLab(): void
    {
        $tbId = $this->seedTb();
        $fromClient = $this->seedTbTest($tbId, ['lab_id' => null, 'actual_no' => '1', 'test_result' => 'Negative']);

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'testResult' => ['1+', '', ''],
            'actualNo' => ['1', '', ''],
        ]));

        self::assertSame(
            [[$fromClient, '1', '1+']],
            array_map(
                static fn($t) => [(int) $t['tb_test_id'], (string) $t['lab_id'], $t['test_result']],
                $this->tbTests($tbId)
            )
        );
    }

    #[RunInSeparateProcess]
    public function testTheResultPageKeepsAFinalInterpretationFromAFormThatDoesNotAsk(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/results/tb-update-result-helper.php', [
            'tbSampleId' => (string) $tbId,
            'labId' => '1',
            'instanceId' => 'test',
            'isSampleRejected' => 'no',
            'tbTestsRequested' => '',
            'finalResult' => 'MTB detected',
            'testResult' => ['Negative', '', ''],
            'actualNo' => ['', '', ''],
        ]);

        $row = $this->formTb($tbId);
        self::assertSame('MTB detected', $row['result']);
        self::assertSame(8, (int) $row['result_status']);
        self::assertCount(1, $this->tbTests($tbId));
    }

    /** A result the form was told is not final neither stays nor sends the sample for approval. */
    #[RunInSeparateProcess]
    public function testTheResultPageDoesNotSendAResultNotMarkedFinalForApproval(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/results/tb-update-result-helper.php', [
            'tbSampleId' => (string) $tbId,
            'labId' => '1',
            'instanceId' => 'test',
            'isSampleRejected' => 'no',
            'tbTestsRequested' => '',
            'isResultFinalized' => 'no',
            'finalResult' => 'MTB detected',
        ]);

        $row = $this->formTb($tbId);
        self::assertNull($row['result']);
        self::assertSame(6, (int) $row['result_status']);
    }
}
