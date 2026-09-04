<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\SampleCountUtility;
use App\Utilities\SampleStatusUtility;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;

/**
 * Puts a sample's status back to what its own record proves, where the two have
 * come apart.
 *
 * One repair so far: a sample marked Accepted or Awaiting Approval that holds no
 * result at all. Neither status can be true of a row with nothing to show, and
 * the write paths that produce them are being closed separately -- this is what
 * puts right the ones already written, which nothing else does.
 *
 * It matters because the two halves of the app read a sample's position from
 * different columns. Every report that keys off the status counts these as
 * finished; every report that keys off the milestones -- the Sample Ageing
 * report, the Quality Monitoring module -- counts them as still pending. That is
 * how one lab comes to have two answers to the same question.
 *
 * Where a sample is put back to is read from the milestones, never guessed: a
 * lab receipt means it is at the lab, and nothing means it never left the
 * collection point.
 *
 * The test date is the one judgement, and it is made per row:
 *
 *   - A test date that is a byte-for-byte copy of the receipt or the collection
 *     stamp is not a record of testing. It came from the same write that
 *     invented the approval, and it goes -- because a great many surfaces still
 *     read a bare sample_tested_datetime as proof a sample was tested: the
 *     dashboard's samplesTested, the monthly threshold reports' totalReceived,
 *     the samplewise report, the API dashboard, and every result PDF, which
 *     substitutes it for a missing approval date and prints a signature beside
 *     it.
 *   - A test date that stands apart from both -- days after receipt, as a real
 *     bench date does -- is kept. The sample then reads as tested and waiting on
 *     the result somebody still has to enter, which is the truth of it.
 *
 * The approval and review stamps always go: you cannot approve or review a
 * result that does not exist. tested_by is left alone, because the PDFs need the
 * date as well as the name before they will print a signature, so clearing the
 * date is enough to disarm them.
 *
 * Every form_* table carries audit triggers, so each value cleared here is
 * recoverable from audit_log.
 */
final class SampleStatusRepairService
{
    /** Rows read, and repaired, per pass. */
    public const BATCH_SIZE = 200;

