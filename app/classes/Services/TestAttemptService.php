<?php

namespace App\Services;

use Throwable;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;

/**
 * Retains superseded test results so a failure survives the re-test that replaces it.
 *
 * Every form_* table holds exactly one result, so re-testing a failed sample -- routine
 * practice, since labs draw more specimen than a single run needs -- overwrites the
 * failure and loses it. This is the single point where the outgoing result is preserved,
 * and every path that can replace a result calls it BEFORE writing.
 *
 * One row per completed testing attempt. attempt_number is the re-test signal; there is
 * deliberately no is_retest flag, because sample_reordered already means something
 * different -- that a fresh specimen was requested as corrective action, which produces a
 * NEW request row rather than a second attempt on this one. Keeping the two apart is what
 * lets reporting distinguish a sample re-tested in-house from one abandoned for a new draw.
 *
 * Per-module details (result column, platform column, child result table, columns cleared
 * on retest) come from TestsService, so adding a module means editing one map.
 *
 * @see sys/migrations/5.6.2.sql for the table and the rationale behind it.
 */
final class TestAttemptService
{
    /** Why the archived attempt stopped being the live result. */
    public const BY_RETEST       = 'retest';
    public const BY_RESULT_EDIT  = 'result-edit';
    public const BY_IMPORT       = 'import';
    public const BY_INTERFACE    = 'interface';
    public const BY_API          = 'api';
    public const BY_BULK_STATUS  = 'bulk-status';

    public function __construct(private ?DatabaseService $db = null)
    {
        $this->db ??= ContainerRegistry::get(DatabaseService::class);
    }

    /**
     * Read sample ids out of a retest request.
     *
     * The Failed Results grid posts either a single base64-encoded id, or a raw array under
     * bulkIds. Every module previously interpolated those straight into
     * "... IN (" . implode(",", $_POST[...]) . ")", so the bulk path took unvalidated input
     * into SQL. Casting to int here means the value can only ever be a number, and the
     * check lives in one place rather than seven.
     *
     * @return int[]
     */
    public static function sampleIdsFromRequest(array $post, string $field): array
    {
        $raw = (!empty($post['bulkIds']) && isset($post[$field]) && is_array($post[$field]))
            ? $post[$field]
            : [base64_decode((string) ($post[$field] ?? ''))];

        return self::positiveIds($raw);
    }

    /**
     * Preserve the current result of one or more samples before it is replaced.
     *
     * Call this BEFORE the update that overwrites the result, inside the same transaction,
     * so an archive and its overwrite either both land or neither does.
     *
     * Archiving is skipped for a sample with nothing worth keeping -- no result and no
     * failed/rejected status -- so ordinary first-time result entry adds no rows.
     *
     * @param string    $testType     Key from TestsService::getTestTypes().
     * @param int|int[] $recordIds    form_* primary key(s).
     * @param string    $supersededBy One of the BY_* constants.
     * @param string|null $reason     Operator-supplied reason, where the path collects one.
     * @return int Number of attempts archived.
     */
    public function archive(string $testType, int|array $recordIds, string $supersededBy = self::BY_RETEST, ?string $reason = null): int
    {
        $testType = trim($testType);
        $ids = self::positiveIds((array) $recordIds);

        if ($ids === []) {
            return 0;
        }

        $module = TestsService::getTestTypes()[$testType] ?? null;
        if (empty($module['tableName']) || empty($module['primaryKey'])) {
            return 0;
        }

        $table = $module['tableName'];
        $primaryKey = $module['primaryKey'];
        $resultColumn = $module['resultColumn'] ?? 'result';
        $platformColumn = $module['testPlatformColumn'] ?? null;
        $child = TestsService::getChildResultTable($testType);

        $archived = 0;

        try {
            $placeholders = implode(',', array_fill(0, \count($ids), '?'));
            $rows = $this->db->rawQuery(
                "SELECT * FROM `$table` WHERE `$primaryKey` IN ($placeholders)",
                $ids
            ) ?: [];

            foreach ($rows as $row) {
                $recordId = (int) ($row[$primaryKey] ?? 0);
                if ($recordId <= 0 || !$this->isWorthArchiving($row, $resultColumn)) {
                    continue;
                }

                $childRows = $this->fetchChildRows($child, $recordId);

                if ($this->insertAttempt($testType, $table, $recordId, $row, $childRows, $resultColumn, $platformColumn, $supersededBy, $reason)) {
                    $archived++;
                }
            }
        } catch (Throwable $e) {
            // Never let archiving block the clinical workflow: a lab must still be able to
            // re-test. Losing an archive is logged rather than thrown, because refusing the
            // re-test would be the worse failure.
            error_log('TestAttemptService::archive failed for ' . $testType . ': ' . $e->getMessage());
            return $archived;
        }

        return $archived;
    }

