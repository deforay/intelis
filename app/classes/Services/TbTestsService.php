<?php

namespace App\Services;

use RuntimeException;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\ContainerRegistry;

/**
 * The per-test TB form keeps one tb_tests row per test, and form_tb carries the
 * details of the sample's latest test.
 *
 * Rows are saved in place. A card the form posts back updates its own row, a new
 * card adds one, and a row is deleted only when the user removed its card. A row
 * the form did not post -- one an analyzer added while the page was open -- is
 * left alone. The form used to delete every row and insert what it posted, which
 * lost those rows and gave every test a new id on each save.
 */
final class TbTestsService
{
    /**
     * Everything a TB request form posts from its result section: a request save takes
     * none of it from a user the form does not show that section to. The received date
     * is added for the one form that keeps it there; the others show it to everyone.
     */
    private const array REQUEST_FORM_RESULT_KEYS = [
        'finalResult', 'isResultFinalized', 'testResult', 'actualNo', 'microscopyTestId', 'deletedTestIds',
        'tbLamResult', 'xPertMTMResult', 'cultureResult', 'identicationResult', 'drugMGITResult', 'drugLPAResult',
        'xpertDateOfResult', 'cultureDateOfResult', 'tbLamDateOfResult', 'identificationDateOfResult',
        'drugMGITDateOfResult', 'drugLPADateOfResult', 'resultDispatchedDatetime', 'reviewedBy', 'reviewedOn',
        'approvedBy', 'approvedOn', 'sampleTestedDateTime', 'testedBy', 'resultDate', 'labComments',
        'sampleDispatchedDate', 'isSampleRejected', 'sampleRejectionReason', 'newRejectionReason', 'rejectionDate',
        'correctiveAction',
    ];

    /**
     * Whether the instance's TB request form shows its result section to this user,
     * by the rule the forms themselves use: to a user who may enter results or who is
     * not at a collection site, and on the Rwanda form never on an STS.
     */
    public static function requestFormShowsResults(CommonService $general): bool
    {
        if ($general->isSTSInstance() && (int) $general->getGlobalConfig('vl_form') === \COUNTRY\RWANDA) {
            return false;
        }
        return _isAllowed('/tb/results/tb-update-result.php')
            || ($_SESSION['accessType'] ?? null) !== 'collection-site';
    }

    /**
     * A request form's post without its result section when the form hid that section
     * from this user: such a post carries no results.
     *
     * @param array<array-key, mixed> $post
     * @return array<array-key, mixed>
     */
    public static function withoutHiddenResults(CommonService $general, array $post): array
    {
        if (self::requestFormShowsResults($general)) {
            return $post;
        }
        $hidden = self::REQUEST_FORM_RESULT_KEYS;
        if ((int) $general->getGlobalConfig('vl_form') === \COUNTRY\SIERRA_LEONE) {
            $hidden[] = 'sampleReceivedDate';
        }
        return array_diff_key($post, array_flip($hidden));
    }

    public function __construct(private ?DatabaseService $db = null)
    {
        $this->db ??= ContainerRegistry::get(DatabaseService::class);
    }

