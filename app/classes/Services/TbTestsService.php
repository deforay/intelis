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

        // Only a test whose card the user removed. Each is kept in audit_log first,
        // as tb_tests has no audit triggers.
        foreach (array_unique(array_map('strval', $deletedTestIds)) as $testId) {
            if (!isset($existing[$testId]) || isset($kept[$testId])) {
                continue;
            }
            $this->attempts()->snapshotBeforeDelete('tb_tests', (int) $testId, $existing[$testId]);
            $this->db->where('tb_test_id', (int) $testId);
            $this->db->where('tb_id', $tbId);
            if (!$this->db->delete('tb_tests')) {
                throw new RuntimeException("Could not delete test $testId on TB sample $tbId");
            }
        }
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

    private function attempts(): TestAttemptService
    {
        return new TestAttemptService($this->db);
    }
}