    /**
     * Send failed samples back for re-testing: retain the failing attempt, then clear the
     * result so the sample re-enters the testing queue.
     *
     * This lived as seven near-identical copies of failed-results-retest.php, which is how
     * TB and Hepatitis came to file their history under test_type 'vl' and how each module
     * drifted on which columns it cleared.
     *
     * Ordering is the point of this method: the archive happens BEFORE the wipe and before
     * any child rows are deleted, all in one transaction. The previous code wiped first and
     * archived afterwards with no transaction, so a failure in between destroyed the result
     * and recorded nothing in its place.
     *
     * @param int[] $sampleIds form_* primary keys.
     * @param int   $status    Status to return the samples to.
     * @return int Rows reset.
     */
    public function resetForRetest(string $testType, array $sampleIds, int $status): int
    {
        $ids = self::positiveIds($sampleIds);
        $module = TestsService::getTestTypes()[$testType] ?? null;

        if ($ids === [] || empty($module['tableName']) || empty($module['clearOnRetest'])) {
            return 0;
        }

        $this->db->beginTransaction();

        try {
            $this->archive($testType, $ids, self::BY_RETEST);

            $child = TestsService::getChildResultTable($testType);
            if ($child !== null && TestsService::deletesChildOnRetest($testType)) {
                $this->db->where($child['key'], $ids, 'IN');
                $this->db->delete($child['table']);
            }

            $clear = array_fill_keys(TestsService::getColumnsClearedOnRetest($testType), null);
            $clear['result_status'] = $status;

            $this->db->where($module['primaryKey'], $ids, 'IN');
            $this->db->update($module['tableName'], $clear);

            $this->db->commitTransaction();
        } catch (Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }

        return \count($ids);
    }

    /**
     * A row is worth archiving when it carries a result, or when its status records a
     * failure or rejection. The status check matters on its own: a rejected sample has its
     * result NULLed at save time, so status is the only remaining evidence it was handled.
     */
    private function isWorthArchiving(array $row, string $resultColumn): bool
    {
        if (trim((string) ($row[$resultColumn] ?? '')) !== '') {
            return true;
        }

        $status = (int) ($row['result_status'] ?? 0);
        return $status === \SAMPLE_STATUS\TEST_FAILED || $status === \SAMPLE_STATUS\REJECTED;
    }

    /**
     * Child result rows (tb_tests, covid19_tests, generic_test_results) are captured into
     * the snapshot because the TB and Covid-19 re-test paths DELETE them outright and
     * neither table carries audit triggers -- without this they are unrecoverable.
     *
     * @param array{table: string, key: string}|null $child
     */
    private function fetchChildRows(?array $child, int $recordId): array
    {
        if ($child === null) {
            return [];
        }

        return $this->db->rawQuery(
            "SELECT * FROM `{$child['table']}` WHERE `{$child['key']}` = ?",
            [$recordId]
        ) ?: [];
    }