    /**
     * Save the test cards a form posted for one sample.
     *
     * Call inside the caller's transaction. Throws when a row cannot be written.
     *
     * @param array<string, mixed> $cards          The posted testResult[field][] arrays.
     * @param array<int, mixed>    $deletedTestIds The posted deletedTestIds[]: saved tests whose card was removed.
     */
    public function saveCards(int $tbId, array $cards, array $deletedTestIds, ?string $userId): void
    {
        if ($tbId <= 0) {
            throw new RuntimeException('Cannot save TB tests without a sample');
        }

        // The sample's own rows. An id the form posts is only ever looked up here, so
        // it cannot reach another sample's test, and the change history is read from
        // the row rather than taken from the form.
        $existing = [];
        foreach ($this->db->rawQuery('SELECT * FROM tb_tests WHERE tb_id = ?', [$tbId]) ?: [] as $row) {
            $existing[(string) $row['tb_test_id']] = $row;
        }

        $kept = [];
        foreach ((array) ($cards['labId'] ?? []) as $key => $labId) {
            $testId = trim((string) ($cards['testId'][$key] ?? ''));
            $isSaved = $testId !== '' && isset($existing[$testId]);
            // A new card without a lab is a blank card. A saved test posted without
            // one keeps its own lab: the edits are saved rather than dropped.
            if (empty($labId)) {
                if (!$isSaved) {
                    continue;
                }
                $labId = $existing[$testId]['lab_id'];
            }

            $history = MiscUtility::parseResultChangeHistory(
                $isSaved ? ($existing[$testId]['reason_for_result_change'] ?? null) : null
            );
            $reason = trim((string) ($cards['reasonForChange'][$key] ?? ''));
            if ($reason !== '') {
                $history[] = ['usr' => $userId, 'dtime' => DateUtility::getCurrentDateTime(), 'msg' => $reason];
            }

            $row = [
                'tb_id' => $tbId,
                'lab_id' => $labId,
                'specimen_type' => $cards['specimenType'][$key] ?? null,
                'sample_received_at_lab_datetime' => self::dateTime($cards['sampleReceivedDate'][$key] ?? null),
                'test_type' => $cards['testType'][$key] ?? null,
                'test_result' => $cards['testResult'][$key] ?? null,
                'sample_tested_datetime' => self::dateTime($cards['sampleTestedDateTime'][$key] ?? null),
                'tested_by' => $cards['testedBy'][$key] ?? null,
                'result_reviewed_by' => $cards['reviewedBy'][$key] ?? null,
                'result_reviewed_datetime' => self::dateTime($cards['reviewedOn'][$key] ?? null),
                'result_approved_by' => $cards['approvedBy'][$key] ?? null,
                'result_approved_datetime' => self::dateTime($cards['approvedOn'][$key] ?? null),
                'revised_by' => $cards['revisedBy'][$key] ?? null,
                'revised_on' => self::dateTime($cards['revisedOn'][$key] ?? null),
                'reason_for_result_change' => $history !== [] ? json_encode($history) : null,
                'comments' => $cards['comments'][$key] ?? null,
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            ];

            if ($isSaved) {
                $this->db->where('tb_test_id', (int) $testId);
                $this->db->where('tb_id', $tbId);
                $written = $this->db->update('tb_tests', $row);
                $kept[$testId] = true;
            } else {
                $written = $this->db->insert('tb_tests', $row);
            }
            if (!$written) {
                throw new RuntimeException("Could not save a test on TB sample $tbId: " . $this->db->getLastError());
            }
        }

        // Only a test whose card the user removed. Its audit trigger keeps a copy.
        foreach (array_unique(array_map('strval', $deletedTestIds)) as $testId) {
            if (!isset($existing[$testId]) || isset($kept[$testId])) {
                continue;
            }
            $this->db->where('tb_test_id', (int) $testId);
            $this->db->where('tb_id', $tbId);
            if (!$this->db->delete('tb_tests')) {
                throw new RuntimeException("Could not delete test $testId on TB sample $tbId");
            }
        }
    }

