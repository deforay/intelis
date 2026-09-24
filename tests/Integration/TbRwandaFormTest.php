<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\RedirectException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Throwable;
use Tests\Support\LegacyAppHarness;

/**
 * The Rwanda TB form, which posts per-test cards (testResult[field][]) and asks
 * whether the final interpretation is final, through the request add, request edit
 * and result pages, as a lab user posts it.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class TbRwandaFormTest extends TestCase
{
    private const DATABASE = 'intelis_tb_rwanda_form';

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
                VALUES (4, 'Rejected'), (6, 'Received at lab'), (7, 'Accepted'), (8, 'Awaiting approval'),
                    (9, 'Received at clinic')"
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

    private function seedTb(int $status = 6): int
    {
        LegacyAppHarness::db()->insert('form_tb', [
            'vlsm_instance_id' => 'test',
            'vlsm_country_id' => 7,
            'sample_code' => 'TB-RW-1',
            'sample_code_key' => 1,
            'sample_collection_date' => '2026-09-15 09:00:00',
            'facility_id' => 1,
            'lab_id' => 1,
            'result_status' => $status,
            'data_sync' => 1,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /** @param array<string, mixed> $columns */
    private function seedTbTest(int $tbId, array $columns): int
    {
        LegacyAppHarness::db()->insert('tb_tests', $columns + [
            'tb_id' => $tbId,
            'lab_id' => 1,
            'test_type' => 'Smear Microscopy',
            'test_result' => 'Negative',
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /**
     * The cards as the Rwanda form posts them.
     *
     * @param array<string, string> ...$cards
     * @return array<string, list<string>>
     */
    private static function cards(array ...$cards): array
    {
        $fields = [
            'labId', 'sampleReceivedDate', 'testType', 'testResult', 'comments', 'testedBy', 'sampleTestedDateTime',
            'reviewedBy', 'reviewedOn', 'approvedBy', 'approvedOn', 'testId',
        ];
        $posted = [];
        foreach ($fields as $field) {
            foreach ($cards as $card) {
                $posted[$field][] = $card[$field] ?? '';
            }
        }
        return $posted;
    }

    private static function requestPost(int $tbId, array $post): array
    {
        return $post + [
            'tbSampleId' => (string) $tbId,
            'sampleCode' => 'TB-RW-1',
            'instanceId' => 'test',
            'facilityId' => '1',
            'labId' => '1',
            'formId' => '7',
            'sampleCollectionDate' => '15-Sep-2026 09:00',
            'typeOfPatient' => [],
            'specimenType' => '',
            'isSampleRejected' => 'no',
            'isResultFinalized' => 'no',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function tbTests(int $tbId): array
    {
        return LegacyAppHarness::db()->rawQuery('SELECT * FROM tb_tests WHERE tb_id = ? ORDER BY tb_test_id', [$tbId]);
    }

    /** @return array<string, mixed> */
    private function formTb(int $tbId): array
    {
        return LegacyAppHarness::db()->rawQueryOne('SELECT * FROM form_tb WHERE tb_id = ?', [$tbId]);
    }

    #[RunInSeparateProcess]
    public function testTheAddSavesTheCardsAndAFinalResult(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/requests/tb-add-request-helper.php', self::requestPost($tbId, [
            'testResult' => self::cards(
                [
                    'labId' => '1', 'sampleReceivedDate' => '16-Sep-2026 10:00', 'testType' => 'Smear Microscopy',
                    'testResult' => 'Negative', 'sampleTestedDateTime' => '17-Sep-2026 08:00', 'testedBy' => 'user-a',
                ],
                [
                    'labId' => '1', 'sampleReceivedDate' => '16-Sep-2026 10:00', 'testType' => 'MTB/ RIF Ultra',
                    'testResult' => 'MTB Not Detected', 'sampleTestedDateTime' => '18-Sep-2026 08:00',
                    'testedBy' => 'user-b',
                ]
            ),
            'isResultFinalized' => 'yes',
            'finalResult' => 'MTB Not Detected',
        ]));

        $sample = $this->formTb($tbId);
        self::assertSame(
            [['Smear Microscopy', 'Negative'], ['MTB/ RIF Ultra', 'MTB Not Detected']],
            array_map(static fn($t) => [$t['test_type'], $t['test_result']], $this->tbTests($tbId))
        );
        self::assertSame('MTB Not Detected', $sample['result']);
        self::assertSame('yes', $sample['is_result_finalized']);
        self::assertSame('2026-09-16 10:00:00', $sample['sample_received_at_lab_datetime']);
        self::assertSame('user-b', $sample['tested_by']);
    }

    #[RunInSeparateProcess]
    public function testTheAddKeepsNoResultThatIsNotFinal(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/requests/tb-add-request-helper.php', self::requestPost($tbId, [
            'testResult' => self::cards([
                'labId' => '1', 'testType' => 'Smear Microscopy', 'testResult' => 'Negative',
            ]),
            'finalResult' => 'MTB Not Detected',
        ]));

        $sample = $this->formTb($tbId);
        self::assertNull($sample['result']);
        self::assertSame(6, (int) $sample['result_status']);
        self::assertCount(1, $this->tbTests($tbId));
    }

    #[RunInSeparateProcess]
    public function testTheEditSavesCardsInPlaceAndAFinalResultGoesForApproval(): void
    {
        $tbId = $this->seedTb();
        $kept = $this->seedTbTest($tbId, []);
        $removed = $this->seedTbTest($tbId, ['test_type' => 'TB LAM']);

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'testResult' => self::cards([
                'labId' => '1', 'testType' => 'Smear Microscopy', 'testResult' => '1+', 'testId' => (string) $kept,
            ]),
            'deletedTestIds' => [(string) $removed],
            'isResultFinalized' => 'yes',
            'finalResult' => 'MTB Detected',
        ]));

        $sample = $this->formTb($tbId);
        $tests = $this->tbTests($tbId);
        self::assertSame([$kept], array_map(static fn($t) => (int) $t['tb_test_id'], $tests));
        self::assertSame('1+', $tests[0]['test_result']);
        self::assertSame('MTB Detected', $sample['result']);
        self::assertSame(8, (int) $sample['result_status']);
    }

    /** An edit of a sample at the clinic still receives it at the lab. */
    #[RunInSeparateProcess]
    public function testTheEditReceivesASampleFromTheClinic(): void
    {
        $tbId = $this->seedTb(9);

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, []));

        self::assertSame(6, (int) $this->formTb($tbId)['result_status']);
    }

    #[RunInSeparateProcess]
    public function testTheEditRejectsTheSample(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'isSampleRejected' => 'yes',
            'sampleRejectionReason' => '3',
            'rejectionDate' => '17-Sep-2026',
        ]));

        $sample = $this->formTb($tbId);
        self::assertSame(4, (int) $sample['result_status']);
        self::assertSame('yes', $sample['is_sample_rejected']);
        self::assertSame('2026-09-17', substr((string) $sample['rejection_on'], 0, 10));
    }

    #[RunInSeparateProcess]
    public function testTheResultPageTakesAFinalResult(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/results/tb-update-result-helper.php', [
            'tbSampleId' => (string) $tbId,
            'labId' => '1',
            'instanceId' => 'test',
            'isSampleRejected' => 'no',
            'tbTestsRequested' => '',
            'testResult' => self::cards([
                'labId' => '1', 'testType' => 'Smear Microscopy', 'testResult' => '2+',
                'sampleTestedDateTime' => '17-Sep-2026 08:00',
            ]),
            'isResultFinalized' => 'yes',
            'finalResult' => 'MTB Detected',
        ]);

        $sample = $this->formTb($tbId);
        self::assertSame('MTB Detected', $sample['result']);
        self::assertSame(8, (int) $sample['result_status']);
        self::assertSame(['2+'], array_column($this->tbTests($tbId), 'test_result'));
    }

    #[RunInSeparateProcess]
    public function testTheResultPageKeepsNoResultThatIsNotFinal(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/results/tb-update-result-helper.php', [
            'tbSampleId' => (string) $tbId,
            'labId' => '1',
            'instanceId' => 'test',
            'isSampleRejected' => 'no',
            'tbTestsRequested' => '',
            'isResultFinalized' => 'no',
            'finalResult' => 'MTB Detected',
        ]);

        $sample = $this->formTb($tbId);
        self::assertNull($sample['result']);
        self::assertSame(6, (int) $sample['result_status']);
    }
}
