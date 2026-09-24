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
            self::clearFileCache();
        }
    }

    private static function clearFileCache(): void
    {
        $dir = CACHE_PATH . DIRECTORY_SEPARATOR . 'file_cache';
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
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
            'microscopyTestId' => [(string) $fromClient, (string) $changed, (string) $cleared],
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
     * list is shown as an extra option. A page from before the row ids, which could not
     * show it and posts it blank, leaves the sample's rows alone.
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
        // A stored "0" is shown selected, so the page posts it back.
        self::assertStringContainsString(
            "value='0' selected='selected'",
            ContainerRegistry::get(CommonService::class)->generateSelectOptions(
                TbTestsService::microscopyOptions(['No AFB' => 'No AFB'], '0'),
                '0',
                '-- Select --'
            )
        );
    }

    /**
     * The page posts the id of the row each slot showed. A row deleted while the page
     * was open is not written back onto the next row, and a row added meanwhile stays.
     */
    #[RunInSeparateProcess]
    public function testEachSlotSavesTheRowItShowed(): void
    {
        $tbId = $this->seedTb();
        $first = $this->seedTbTest($tbId, ['actual_no' => '1', 'test_result' => 'No AFB']);
        $second = $this->seedTbTest($tbId, ['actual_no' => '2', 'test_result' => '1+']);
        LegacyAppHarness::db()->rawQuery('DELETE FROM tb_tests WHERE tb_test_id = ?', [$first]);
        $addedMeanwhile = $this->seedTbTest($tbId, ['actual_no' => '5', 'test_result' => '3+']);

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'testResult' => ['No AFB', '2+', ''],
            'actualNo' => ['1', '2', ''],
            'microscopyTestId' => [(string) $first, (string) $second, ''],
        ]));

        self::assertSame(
            [[$second, '2', '2+'], [$addedMeanwhile, '5', '3+']],
            array_map(
                static fn($t) => [(int) $t['tb_test_id'], $t['actual_no'], $t['test_result']],
                $this->tbTests($tbId)
            )
        );
    }

    /** On a page that shows it, a result outside the list can be cleared. */
    #[RunInSeparateProcess]
    public function testAShownResultOutsideTheListCanBeCleared(): void
    {
        $tbId = $this->seedTb();
        $row = $this->seedTbTest($tbId, ['lab_id' => null, 'actual_no' => '1', 'test_result' => 'Negative']);

        $this->drive('/tb/results/tb-update-result-helper.php', [
            'tbSampleId' => (string) $tbId,
            'labId' => '1',
            'instanceId' => 'test',
            'isSampleRejected' => 'no',
            'tbTestsRequested' => '',
            'testResult' => ['', '', ''],
            'actualNo' => ['1', '', ''],
            'microscopyTestId' => [(string) $row, '', ''],
        ]);

        self::assertSame([[$row, null]], array_map(
            static fn($t) => [(int) $t['tb_test_id'], $t['test_result']],
            $this->tbTests($tbId)
        ));
    }

    /** A result that is not kept is not queued to go back to the source. */
    #[RunInSeparateProcess]
    public function testAResultNotKeptIsNotSentToTheSource(): void
    {
        $tbId = $this->seedTb();

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'isResultFinalized' => 'no',
            'finalResult' => '0',
        ]));

        self::assertNull($this->formTb($tbId)['result_sent_to_source']);
    }

    /** Acting as one lab, the form never changes or deletes another lab's row. */
    #[RunInSeparateProcess]
    public function testAnotherLabsRowIsLeftAlone(): void
    {
        LegacyAppHarness::withSession(['labId' => 1, 'instance' => ['type' => 'vluser']]);
        $tbId = $this->seedTb();
        $otherLab = $this->seedTbTest($tbId, ['lab_id' => 2, 'actual_no' => '1', 'test_result' => '1+']);

        $own = $this->seedTbTest($tbId, ['lab_id' => 1, 'actual_no' => '2', 'test_result' => 'No AFB']);
        $tbTests = ContainerRegistry::get(TbTestsService::class);

        // Slot 1 names the other lab's row, slot 2 the lab's own; slot 3 adds a row.
        $tbTests->saveMicroscopyRows(
            $tbId,
            ['', '2+', '1+'],
            ['', '2', '3'],
            2,
            [(string) $otherLab, (string) $own, '']
        );

        self::assertSame(
            [$own],
            array_map(
                static fn($t) => (int) $t['tb_test_id'],
                TbTestsService::microscopyRowsForLab([
                    ['tb_test_id' => $otherLab, 'lab_id' => 2],
                    ['tb_test_id' => $own, 'lab_id' => 1],
                ])
            )
        );
        self::assertSame(
            [[$otherLab, '2', '1+'], [$own, '1', '2+'], [$own + 1, '1', '1+']],
            array_map(
                static fn($t) => [(int) $t['tb_test_id'], (string) $t['lab_id'], $t['test_result']],
                $this->tbTests($tbId)
            )
        );
    }

    /**
     * Every single-result edit and result form posts the id of the row behind each
     * slot, and a blank one for an empty slot; without them a save falls back to
     * matching by position.
     */
    public function testEveryFormPostsTheRowIdOfEachSlot(): void
    {
        foreach (
            [
                'requests/forms/edit-burkina-faso.php', 'requests/forms/edit-sierraleone.php',
                'requests/forms/edit-southsudan.php', 'results/forms/update-burkina-faso.php',
                'results/forms/update-sierraleone.php', 'results/forms/update-southsudan.php',
            ] as $form
        ) {
            $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/tb/' . $form);
            $savedSlot = '<input type="hidden" name="microscopyTestId[]" '
                . 'value="<?= (int) $tbTestInfo[$no - 1][\'tb_test_id\']; ?>" />';
            self::assertSame(1, substr_count($source, $savedSlot), $form);
            self::assertSame(
                1,
                substr_count($source, '<input type="hidden" name="microscopyTestId[]" value="" />'),
                $form
            );
            self::assertSame(2, substr_count($source, 'name="actualNo[]"'), $form);
            // The slots show only the rows the save may change.
            $filter = '$tbTestInfo = \\App\\Services\\TbTestsService::microscopyRowsForLab($tbTestInfo);';
            self::assertSame(1, substr_count($source, $filter), $form);
        }
    }

    /**
     * The request forms show the results only to a user who may enter them or who is
     * not at a collection site. A post from anyone else changes no result.
     */
    #[RunInSeparateProcess]
    public function testACollectionSiteUserWithoutResultAccessChangesNoResult(): void
    {
        LegacyAppHarness::withSession(['accessType' => 'collection-site', 'roleCode' => 'clinic', 'privileges' => []]);
        $tbId = $this->seedTb();
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_tb SET result = 'MTB detected', result_status = 8,
                sample_dispatched_datetime = '2026-09-15 12:00:00', recommended_corrective_action = 2
                WHERE tb_id = ?",
            [$tbId]
        );
        $row = $this->seedTbTest($tbId, ['actual_no' => '1', 'test_result' => '1+']);

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'finalResult' => 'MTB not detected',
            'xPertMTMResult' => 'MTB not detected',
            'labComments' => 'changed',
            'isSampleRejected' => 'yes',
            'correctiveAction' => '5',
            'testResult' => ['', '', ''],
            'actualNo' => ['', '', ''],
            'microscopyTestId' => [(string) $row, '', ''],
            'patientId' => 'P-2',
        ]));

        $sample = $this->formTb($tbId);
        self::assertSame('P-2', $sample['patient_id']);
        self::assertSame('MTB detected', $sample['result']);
        self::assertNull($sample['xpert_mtb_result']);
        self::assertSame(8, (int) $sample['result_status']);
        self::assertNotSame('yes', $sample['is_sample_rejected']);
        self::assertSame('2026-09-15 12:00:00', $sample['sample_dispatched_datetime']);
        self::assertSame(2, (int) $sample['recommended_corrective_action']);
        self::assertNotSame('changed', $sample['lab_tech_comments']);
        self::assertSame([[$row, '1+']], array_map(
            static fn($t) => [(int) $t['tb_test_id'], $t['test_result']],
            $this->tbTests($tbId)
        ));
    }

    /**
     * A form that keeps the received date in the section it hides from a collection
     * site posts none: the saved date stays.
     */
    #[RunInSeparateProcess]
    public function testAHiddenReceivedDateIsKept(): void
    {
        LegacyAppHarness::withSession(['accessType' => 'collection-site', 'roleCode' => 'clinic', 'privileges' => []]);
        $tbId = $this->seedTb();
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_tb SET sample_received_at_lab_datetime = '2026-09-16 10:00:00' WHERE tb_id = ?",
            [$tbId]
        );
        $post = self::requestPost($tbId, ['patientId' => 'P-3']);
        unset($post['isSampleRejected']);

        $this->drive('/tb/requests/tb-edit-request-helper.php', $post);

        $sample = $this->formTb($tbId);
        self::assertSame('P-3', $sample['patient_id']);
        self::assertSame('2026-09-16 10:00:00', $sample['sample_received_at_lab_datetime']);
        self::assertSame(6, (int) $sample['result_status']);
    }

    /** Saving a request with "not rejected" and no result keeps the sample where it is. */
    #[RunInSeparateProcess]
    public function testARequestSaveDoesNotSendASampleBack(): void
    {
        $tbId = $this->seedTb();
        LegacyAppHarness::db()->rawQuery('UPDATE form_tb SET result_status = 8 WHERE tb_id = ?', [$tbId]);

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, ['patientId' => 'P-4']));

        self::assertSame(8, (int) $this->formTb($tbId)['result_status']);
    }

    /** Lifting a rejection on the request still moves the sample back to the lab. */
    #[RunInSeparateProcess]
    public function testLiftingARejectionOnTheRequestMovesTheSample(): void
    {
        $tbId = $this->seedTb();
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_tb SET result_status = 4, is_sample_rejected = 'yes' WHERE tb_id = ?",
            [$tbId]
        );

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, ['patientId' => 'P-4']));

        self::assertSame(6, (int) $this->formTb($tbId)['result_status']);
    }

    /** On an STS the Rwanda request form shows no results, so its save takes none. */
    #[RunInSeparateProcess]
    public function testTheRwandaRequestFormOnAnStsTakesNoResult(): void
    {
        LegacyAppHarness::withSession(['instance' => ['type' => 'remoteuser']]);
        LegacyAppHarness::db()->insert('global_config', ['name' => 'vl_form', 'value' => '7']);
        // Global config is file-cached across processes.
        self::clearFileCache();
        $tbId = $this->seedTb();

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'isResultFinalized' => 'yes',
            'finalResult' => 'MTB detected',
            'patientId' => 'P-5',
        ]));

        $sample = $this->formTb($tbId);
        self::assertSame('P-5', $sample['patient_id']);
        self::assertNull($sample['result']);
    }

    /** The Sierra Leone form keeps the received date in that section: a hidden one is not taken. */
    #[RunInSeparateProcess]
    public function testAHiddenSierraLeoneReceivedDateIsNotTaken(): void
    {
        LegacyAppHarness::withSession(['accessType' => 'collection-site', 'roleCode' => 'clinic', 'privileges' => []]);
        LegacyAppHarness::db()->insert('global_config', ['name' => 'vl_form', 'value' => '2']);
        self::clearFileCache();
        $tbId = $this->seedTb();
        LegacyAppHarness::db()->rawQuery(
            "UPDATE form_tb SET sample_received_at_lab_datetime = '2026-09-16 10:00:00' WHERE tb_id = ?",
            [$tbId]
        );

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'sampleReceivedDate' => '20-Sep-2026 10:00',
        ]));

        self::assertSame('2026-09-16 10:00:00', $this->formTb($tbId)['sample_received_at_lab_datetime']);
    }

    /**
     * The add form draws empty slots, so none stands for a row already on the sample --
     * one an import added after the sample was created -- and a form posted twice does
     * not add its rows twice.
     */
    #[RunInSeparateProcess]
    public function testTheAddFormNeitherDeletesAnUnseenRowNorAddsItsRowsTwice(): void
    {
        $tbId = $this->seedTb();
        $imported = $this->seedTbTest($tbId, ['lab_id' => null, 'actual_no' => '9', 'test_result' => '3+']);
        $this->seedTbTest($tbId, ['actual_no' => '1', 'test_result' => 'No AFB']);

        $this->drive('/tb/requests/tb-add-request-helper.php', self::requestPost($tbId, [
            'testResult' => ['No AFB', '', ''],
            'actualNo' => ['1', '', ''],
        ]));

        self::assertSame(
            [[$imported, '9', '3+'], [$imported + 1, '1', 'No AFB']],
            array_map(
                static fn($t) => [(int) $t['tb_test_id'], $t['actual_no'], $t['test_result']],
                $this->tbTests($tbId)
            )
        );
    }

    /** A page from before the row ids cannot say which row a slot showed: rows stay. */
    #[RunInSeparateProcess]
    public function testAPageWithoutRowIdsLeavesTheRowsAlone(): void
    {
        $tbId = $this->seedTb();
        $row = $this->seedTbTest($tbId, ['actual_no' => '1', 'test_result' => '1+']);

        $this->drive('/tb/requests/tb-edit-request-helper.php', self::requestPost($tbId, [
            'testResult' => ['', '2+', ''],
            'actualNo' => ['', '2', ''],
        ]));

        self::assertSame([[$row, '1+']], array_map(
            static fn($t) => [(int) $t['tb_test_id'], $t['test_result']],
            $this->tbTests($tbId)
        ));
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
            'microscopyTestId' => [(string) $fromClient, '', ''],
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