    /**
     * Save the microscopy rows a single-result form posted (testResult[], actualNo[]).
     *
     * Each slot the page drew posts the id of the row it showed (microscopyTestId[],
     * blank for an empty slot): a changed slot updates its row, a slot the user
     * emptied deletes it, and a filled empty slot adds a row -- unless a row with the
     * same number and result is already there, as when a form is posted twice. A row
     * no slot names -- past the slots the page drew, added while it was open, or
     * another lab's -- is left alone, as is a slot whose row is no longer on the
     * sample. Rows posted back unchanged are not touched: an unchanged row the API
     * client sent stays the client's, so its next post matches it instead of adding
     * it again. The forms used to delete every row and insert all their slots, blank
     * ones included.
     *
     * A page from before the ids ($testIds null) cannot say which row a slot showed,
     * so it only adds rows to a sample that has none; the rows already there are left
     * as they are.
     *
     * Call inside the caller's transaction. Throws when a row cannot be written.
     *
     * Returns how many filled slots named a row that is gone -- removed by a retest or a
     * sync while the page was open. Those values are not saved: bringing the row back
     * could revive an attempt the lab just replaced, so the caller tells the user instead.
     *
     * @param array<array-key, mixed>      $results   The posted testResult[].
     * @param array<array-key, mixed>      $actualNos The posted actualNo[].
     * @param array<array-key, mixed>|null $testIds   The posted microscopyTestId[], null when not posted.
     * @return int Filled slots not saved because their row is gone.
     */
    public function saveMicroscopyRows(
        int $tbId,
        array $results,
        array $actualNos,
        mixed $labId,
        ?array $testIds = null
    ): int {
        if ($tbId <= 0) {
            throw new RuntimeException('Cannot save TB tests without a sample');
        }
        // Acting as one lab, another lab's rows are that lab's: the form neither reads,
        // changes nor deletes them. A row the form adds belongs to the lab acting, not
        // to a lab the sample was referred from.
        $general = ContainerRegistry::get(CommonService::class);
        $ownLabId = $general->getOwnLabId();
        $labScope = $general->labScopeWhere('');
        $existing = $this->db->rawQuery(
            'SELECT * FROM tb_tests WHERE tb_id = ?' . ($labScope !== '' ? " AND $labScope" : '')
                . ' ORDER BY tb_test_id',
            [$tbId]
        ) ?: [];
        $existing = self::microscopyRowsForLab($existing);
        if ($testIds === null) {
            if ($existing !== []) {
                return 0;
            }
            $testIds = [];
        }
        $byId = array_column($existing, null, 'tb_test_id');
        $newRowLabId = $ownLabId ?? (empty($labId) ? null : $labId);
        $actualNos = array_values($actualNos);
        $testIds = array_map(
            static fn($id): string => is_scalar($id) ? trim((string) $id) : '',
            array_values($testIds)
        );

        $claimed = [];
        $notSaved = 0;
        foreach (array_values($results) as $slot => $result) {
            $result = is_scalar($result) ? trim((string) $result) : '';
            $actualNo = is_scalar($actualNos[$slot] ?? null) ? trim((string) $actualNos[$slot]) : '';
            $testId = $testIds[$slot] ?? '';
            if ($testId !== '') {
                if (!isset($byId[$testId])) {
                    if ($result !== '' || $actualNo !== '') {
                        $notSaved++;
                    }
                    continue;
                }
                $row = $byId[$testId];
                $claimed[$testId] = true;
            } else {
                if ($result === '' && $actualNo === '') {
                    continue;
                }
                $row = null;
                foreach ($existing as $candidate) {
                    $candidateId = (string) $candidate['tb_test_id'];
                    if (
                        !isset($claimed[$candidateId]) && !in_array($candidateId, $testIds, true)
                        && (string) ($candidate['actual_no'] ?? '') === $actualNo
                        && (string) ($candidate['test_result'] ?? '') === $result
                    ) {
                        $claimed[$candidateId] = true;
                        continue 2;
                    }
                }
            }

            if ($row === null) {
                $written = $this->db->insert('tb_tests', [
                    'tb_id' => $tbId,
                    'lab_id' => $newRowLabId,
                    'actual_no' => $actualNo === '' ? null : $actualNo,
                    'test_result' => $result === '' ? null : $result,
                    'updated_datetime' => DateUtility::getCurrentDateTime(),
                ]);
            } elseif ($result === '' && $actualNo === '') {
                $this->db->where('tb_test_id', (int) $row['tb_test_id']);
                $written = $this->db->delete('tb_tests');
            } elseif (
                $result !== (string) ($row['test_result'] ?? '')
                || $actualNo !== (string) ($row['actual_no'] ?? '')
            ) {
                $this->db->where('tb_test_id', (int) $row['tb_test_id']);
                $written = $this->db->update('tb_tests', [
                    // A row the lab changed is the lab's.
                    'lab_id' => $row['lab_id'] ?? $newRowLabId,
                    'actual_no' => $actualNo === '' ? null : $actualNo,
                    'test_result' => $result === '' ? null : $result,
                    'updated_datetime' => DateUtility::getCurrentDateTime(),
                ]);
            } else {
                continue;
            }
            if (!$written) {
                throw new RuntimeException("Could not save a test on TB sample $tbId: " . $this->db->getLastError());
            }
        }
        return $notSaved;
    }