    private function insertAttempt(
        string $testType,
        string $table,
        int $recordId,
        array $row,
        array $childRows,
        string $resultColumn,
        ?string $platformColumn,
        string $supersededBy,
        ?string $reason
    ): bool {
        $status = isset($row['result_status']) && $row['result_status'] !== ''
            ? (int) $row['result_status']
            : null;

        $snapshot = ['row' => $row];
        if ($childRows !== []) {
            $snapshot['childResults'] = $childRows;
        }

        // Migration 5.6.2 levelled reason_for_failure, instrument_id, lot_number and
        // lot_expiration_date across all seven modules, so the shape is uniform. Reads stay
        // null-tolerant so a column missing mid-upgrade archives as null rather than throwing.
        $data = [
            'test_type'              => $testType,
            'form_table'             => $table,
            'record_id'              => $recordId,
            'attempt_number'         => $this->nextAttemptNumber($table, $recordId),
            'superseded_by'          => $supersededBy,

            'lab_id'                 => $this->intOrNull($row['lab_id'] ?? null),
            'facility_id'            => $this->intOrNull($row['facility_id'] ?? null),
            'sample_code'            => $row['sample_code'] ?? null,
            'remote_sample_code'     => $row['remote_sample_code'] ?? null,
            'batch_id'               => $row['sample_batch_id'] ?? null,

            'result'                 => $row[$resultColumn] ?? null,
            'result_status'          => $status,
            'result_failed'          => $status === \SAMPLE_STATUS\TEST_FAILED ? 1 : 0,
            'result_rejected'        => ($status === \SAMPLE_STATUS\REJECTED
                                          || ((string) ($row['is_sample_rejected'] ?? '')) === 'yes') ? 1 : 0,
            'reason_for_failure'     => $row['reason_for_failure'] ?? null,

            'sample_tested_datetime' => $row['sample_tested_datetime'] ?? null,
            'tested_by'              => $row['tested_by'] ?? null,
            'test_platform'          => $platformColumn !== null ? ($row[$platformColumn] ?? null) : null,
            'instrument_id'          => $row['instrument_id'] ?? null,
            'lot_number'             => $row['lot_number'] ?? null,
            'lot_expiration_date'    => $row['lot_expiration_date'] ?? null,

            'sample_reordered'       => $row['sample_reordered'] ?? null,

            'attempt_data'           => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'change_reason'          => ($reason !== null && trim($reason) !== '') ? trim($reason) : null,

            'created_datetime'       => DateUtility::getCurrentDateTime(),
            'created_by'             => $_SESSION['userId'] ?? null,
        ];

        return (bool) $this->db->insert('test_result_attempts', $data);
    }

    /**
     * Attempts are numbered per record. The unique key on
     * (form_table, record_id, attempt_number) means a duplicate archive in the same request
     * is rejected by the database rather than silently doubling the count.
     */
    private function nextAttemptNumber(string $table, int $recordId): int
    {
        $row = $this->db->rawQueryOne(
            "SELECT COALESCE(MAX(`attempt_number`), 0) + 1 AS `next`
               FROM `test_result_attempts`
              WHERE `form_table` = ? AND `record_id` = ?",
            [$table, $recordId]
        );

        return (int) ($row['next'] ?? 1);
    }

    /** @return int[] */
    private static function positiveIds(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $values),
            static fn($id) => $id > 0
        )));
    }

    private function intOrNull($value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    /**
     * Attempts previously recorded for a sample, oldest first. Used by the attempt history
     * shown on the sample page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAttemptsForSample(string $testType, int $recordId): array
    {
        $module = TestsService::getTestTypes()[$testType] ?? null;
        if (empty($module['tableName']) || $recordId <= 0) {
            return [];
        }

        return $this->db->rawQuery(
            "SELECT * FROM `test_result_attempts`
              WHERE `form_table` = ? AND `record_id` = ?
           ORDER BY `attempt_number` ASC",
            [$module['tableName'], $recordId]
        ) ?: [];
    }
}
