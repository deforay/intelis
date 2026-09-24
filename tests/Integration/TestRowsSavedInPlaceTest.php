<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\RedirectException;
use App\HttpHandlers\LegacyRequestHandler;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GenericTestsService;
use App\Services\TbTestsService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Throwable;
use Tests\Support\LegacyAppHarness;

/**
 * A sample's test rows (tb_tests, generic_test_results) are saved in place.
 *
 * The pages used to delete every row and insert what they posted. An analyzer can
 * add a TB test while the result page is open, and that row was lost on the next
 * save; every test also got a new id on every save. Now a posted row updates its
 * own row, a new card adds one, and a saved row goes only when the user removed it,
 * after a copy is kept in audit_log. The sample's latest-test details come from the
 * test tested last, whichever page or import wrote it.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class TestRowsSavedInPlaceTest extends TestCase
{
    private const DATABASE = 'intelis_test_rows_in_place';

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
            'r_sample_status', 'form_tb', 'tb_tests', 'form_generic', 'generic_test_results',
            'audit_log', 'test_result_attempts', 'activity_log', 'system_config', 'global_config',
        ]);
        LegacyAppHarness::withSession();
        LegacyAppHarness::db()->rawQuery(
            "INSERT INTO r_sample_status (status_id, status_name)
                VALUES (6, 'Received at lab'), (8, 'Awaiting approval')"
        );
    }

    protected function tearDown(): void
    {
        if (self::booted()) {
            LegacyAppHarness::shutdown();
        }
    }

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

    private function seedTb(): int
    {
        LegacyAppHarness::db()->insert('form_tb', [
            'vlsm_instance_id' => 'test',
            'sample_code' => 'TB-1',
            'sample_code_key' => 1,
            'sample_collection_date' => '2026-09-15 09:00:00',
            'sample_received_at_lab_datetime' => '2026-09-16 10:00:00',
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
        LegacyAppHarness::db()->insert('tb_tests', $columns + [
            'tb_id' => $tbId,
            'lab_id' => 1,
            'specimen_type' => '1',
            'sample_received_at_lab_datetime' => '2026-09-16 10:00:00',
            'test_type' => 'Smear Microscopy',
            'test_result' => 'Negative',
            'sample_tested_datetime' => '2026-09-17 08:00:00',
            'tested_by' => 'user-a',
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /**
     * One card as the per-test result page posts it.
     *
     * @param array<string, string> $card
     * @return array<string, list<string>>
     */
    private static function cards(array ...$cards): array
    {
        $fields = [
            'labId', 'specimenType', 'sampleReceivedDate', 'testType', 'testResult', 'comments',
            'testedBy', 'sampleTestedDateTime', 'reviewedBy', 'reviewedOn', 'approvedBy', 'approvedOn',
            'revisedBy', 'revisedOn', 'reasonForChange', 'testId',
        ];
        $posted = [];
        foreach ($fields as $field) {
            foreach ($cards as $card) {
                $posted[$field][] = $card[$field] ?? '';
            }
        }
        return $posted;
    }

    private function driveTbResultPage(array $post): void
    {
        $this->drive('/tb/results/tb-update-result-helper.php', $post + [
            'labId' => '1',
            'instanceId' => 'test',
            'isSampleRejected' => 'no',
            'tbTestsRequested' => '',
        ]);
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

    // ---------------------------------------------------------------- TB result page

    /**
     * The case this change exists for: the GeneXpert import adds a test while the
     * result page is open, and the page's save posts back only the tests it showed.
     */
    #[RunInSeparateProcess]
    public function testTheResultPageKeepsATestItDidNotShow(): void
    {
        $tbId = $this->seedTb();
        $smear = $this->seedTbTest($tbId, []);
        $imported = $this->seedTbTest($tbId, [
            'test_type' => 'MTB/ RIF Ultra',
            'test_result' => 'MTB Not Detected',
            'sample_tested_datetime' => '2026-09-18 19:34:31',
            'tested_by' => 'analyzer-user',
            'comments' => 'Pooled with A, B: pool NOT DETECTED',
        ]);

        $this->driveTbResultPage([
            'tbSampleId' => (string) $tbId,
            'testResult' => self::cards([
                'labId' => '1', 'specimenType' => '1', 'sampleReceivedDate' => '16-Sep-2026 10:00',
                'testType' => 'Smear Microscopy', 'testResult' => 'Scanty',
                'testedBy' => 'user-a', 'sampleTestedDateTime' => '17-Sep-2026 08:00',
                'testId' => (string) $smear,
            ]),
        ]);

        $tests = $this->tbTests($tbId);
        self::assertSame([$smear, $imported], array_map(static fn($t) => (int) $t['tb_test_id'], $tests));
        // The shown test was updated in its own row...
        self::assertSame('Scanty', $tests[0]['test_result']);
        // ...and the one it did not show is as the import left it.
        self::assertSame('MTB Not Detected', $tests[1]['test_result']);
        self::assertSame('analyzer-user', $tests[1]['tested_by']);
        self::assertSame('Pooled with A, B: pool NOT DETECTED', $tests[1]['comments']);

        // The sample's latest test is the one tested last, not the last card posted.
        $sample = $this->formTb($tbId);
        self::assertSame('2026-09-18 19:34:31', $sample['sample_tested_datetime']);
        self::assertSame('analyzer-user', $sample['tested_by']);
        self::assertSame(0, (int) $sample['data_sync']);
    }

    /** Removing a saved card deletes that test and no other, and keeps a copy of it. */
    #[RunInSeparateProcess]
    public function testRemovingACardDeletesOnlyThatTestAndKeepsACopy(): void
    {
        $tbId = $this->seedTb();
        $kept = $this->seedTbTest($tbId, []);
        $removed = $this->seedTbTest($tbId, ['test_type' => 'TB LAM', 'test_result' => 'Positive']);
        $notShown = $this->seedTbTest($tbId, ['test_type' => 'MTB/ RIF Ultra', 'test_result' => 'MTB Not Detected']);

        $this->driveTbResultPage([
            'tbSampleId' => (string) $tbId,
            'testResult' => self::cards([
                'labId' => '1', 'testType' => 'Smear Microscopy', 'testResult' => 'Negative',
                'sampleTestedDateTime' => '17-Sep-2026 08:00', 'testId' => (string) $kept,
            ]),
            'deletedTestIds' => [(string) $removed],
        ]);

        self::assertSame(
            [$kept, $notShown],
            array_map(static fn($t) => (int) $t['tb_test_id'], $this->tbTests($tbId))
        );
        $copy = LegacyAppHarness::db()->rawQueryOne(
            "SELECT * FROM audit_log WHERE form_table = 'tb_tests' AND record_id = ?",
            [(string) $removed]
        );
        self::assertSame('delete', $copy['action'] ?? null);
        self::assertSame('Positive', json_decode((string) $copy['row_data'], true)['test_result'] ?? null);
    }

    /**
     * A test id comes from the browser. Another sample's id can neither change nor
     * delete that sample's test.
     */
    #[RunInSeparateProcess]
    public function testAnotherSamplesTestIdReachesNothing(): void
    {
        $tbId = $this->seedTb();
        LegacyAppHarness::db()->insert('form_tb', [
            'vlsm_instance_id' => 'test', 'sample_code' => 'TB-2', 'sample_code_key' => 2,
            'sample_collection_date' => '2026-09-15 09:00:00', 'facility_id' => 1, 'lab_id' => 1,
            'result_status' => 6,
        ]);
        $otherTbId = (int) LegacyAppHarness::db()->getInsertId();
        $otherTest = $this->seedTbTest($otherTbId, ['test_result' => 'Negative']);
        $otherLam = $this->seedTbTest($otherTbId, ['test_type' => 'TB LAM', 'test_result' => 'Positive']);

        $this->driveTbResultPage([
            'tbSampleId' => (string) $tbId,
            'testResult' => self::cards([
                'labId' => '1', 'testType' => 'Smear Microscopy', 'testResult' => '3+',
                'sampleTestedDateTime' => '17-Sep-2026 08:00', 'testId' => (string) $otherTest,
            ]),
            'deletedTestIds' => [(string) $otherLam],
        ]);

        $other = $this->tbTests($otherTbId);
        self::assertSame([$otherTest, $otherLam], array_map(static fn($t) => (int) $t['tb_test_id'], $other));
        self::assertSame('Negative', $other[0]['test_result']);
        // The card is this sample's new test.
        $mine = $this->tbTests($tbId);
        self::assertCount(1, $mine);
        self::assertSame('3+', $mine[0]['test_result']);
    }

    /**
     * A reason given for changing a result is added to the history held on that
     * test's row, which survives the save because the row does.
     */
    #[RunInSeparateProcess]
    public function testAChangeReasonIsAddedToTheTestsOwnHistory(): void
    {
        $tbId = $this->seedTb();
        $test = $this->seedTbTest($tbId, [
            'reason_for_result_change' => json_encode([
                ['usr' => 'u0', 'dtime' => '2026-09-17 09:00:00', 'msg' => 'first'],
            ]),
        ]);

        $this->driveTbResultPage([
            'tbSampleId' => (string) $tbId,
            'testResult' => self::cards([
                'labId' => '1', 'testType' => 'Smear Microscopy', 'testResult' => '1+',
                'sampleTestedDateTime' => '17-Sep-2026 08:00', 'testId' => (string) $test,
                'reasonForChange' => 'second',
            ]),
        ]);

        $row = $this->tbTests($tbId)[0];
        self::assertSame($test, (int) $row['tb_test_id']);
        self::assertSame(
            ['first', 'second'],
            array_column(json_decode((string) $row['reason_for_result_change'], true), 'msg')
        );
    }

    /**
     * A single-result form that posts no microscopy, like the per-test request form
     * with its cards hidden, says nothing about the sample's tests.
     */
    #[RunInSeparateProcess]
    public function testASaveThatPostsNoTestsLeavesThemAlone(): void
    {
        $tbId = $this->seedTb();
        $test = $this->seedTbTest($tbId, ['test_type' => 'MTB/ RIF Ultra', 'test_result' => 'MTB Not Detected']);

        $this->driveTbResultPage(['tbSampleId' => (string) $tbId]);

        self::assertSame([$test], array_map(static fn($t) => (int) $t['tb_test_id'], $this->tbTests($tbId)));
    }

    /**
     * The request edit hides the test cards on an STS and from users who cannot enter
     * results, so it posts none. That edit deleted every test on the sample.
     */
    #[RunInSeparateProcess]
    public function testARequestEditWithTheTestsHiddenLeavesThemAlone(): void
    {
        $tbId = $this->seedTb();
        $test = $this->seedTbTest($tbId, ['test_type' => 'MTB/ RIF Ultra', 'test_result' => 'MTB Not Detected']);

        $this->drive('/tb/requests/tb-edit-request-helper.php', [
            'tbSampleId' => (string) $tbId,
            'sampleCode' => 'TB-1',
            'instanceId' => 'test',
            'facilityId' => '1',
            'labId' => '1',
            'sampleCollectionDate' => '15-Sep-2026 09:00',
            'typeOfPatient' => [],
            'formId' => '',
            'specimenType' => '',
            'patientId' => 'P-1',
            'isSampleRejected' => 'no',
        ]);

        self::assertSame([$test], array_map(static fn($t) => (int) $t['tb_test_id'], $this->tbTests($tbId)));
        self::assertSame('P-1', $this->formTb($tbId)['patient_id']);
    }

    /** The request edit saves its cards in place too, and deletes only a removed one. */
    #[RunInSeparateProcess]
    public function testARequestEditSavesItsCardsInPlace(): void
    {
        $tbId = $this->seedTb();
        $shown = $this->seedTbTest($tbId, []);
        $removed = $this->seedTbTest($tbId, ['test_type' => 'TB LAM']);
        $notShown = $this->seedTbTest($tbId, [
            'test_type' => 'MTB/ RIF Ultra',
            'test_result' => 'MTB Not Detected',
            'sample_tested_datetime' => '2026-09-18 19:34:31',
            'tested_by' => 'analyzer-user',
        ]);

        $this->drive('/tb/requests/tb-edit-request-helper.php', [
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
            'testResult' => self::cards([
                'labId' => '1', 'testType' => 'Smear Microscopy', 'testResult' => '2+',
                'sampleTestedDateTime' => '17-Sep-2026 08:00', 'testedBy' => 'user-a', 'testId' => (string) $shown,
            ]),
            'deletedTestIds' => [(string) $removed],
        ]);

        $tests = $this->tbTests($tbId);
        self::assertSame([$shown, $notShown], array_map(static fn($t) => (int) $t['tb_test_id'], $tests));
        self::assertSame('2+', $tests[0]['test_result']);
        self::assertSame('analyzer-user', $this->formTb($tbId)['tested_by']);
    }

    /**
     * The tests and the sample are written together. When the sample cannot be
     * saved, no test is changed either.
     */
    #[RunInSeparateProcess]
    public function testTheTestsAreNotChangedWhenTheSampleCannotBeSaved(): void
    {
        $tbId = $this->seedTb();
        $test = $this->seedTbTest($tbId, []);
        LegacyAppHarness::db()->mysqli()->query(
            "CREATE TRIGGER form_tb_refuse BEFORE UPDATE ON form_tb FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused'"
        );

        try {
            $this->driveTbResultPage([
                'tbSampleId' => (string) $tbId,
                'testResult' => self::cards(
                    [
                        'labId' => '1', 'testType' => 'Smear Microscopy', 'testResult' => '3+',
                        'sampleTestedDateTime' => '17-Sep-2026 08:00', 'testId' => (string) $test,
                    ],
                    ['labId' => '1', 'testType' => 'TB LAM', 'testResult' => 'Positive'],
                ),
            ]);
        } catch (Throwable) {
            // The page reports the failure; what matters is what it left behind.
        }

        $tests = $this->tbTests($tbId);
        self::assertCount(1, $tests);
        self::assertSame('Negative', $tests[0]['test_result']);
    }

    /**
     * The latest test is decided by when each test was run, and an older test is
     * still the latest until a newer one is tested. A new card with no tested date
     * does not become the sample's latest test over one that was tested.
     */
    #[RunInSeparateProcess]
    public function testTheLatestTestIsTheOneTestedLast(): void
    {
        $tbId = $this->seedTb();

        $this->driveTbResultPage([
            'tbSampleId' => (string) $tbId,
            'testResult' => self::cards(
                [
                    'labId' => '1', 'testType' => 'MTB/ RIF Ultra', 'testResult' => 'MTB Not Detected',
                    'testedBy' => 'user-late', 'sampleTestedDateTime' => '19-Sep-2026 11:00',
                    'reviewedBy' => 'reviewer-late', 'reviewedOn' => '19-Sep-2026 12:00',
                ],
                [
                    'labId' => '1', 'testType' => 'Smear Microscopy', 'testResult' => 'Negative',
                    'testedBy' => 'user-early', 'sampleTestedDateTime' => '17-Sep-2026 08:00',
                ],
                ['labId' => '1', 'testType' => 'TB LAM'],
            ),
        ]);

        $sample = $this->formTb($tbId);
        self::assertSame('2026-09-19 11:00:00', $sample['sample_tested_datetime']);
        self::assertSame('user-late', $sample['tested_by']);
        self::assertSame('reviewer-late', $sample['result_reviewed_by']);
    }

    // ---------------------------------------------------------------- TB service

    #[RunInSeparateProcess]
    public function testTheLatestTestFallsBackToTheLastAddedWhenNoneIsTested(): void
    {
        $tbId = $this->seedTb();
        $this->seedTbTest($tbId, ['sample_tested_datetime' => null, 'tested_by' => 'first']);
        $this->seedTbTest($tbId, ['sample_tested_datetime' => null, 'tested_by' => 'second']);

        $service = new TbTestsService(LegacyAppHarness::db());
        self::assertSame('second', $service->latestTestColumns($tbId)['tested_by']);
        self::assertSame([], $service->latestTestColumns($tbId + 100));
    }

    // ---------------------------------------------------------------- Custom tests, per-test cards

    private function seedGeneric(array $columns = []): int
    {
        LegacyAppHarness::db()->insert('form_generic', $columns + [
            'vlsm_instance_id' => 'test',
            'request_created_by' => 'user-a',
            'sample_code' => 'GT-1',
            'facility_id' => 1,
            'lab_id' => 1,
            'result_status' => 6,
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /** @param array<string, mixed> $columns */
    private function seedGenericTest(int $sampleId, array $columns = []): int
    {
        LegacyAppHarness::db()->insert('generic_test_results', $columns + [
            'generic_id' => $sampleId,
            'lab_id' => 1,
            'test_name' => 'Method A',
            'result' => 'Negative',
            'tested_by' => 'user-a',
            'sample_tested_datetime' => '2026-09-17 08:00:00',
        ]);
        return (int) LegacyAppHarness::db()->getInsertId();
    }

    /** @return list<array<string, mixed>> */
    private function genericTests(int $sampleId): array
    {
        return LegacyAppHarness::db()->rawQuery(
            'SELECT * FROM generic_test_results WHERE generic_id = ? ORDER BY test_id',
            [$sampleId]
        );
    }

    /**
     * A request edit that only removes a saved test posts no cards at all. It
     * blanked the sample's latest-test details, as if it had no tests left.
     */
    #[RunInSeparateProcess]
    public function testRemovingTheOnlyEditedCustomTestKeepsTheOthersDetails(): void
    {
        $sampleId = $this->seedGeneric();
        $this->seedGenericTest($sampleId, [
            'tested_by' => 'user-kept',
            'sample_tested_datetime' => '2026-09-17 08:00:00',
        ]);
        $removed = $this->seedGenericTest($sampleId, [
            'tested_by' => 'user-gone',
            'sample_tested_datetime' => '2026-09-18 08:00:00',
        ]);

        ContainerRegistry::get(GenericTestsService::class)
            ->saveMultiTestResults($sampleId, ['deletedTestIds' => [(string) $removed]], 'user-a');

        self::assertCount(1, $this->genericTests($sampleId));
        $sample = LegacyAppHarness::db()->rawQueryOne('SELECT * FROM form_generic WHERE sample_id = ?', [$sampleId]);
        self::assertSame('user-kept', $sample['tested_by']);
        self::assertSame('2026-09-17 08:00:00', $sample['sample_tested_datetime']);
        self::assertNotNull(LegacyAppHarness::db()->rawQueryOne(
            "SELECT id FROM audit_log WHERE form_table = 'generic_test_results' AND record_id = ?",
            [(string) $removed]
        ));
    }

    /** A test not posted back is kept, and the latest test is the one tested last. */
    #[RunInSeparateProcess]
    public function testCustomTestCardsKeepATestNotShownAndTakeTheLatestByDate(): void
    {
        $sampleId = $this->seedGeneric();
        $shown = $this->seedGenericTest($sampleId);
        $notShown = $this->seedGenericTest($sampleId, [
            'tested_by' => 'user-late',
            'sample_tested_datetime' => '2026-09-19 08:00:00',
        ]);

        ContainerRegistry::get(GenericTestsService::class)->saveMultiTestResults($sampleId, [
            'testResult' => [
                'labId' => ['1'],
                'testType' => ['Method A'],
                'testResult' => ['Positive'],
                'testedBy' => ['user-a'],
                'sampleTestedDateTime' => ['17-Sep-2026 08:00'],
                'testId' => [(string) $shown],
            ],
        ], 'user-a');

        $tests = $this->genericTests($sampleId);
        self::assertSame([$shown, $notShown], array_map(static fn($t) => (int) $t['test_id'], $tests));
        self::assertSame('Positive', $tests[0]['result']);
        $sample = LegacyAppHarness::db()->rawQueryOne('SELECT * FROM form_generic WHERE sample_id = ?', [$sampleId]);
        self::assertSame('user-late', $sample['tested_by']);
    }

    /** Replacing a result on the cards keeps the result it replaces, as every other edit does. */
    #[RunInSeparateProcess]
    public function testCustomTestCardsKeepTheResultTheyReplace(): void
    {
        $sampleId = $this->seedGeneric(['result' => 'Positive', 'result_status' => 7]);
        $test = $this->seedGenericTest($sampleId, ['result' => 'Positive']);

        ContainerRegistry::get(GenericTestsService::class)->saveMultiTestResults($sampleId, [
            'testResult' => [
                'labId' => ['1'], 'testType' => ['Method A'], 'testResult' => ['Negative'],
                'sampleTestedDateTime' => ['17-Sep-2026 08:00'], 'testId' => [(string) $test],
            ],
        ], 'user-a');

        $attempt = LegacyAppHarness::db()->rawQueryOne(
            "SELECT * FROM test_result_attempts WHERE test_type = 'generic-tests' AND record_id = ?",
            [$sampleId]
        );
        self::assertSame('Positive', $attempt['result'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testCustomTestCardsAreNotChangedWhenTheSampleCannotBeSaved(): void
    {
        $sampleId = $this->seedGeneric();
        $test = $this->seedGenericTest($sampleId);
        LegacyAppHarness::db()->mysqli()->query(
            "CREATE TRIGGER form_generic_refuse BEFORE UPDATE ON form_generic FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused'"
        );

        try {
            ContainerRegistry::get(GenericTestsService::class)->saveMultiTestResults($sampleId, [
                'testResult' => [
                    'labId' => ['1', '1'],
                    'testType' => ['Method A', 'Method B'],
                    'testResult' => ['Positive', 'Positive'],
                    'testId' => [(string) $test, ''],
                ],
            ], 'user-a');
        } catch (Throwable) {
        }

        $tests = $this->genericTests($sampleId);
        self::assertCount(1, $tests);
        self::assertSame('Negative', $tests[0]['result']);
    }

    // ---------------------------------------------------------------- Custom tests, sub-test rows

    /** @param array<string, mixed> $post */
    private function driveCustomTestResultPage(array $post): void
    {
        $this->drive('/generic-tests/results/update-generic-test-result-helper.php', $post + [
            'labId' => '1',
            'sampleCode' => 'GT-1',
            'result' => '',
        ]);
    }

    /**
     * The older sub-test rows are saved in place: a drawn row keeps its id, a
     * removed row goes with a copy kept, a row of a sub-test no longer selected goes
     * too, and a row the page did not draw stays.
     */
    #[RunInSeparateProcess]
    public function testSubTestRowsAreSavedInPlace(): void
    {
        $sampleId = $this->seedGeneric();
        $drawn = $this->seedGenericTest($sampleId, ['sub_test_name' => 'hiv', 'result' => 'Negative']);
        $removed = $this->seedGenericTest($sampleId, ['sub_test_name' => 'hiv', 'result' => 'Invalid']);
        $notDrawn = $this->seedGenericTest($sampleId, ['sub_test_name' => 'hiv', 'result' => 'Reactive']);
        $dropped = $this->seedGenericTest($sampleId, ['sub_test_name' => 'hcv', 'result' => 'Negative']);

        $this->driveCustomTestResultPage([
            'requestSampleId' => (string) $sampleId,
            'isSampleRejected' => 'no',
            'subTestResult' => ['HIV'],
            'testName' => ['hiv' => ['Method A', 'Method B']],
            'testRowId' => ['hiv' => [(string) $drawn, '']],
            'testDate' => ['hiv' => ['17-Sep-2026 08:00', '18-Sep-2026 08:00']],
            'testingPlatform' => ['hiv' => ['', '']],
            'testResult' => ['hiv' => ['Positive', 'Positive']],
            'testResultUnit' => ['hiv' => ['', '']],
            'resultType' => ['hiv' => 'qualitative'],
            'finalResult' => ['hiv' => 'Positive'],
            'finalTestResultUnit' => ['hiv' => ''],
            'resultInterpretation' => ['hiv' => ''],
            'deletedTestIds' => [(string) $removed],
        ]);

        $tests = $this->genericTests($sampleId);
        $ids = array_map(static fn($t) => (int) $t['test_id'], $tests);
        self::assertSame([$drawn, $notDrawn], array_slice($ids, 0, 2));
        self::assertCount(3, $tests);
        self::assertSame('Positive', $tests[0]['result']);
        self::assertSame('Reactive', $tests[1]['result']);
        self::assertSame('Method B', $tests[2]['test_name']);
        foreach ([$removed, $dropped] as $gone) {
            self::assertNotNull(LegacyAppHarness::db()->rawQueryOne(
                "SELECT id FROM audit_log WHERE form_table = 'generic_test_results' AND record_id = ?",
                [(string) $gone]
            ), "test $gone was deleted without a copy");
        }
    }

    /** Rejecting the sample removes its tests, each kept in audit_log first. */
    #[RunInSeparateProcess]
    public function testRejectingACustomTestSampleKeepsACopyOfItsTests(): void
    {
        $sampleId = $this->seedGeneric();
        $test = $this->seedGenericTest($sampleId, ['sub_test_name' => 'hiv']);

        $this->driveCustomTestResultPage([
            'requestSampleId' => (string) $sampleId,
            'isSampleRejected' => 'yes',
            'rejectionReason' => '1',
        ]);

        self::assertSame([], $this->genericTests($sampleId));
        self::assertNotNull(LegacyAppHarness::db()->rawQueryOne(
            "SELECT id FROM audit_log WHERE form_table = 'generic_test_results' AND record_id = ?",
            [(string) $test]
        ));
    }

    /** A latest test with no received date does not wipe the sample's. */
    #[RunInSeparateProcess]
    public function testTheSampleKeepsItsReceivedDateWhenTheLatestTestHasNone(): void
    {
        $tbId = $this->seedTb();

        $this->driveTbResultPage([
            'tbSampleId' => (string) $tbId,
            'testResult' => self::cards([
                'labId' => '1', 'testType' => 'Smear Microscopy', 'testResult' => 'Negative',
                'sampleTestedDateTime' => '17-Sep-2026 08:00',
            ]),
        ]);

        self::assertSame('2026-09-16 10:00:00', $this->formTb($tbId)['sample_received_at_lab_datetime']);
    }

    /** Removing a sample's last custom test leaves the sample at its lab. */
    #[RunInSeparateProcess]
    public function testRemovingTheLastCustomTestKeepsTheSamplesLab(): void
    {
        $sampleId = $this->seedGeneric();
        $test = $this->seedGenericTest($sampleId);

        ContainerRegistry::get(GenericTestsService::class)
            ->saveMultiTestResults($sampleId, ['deletedTestIds' => [(string) $test]], 'user-a');

        self::assertSame([], $this->genericTests($sampleId));
        $sample = LegacyAppHarness::db()->rawQueryOne('SELECT * FROM form_generic WHERE sample_id = ?', [$sampleId]);
        self::assertSame(1, (int) $sample['lab_id']);
    }

    /**
     * A row with no sub-test is drawn under every selected sub-test. The first copy
     * updates it and each later copy is its own sub-test's row, as it always was;
     * none is dropped.
     */
    #[RunInSeparateProcess]
    public function testARowDrawnUnderTwoSubTestsIsNotDropped(): void
    {
        $sampleId = $this->seedGeneric();
        $shared = $this->seedGenericTest($sampleId, ['sub_test_name' => null]);

        $this->driveCustomTestResultPage([
            'requestSampleId' => (string) $sampleId,
            'isSampleRejected' => 'no',
            'subTestResult' => ['HIV', 'HCV'],
            'testName' => ['hiv' => ['Method A'], 'hcv' => ['Method C']],
            'testRowId' => ['hiv' => [(string) $shared], 'hcv' => [(string) $shared]],
            'testDate' => ['hiv' => ['17-Sep-2026 08:00'], 'hcv' => ['17-Sep-2026 09:00']],
            'testingPlatform' => ['hiv' => [''], 'hcv' => ['']],
            'testResult' => ['hiv' => ['Positive'], 'hcv' => ['Negative']],
            'testResultUnit' => ['hiv' => [''], 'hcv' => ['']],
            'resultType' => ['hiv' => 'qualitative', 'hcv' => 'qualitative'],
            'finalResult' => ['hiv' => 'Positive', 'hcv' => 'Negative'],
            'finalTestResultUnit' => ['hiv' => '', 'hcv' => ''],
            'resultInterpretation' => ['hiv' => '', 'hcv' => ''],
        ]);

        $tests = $this->genericTests($sampleId);
        self::assertCount(2, $tests);
        self::assertSame($shared, (int) $tests[0]['test_id']);
        self::assertSame(['hiv', 'hcv'], array_column($tests, 'sub_test_name'));
        self::assertSame(['Method A', 'Method C'], array_column($tests, 'test_name'));
    }

    /**
     * A saved test posted without a lab keeps its own lab, and its edits are saved.
     * Such a card used to be skipped, so what the user changed on it was lost.
     */
    #[RunInSeparateProcess]
    public function testASavedTestPostedWithoutALabKeepsItsLabAndItsEdits(): void
    {
        $tbId = $this->seedTb();
        $test = $this->seedTbTest($tbId, ['lab_id' => 7]);

        $this->driveTbResultPage([
            'tbSampleId' => (string) $tbId,
            'testResult' => self::cards([
                'labId' => '', 'testType' => 'Smear Microscopy', 'testResult' => '2+',
                'sampleTestedDateTime' => '17-Sep-2026 08:00', 'testId' => (string) $test,
            ]),
        ]);

        $tests = $this->tbTests($tbId);
        self::assertCount(1, $tests);
        self::assertSame($test, (int) $tests[0]['tb_test_id']);
        self::assertSame('2+', $tests[0]['test_result']);
        self::assertSame(7, (int) $tests[0]['lab_id']);
    }

    /**
     * A request edit posts its cards on every save. One that changes no result keeps
     * no copy of the unchanged result.
     */
    #[RunInSeparateProcess]
    public function testACustomTestSaveThatChangesNoResultKeepsNoCopy(): void
    {
        // Awaiting approval, which is where a save with this result leaves it.
        $sampleId = $this->seedGeneric(['result' => 'Positive', 'result_status' => 8]);
        $test = $this->seedGenericTest($sampleId, ['result' => 'Positive']);

        ContainerRegistry::get(GenericTestsService::class)->saveMultiTestResults($sampleId, [
            'testResult' => [
                'labId' => ['1'], 'testType' => ['Method A'], 'testResult' => ['Positive'],
                'comments' => ['checked again'],
                'sampleTestedDateTime' => ['17-Sep-2026 08:00'], 'testId' => [(string) $test],
            ],
            'isResultFinalized' => 'yes',
            'finalResult' => 'Positive',
        ], 'user-a');

        self::assertSame(0, (int) LegacyAppHarness::db()->rawQueryOne(
            "SELECT COUNT(*) AS n FROM test_result_attempts WHERE record_id = ?",
            [$sampleId]
        )['n']);
        self::assertSame('checked again', $this->genericTests($sampleId)[0]['comments']);
    }

    /**
     * Saving the cards of an approved sample sends it back for approval even with the
     * same result, so the approved result is kept.
     */
    #[RunInSeparateProcess]
    public function testACustomTestSaveThatMovesTheStatusKeepsACopy(): void
    {
        $sampleId = $this->seedGeneric(['result' => 'Positive', 'result_status' => 7]);
        $test = $this->seedGenericTest($sampleId, ['result' => 'Positive']);

        ContainerRegistry::get(GenericTestsService::class)->saveMultiTestResults($sampleId, [
            'testResult' => [
                'labId' => ['1'], 'testType' => ['Method A'], 'testResult' => ['Positive'],
                'sampleTestedDateTime' => ['17-Sep-2026 08:00'], 'testId' => [(string) $test],
            ],
            'isResultFinalized' => 'yes',
            'finalResult' => 'Positive',
        ], 'user-a');

        $attempt = LegacyAppHarness::db()->rawQueryOne(
            'SELECT * FROM test_result_attempts WHERE record_id = ?',
            [$sampleId]
        );
        self::assertSame(7, (int) ($attempt['result_status'] ?? 0));
    }

    /** A test's unit is part of its result: changing it keeps a copy. */
    #[RunInSeparateProcess]
    public function testChangingATestsUnitKeepsACopy(): void
    {
        $sampleId = $this->seedGeneric(['result' => 'Positive', 'result_status' => 8]);
        $test = $this->seedGenericTest($sampleId, ['result' => '50', 'result_unit' => 1]);

        ContainerRegistry::get(GenericTestsService::class)->saveMultiTestResults($sampleId, [
            'testResult' => [
                'labId' => ['1'], 'testType' => ['Method A'], 'testResult' => ['50'], 'resultUnit' => ['2'],
                'sampleTestedDateTime' => ['17-Sep-2026 08:00'], 'testId' => [(string) $test],
            ],
            'isResultFinalized' => 'yes',
            'finalResult' => 'Positive',
        ], 'user-a');

        self::assertNotNull(LegacyAppHarness::db()->rawQueryOne(
            'SELECT attempt_id FROM test_result_attempts WHERE record_id = ?',
            [$sampleId]
        ));
    }

    /** A saved custom test posted without a lab keeps its lab, and its edits are saved. */
    #[RunInSeparateProcess]
    public function testASavedCustomTestPostedWithoutALabKeepsItsLabAndItsEdits(): void
    {
        $sampleId = $this->seedGeneric();
        $test = $this->seedGenericTest($sampleId, ['lab_id' => 7]);

        ContainerRegistry::get(GenericTestsService::class)->saveMultiTestResults($sampleId, [
            'testResult' => [
                'labId' => [''], 'testType' => ['Method A'], 'testResult' => ['Positive'],
                'testId' => [(string) $test],
            ],
        ], 'user-a');

        $tests = $this->genericTests($sampleId);
        self::assertSame('Positive', $tests[0]['result']);
        self::assertSame(7, (int) $tests[0]['lab_id']);
    }

    /**
     * A custom test type with no sub-tests posts testName[][]: each row under its own
     * key. Those rows are saved in place too.
     */
    #[RunInSeparateProcess]
    public function testRowsOfATestTypeWithoutSubTestsAreSavedInPlace(): void
    {
        $sampleId = $this->seedGeneric();
        $first = $this->seedGenericTest($sampleId, ['sub_test_name' => '0']);

        $this->driveCustomTestResultPage([
            'requestSampleId' => (string) $sampleId,
            'isSampleRejected' => 'no',
            'testName' => [['Method A'], ['Method B']],
            'testRowId' => [[(string) $first], ['']],
            'testDate' => [['17-Sep-2026 08:00'], ['18-Sep-2026 08:00']],
            'testingPlatform' => [[''], ['']],
            'testResult' => [['Positive'], ['Negative']],
            'testResultUnit' => [[''], ['']],
            // Posted once for the test type, not once per row.
            'resultType' => ['qualitative'],
            'finalResult' => ['Positive'],
            'finalTestResultUnit' => [''],
            'resultInterpretation' => [''],
        ]);

        $tests = $this->genericTests($sampleId);
        self::assertCount(2, $tests);
        self::assertSame($first, (int) $tests[0]['test_id']);
        self::assertSame('Positive', $tests[0]['result']);
        self::assertSame('Method B', $tests[1]['test_name']);
        // The test type's fields are posted once and belong to every row.
        self::assertSame(['qualitative', 'qualitative'], array_column($tests, 'result_type'));
    }
}