    /** The warning for microscopy results saveMicroscopyRows() could not save; '' when none. */
    public static function microscopyNotSavedMessage(int $notSaved): string
    {
        if ($notSaved <= 0) {
            return '';
        }
        if ($notSaved === 1) {
            return _translate(
                '1 microscopy result was not saved because it was removed from this sample'
                    . ' while the form was open (for example by a retest).'
                    . ' Open the sample again to check the microscopy results.'
            );
        }
        return sprintf(_translate(
            '%d microscopy results were not saved because they were removed from this sample'
                . ' while the form was open (for example by a retest).'
                . ' Open the sample again to check the microscopy results.'
        ), $notSaved);
    }

    /**
     * The microscopy rows a single-result form shows and may change: acting as one lab,
     * that lab's rows and the unassigned ones, never another lab's.
     *
     * @param list<array<string, mixed>> $rows tb_tests rows, oldest first.
     * @return list<array<string, mixed>>
     */
    public static function microscopyRowsForLab(array $rows): array
    {
        $ownLabId = ContainerRegistry::get(CommonService::class)->getOwnLabId();
        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => $ownLabId === null || empty($row['lab_id'])
                || (int) $row['lab_id'] === $ownLabId
        ));
    }

    /**
     * A microscopy slot's options, with the row's stored result added when the
     * form's list does not offer it, so the page shows it and posts it back.
     *
     * @param array<string, string> $options
     * @return array<string, string>
     */
    public static function microscopyOptions(array $options, mixed $stored): array
    {
        $stored = is_scalar($stored) ? trim((string) $stored) : '';
        if ($stored !== '' && !array_key_exists($stored, $options)) {
            $options[$stored] = $stored;
        }
        return $options;
    }

    /**
     * The sample's latest test: the one with the latest tested date, or when none has
     * been tested yet, the last one added. Empty when the sample has no tests.
     *
     * @return array<string, mixed>
     */
    public function latestTest(int $tbId): array
    {
        return $this->db->rawQueryOne(
            'SELECT * FROM tb_tests WHERE tb_id = ?
                ORDER BY sample_tested_datetime IS NULL, sample_tested_datetime DESC, tb_test_id DESC
                LIMIT 1',
            [$tbId]
        ) ?: [];
    }

    /**
     * Save the test results an API client sent for one sample.
     *
     * The client knows nothing of tb_tests ids, only the test number (actualNo) and
     * its result, and its rows carry no lab. A result for a number it sent before
     * updates that row; a new one is added, and a result it already sent without a
     * number is not added twice. Rows with a lab -- the ones the lab entered or an
     * analyzer imported -- are never matched, changed or deleted: a client
     * re-posting its whole dataset used to wipe them on every post.
     *
     * @param array<array-key, mixed> $testResults The payload's testResults.
     */
    public function saveApiTests(int $tbId, array $testResults): void
    {
        if ($tbId <= 0) {
            return;
        }
        $existing = $this->db->rawQuery(
            'SELECT * FROM tb_tests WHERE tb_id = ? AND lab_id IS NULL ORDER BY tb_test_id',
            [$tbId]
        ) ?: [];

        $posted = [];
        foreach ($testResults as $test) {
            $result = is_array($test) ? trim((string) ($test['testResult'] ?? '')) : '';
            if ($result !== '') {
                $posted[] = ['actualNo' => trim((string) ($test['actualNo'] ?? '')), 'result' => $result];
            }
        }

        // Pair each posted test with a stored row: by number, then an unnumbered
        // test by its result, then any unnumbered test left over with an unnumbered
        // row left over, in order -- that is a changed result. A stored row answers
        // for one posted test at most, so two tests with the same result stay two.
        $pairs = array_fill(0, count($posted), null);
        $isUnnumbered = static fn(array $row): bool => (string) ($row['actual_no'] ?? '') === '';
        $passes = [
            static fn(array $test, array $row): bool => $test['actualNo'] !== ''
                && (string) $row['actual_no'] === $test['actualNo'],
            static fn(array $test, array $row): bool => $test['actualNo'] === '' && $isUnnumbered($row)
                && (string) $row['test_result'] === $test['result'],
            static fn(array $test, array $row): bool => $test['actualNo'] === '' && $isUnnumbered($row),
        ];
        foreach ($passes as $matches) {
            foreach ($posted as $i => $test) {
                if ($pairs[$i] !== null) {
                    continue;
                }
                foreach ($existing as $key => $row) {
                    if ($matches($test, $row)) {
                        $pairs[$i] = $row;
                        unset($existing[$key]);
                        break;
                    }
                }
            }
        }

        foreach ($posted as $i => $test) {
            $match = $pairs[$i];
            if ($match === null) {
                $row = [
                    'tb_id' => $tbId,
                    'actual_no' => $test['actualNo'] === '' ? null : $test['actualNo'],
                    'test_result' => $test['result'],
                    'updated_datetime' => DateUtility::getCurrentDateTime(),
                ];
                if (!$this->db->insert('tb_tests', $row)) {
                    throw new RuntimeException('Could not save a TB test: ' . $this->db->getLastError());
                }
            } elseif ((string) $match['test_result'] !== $test['result']) {
                $this->db->where('tb_test_id', $match['tb_test_id']);
                $this->db->update('tb_tests', [
                    'test_result' => $test['result'],
                    'updated_datetime' => DateUtility::getCurrentDateTime(),
                ]);
            }
        }
    }

    /**
     * The form_tb columns that describe the sample's latest test. Empty when the
     * sample has no tests, and without the received date when that test has none:
     * the caller leaves what is missing as it is on the sample.
     *
     * @return array<string, mixed>
     */
    public function latestTestColumns(int $tbId): array
    {
        return self::sampleColumnsOf($this->latestTest($tbId));
    }

    /**
     * @param array<string, mixed> $test A tb_tests row, or [] for none.
     * @return array<string, mixed>
     */
    public static function sampleColumnsOf(array $test): array
    {
        if ($test === []) {
            return [];
        }
        $columns = [
            'sample_tested_datetime' => $test['sample_tested_datetime'] ?? null,
            'tested_by' => $test['tested_by'] ?? null,
            'result_reviewed_by' => $test['result_reviewed_by'] ?? null,
            'result_reviewed_datetime' => $test['result_reviewed_datetime'] ?? null,
            'result_approved_by' => $test['result_approved_by'] ?? null,
            'result_approved_datetime' => $test['result_approved_datetime'] ?? null,
        ];
        // A test without a received date says nothing about when the sample arrived.
        if (!empty($test['sample_received_at_lab_datetime'])) {
            $columns['sample_received_at_lab_datetime'] = $test['sample_received_at_lab_datetime'];
        }
        return $columns;
    }

    private static function dateTime(mixed $value): ?string
    {
        return DateUtility::isoDateFormat($value, true);
    }
}