    /**
     * How long each batch waits before the next one is read. This runs on the
     * same database the labs are working on, and there is nothing urgent about
     * a backlog that has been sitting for months.
     */
    public const BATCH_PAUSE_MICROSECONDS = 200000;

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * Repairs one module's accepted-without-result rows.
     *
     * @param string        $testKey   registry key of the module to sweep
     * @param int|null      $sinceMonths  only touch samples registered or modified
     *                                    within this many months; null for every row
     * @param callable|null $progress  called with (repaired, datesCleared) after each batch
     *
     * @return array{repaired: int, datesCleared: int, scanned: bool} scanned is false
     *         when the module has no single result column to reason about
     */
    public function repairAcceptedWithoutResult(
        string $testKey,
        ?int $sinceMonths = null,
        ?callable $progress = null,
        int $batchSize = self::BATCH_SIZE,
        int $pauseMicroseconds = self::BATCH_PAUSE_MICROSECONDS
    ): array {
        $table = TestsService::getTestTableName($testKey);
        $primaryKey = TestsService::getPrimaryColumn($testKey);
        $resultColumn = TestsService::getResultColumn($testKey);

        // Custom tests keep their results in a child table and so have no single
        // result column to reason about here.
        if ($table === '' || $primaryKey === '' || $resultColumn === '' || !$this->hasColumn($table, $resultColumn)) {
            return ['repaired' => 0, 'datesCleared' => 0, 'scanned' => false];
        }

        $where = [
            // Deliberately wider than SampleStatusUtility::assertsAResult(),
            // which is the rule the write paths enforce. That rule is about what
            // may be written from here on, and it leaves Awaiting Approval out
            // because the paths that set it already require a result. This is
            // about what is already stored, and an Awaiting Approval row with
            // nothing to approve is just as contradictory however it got there:
            // 125 of them on one instance.
            "t.result_status IN (" . ACCEPTED . ", " . PENDING_APPROVAL . ")",
            // Not TestsService::getResultColumn() alone. Hepatitis carries a
            // result in any of three columns -- a row resulted by the analyzer
            // holds only a viral load count -- so reading `result` by itself
            // would call a finished sample empty and strip its status and its
            // approval. One definition of "holds a result", shared with the
            // write guards and the data-issue scan.
            SampleStatusUtility::noResultSql($testKey, 't'),
            "IFNULL(t.is_sample_rejected, 'no') <> 'yes'",
        ];
        if ($sinceMonths !== null && $sinceMonths > 0) {
            $months = (int) $sinceMonths;
            $where[] = "(" . SampleCountUtility::registeredOn('t') . " >= DATE_SUB(NOW(), INTERVAL $months MONTH)"
                . " OR t.last_modified_datetime >= DATE_SUB(NOW(), INTERVAL $months MONTH))";
        }
        $whereSql = implode(' AND ', $where);

        $repaired = 0;
        $datesCleared = 0;
        $previousIds = [];

        // Deliberately NOT paged with an offset. A repaired row leaves the
        // matching set, so advancing the offset would step over the rows that
        // moved up to take its place. Each pass re-reads from the start and the
        // set drains; the identical-batch guard stops the loop rather than
        // spinning if an update ever fails.
        while (true) {
            try {
                $rows = $this->db->rawQuery(
                    "SELECT t.$primaryKey AS record_id,
                            t.sample_tested_datetime,
                            t.sample_received_at_lab_datetime,
                            (t.sample_tested_datetime <=> t.sample_received_at_lab_datetime
                             OR t.sample_tested_datetime <=> t.sample_collection_date) AS copied_stamp
                       FROM $table AS t
                      WHERE $whereSql
                      ORDER BY t.$primaryKey ASC
                      LIMIT " . (int) $batchSize
                ) ?: [];

                if ($rows === []) {
                    break;
                }

                $ids = array_column($rows, 'record_id');
                if ($ids === $previousIds) {
                    LoggerUtility::logError(
                        'Sample status repair: the same batch came back unchanged',
                        ['module' => $testKey, 'ids' => array_slice($ids, 0, 10)]
                    );
                    break;
                }
                $previousIds = $ids;

                foreach ($this->bucket($rows) as $bucket => $bucketIds) {
                    [$status, $testedDate] = explode(':', $bucket);

                    $update = [
                        'result_status' => (int) $status,
                        'result_approved_datetime' => null,
                        'result_approved_by' => null,
                        'result_reviewed_datetime' => null,
                        'result_reviewed_by' => null,
                        // The correction has to reach STS too, or the next sync
                        // brings the contradiction back down.
                        'data_sync' => 0,
                        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                    ];
                    if ($testedDate === 'clear') {
                        $update['sample_tested_datetime'] = null;
                        $datesCleared += count($bucketIds);
                    }

                    $this->db->reset();
                    $this->db->where($primaryKey, $bucketIds, 'IN');
                    $this->db->update($table, $update);
                }

                $repaired += count($ids);

                if ($progress !== null) {
                    $progress($repaired, $datesCleared);
                }

                if ($pauseMicroseconds > 0) {
                    usleep($pauseMicroseconds);
                }
            } catch (Throwable $e) {
                LoggerUtility::logError($e->getMessage(), [
                    'module' => $testKey,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'last_db_error' => $this->db->getLastError(),
                    'last_db_query' => $this->db->getLastQuery(),
                    'trace' => $e->getTraceAsString(),
                ]);
                break;
            }
        }

        return ['repaired' => $repaired, 'datesCleared' => $datesCleared, 'scanned' => true];
    }

    /**
     * Groups a batch by the two things that vary -- where the record proves the
     * sample got to, and whether its test date is a real one -- so each group is
     * one UPDATE rather than one per row.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, list<mixed>> "status:keep|clear" => record ids
     */
    private function bucket(array $rows): array
    {
        $real = static fn(?string $value): bool =>
            !empty($value) && !str_starts_with($value, '0000-00-00');

        $buckets = [];
        foreach ($rows as $row) {
            $atLab = $real($row['sample_received_at_lab_datetime']);
            $keepTested = $real($row['sample_tested_datetime']) && (int) $row['copied_stamp'] === 0;

            $status = $atLab ? RECEIVED_AT_TESTING_LAB : RECEIVED_AT_CLINIC;
            $buckets[$status . ':' . ($keepTested ? 'keep' : 'clear')][] = $row['record_id'];
        }
        return $buckets;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return !empty($this->db->rawQueryOne(
            "SHOW COLUMNS FROM `$table` LIKE '" . $this->db->escape($column) . "'"
        ));
    }
}
