<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\LoggerUtility;

use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\ON_HOLD;
use const SAMPLE_STATUS\REFERRED;
use const SAMPLE_STATUS\NO_RESULT;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\TEST_FAILED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\REORDERED_FOR_TESTING;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;

/**
 * A sample that has reached the lab was received there. When nobody recorded
 * when, the reception date is taken to be the collection date.
 *
 * Every lab-side write path saves a new sample as "Registered at Testing Lab",
 * and none of them asks for a reception date, so labs that key samples in
 * straight from the bench left it empty -- on DRC, 95k VL rows and 2k EID rows.
 * Such a sample reads as at the lab by its status and as never received by its
 * milestones, and every turnaround measured from reception loses it.
 *
 * Why the collection date and not the moment the sample was typed in: labs
 * routinely enter samples days after testing them, and instrument imports
 * create the row when the result file is uploaded. The typing-in moment would
 * put reception after the test for more than half of the affected rows. The
 * collection date can never come after the test.
 *
 * What is never touched:
 *   - a reception date somebody entered. Only an empty one is filled.
 *   - a sample that has not reached a lab: still at the clinic, or cancelled.
 *   - a sample with no collection date, because there is nothing to assume.
 *
 * "Reached a lab" is read two ways, because a sample's origin says as much as
 * its status:
 *   - entered at a lab (remote_sample is not 'yes'): any status except Registered
 *     at Health Center or Cancelled. A lab registered it, so it was there.
 *   - requested from a collection site (remote_sample = 'yes'): only a status a
 *     lab alone can give it. Rejected, Lost and Expired are excluded because
 *     each can happen before a sample ever arrives.
 *
 * Enforced by the `<form>_receipt_bi/bu` triggers that
 * {@see AuditTriggerService::buildReceiptTriggersFor()} installs, so it holds for
 * every write path -- forms, APIs, imports, instruments and sync -- including
 * ones written later. {@see stampMissing()} applies the same predicate to rows
 * already stored. Both read {@see needsFallbackSql()}, so they cannot disagree.
 */
final class LabReceiptService
{
    public const string RECEIVED_COLUMN = 'sample_received_at_lab_datetime';
    public const string COLLECTED_COLUMN = 'sample_collection_date';

    /** Columns the rule reads; a table missing any of them is left alone. */
    public const array REQUIRED_COLUMNS = [
        self::RECEIVED_COLUMN,
        self::COLLECTED_COLUMN,
        'remote_sample',
        'result_status',
    ];

    /** Rows per update in {@see stampMissing()}. */
    private const int PK_WINDOW = 5000;

    /** Pause between windows, since this runs against the database labs work on. */
    private const int WINDOW_PAUSE_MICROSECONDS = 100000;

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * Statuses that are true of a sample a collection site requested only once a
     * lab has it.
     *
     * @return list<int>
     */
    public static function labOnlyStatuses(): array
    {
        return [
            ON_HOLD,
            REORDERED_FOR_TESTING,
            TEST_FAILED,
            RECEIVED_AT_TESTING_LAB,
            ACCEPTED,
            PENDING_APPROVAL,
            NO_RESULT,
            REFERRED,
        ];
    }

    /**
     * Statuses under which a sample entered at a lab is still not treated as
     * received.
     *
     * @return list<int>
     */
    public static function notReceivedStatuses(): array
    {
        return [RECEIVED_AT_CLINIC, CANCELLED];
    }

    /**
     * SQL condition that is true when a row's reception date should be filled in
     * from its collection date.
     *
     * $row is the row reference: `NEW` inside a trigger, a table alias elsewhere.
     * The zero-date comparisons are there because older rows carry
     * '0000-00-00 00:00:00', which is as empty as NULL.
     */
    public static function needsFallbackSql(string $row): string
    {
        $received = $row . '.`' . self::RECEIVED_COLUMN . '`';
        $collected = $row . '.`' . self::COLLECTED_COLUMN . '`';
        $status = $row . '.`result_status`';
        $remote = $row . '.`remote_sample`';

        $labOnly = implode(', ', self::labOnlyStatuses());
        $notReceived = implode(', ', self::notReceivedStatuses());

        return "({$received} IS NULL OR {$received} < '1970-01-02')"
            . " AND {$collected} IS NOT NULL AND {$collected} >= '1970-01-02'"
            . " AND {$status} IS NOT NULL"
            . " AND ("
            . "(IFNULL({$remote}, '') <> 'yes' AND {$status} NOT IN ({$notReceived}))"
            . " OR {$status} IN ({$labOnly})"
            . ")";
    }

    /** Whether a table carries every column the rule reads. */
    public static function appliesTo(array $columns): bool
    {
        return array_diff(self::REQUIRED_COLUMNS, $columns) === [];
    }

    /**
     * Fills the reception date from the collection date on stored rows that
     * need it, a primary-key window at a time.
     *
     * Deliberately leaves last_modified_datetime and data_sync alone. Both the
     * lab and STS apply the same rule to the same collection date and reach the
     * same value, so there is nothing to send; marking the rows unsynced would
     * push every one of them across the network for no change.
     *
     * @param int|null $modifiedWithinDays only rows modified this recently; null for every row
     * @return int rows filled
     */
    public function stampMissing(string $table, string $primaryKey, ?int $modifiedWithinDays = null): int
    {
        $columns = array_column(
            $this->db->rawQuery(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            ) ?: [],
            'COLUMN_NAME'
        );
        if (!self::appliesTo($columns)) {
            return 0;
        }

        $tableQ = '`' . str_replace('`', '``', $table) . '`';
        $pkQ = '`' . str_replace('`', '``', $primaryKey) . '`';
        $condition = self::needsFallbackSql('t');

        $recency = '';
        if ($modifiedWithinDays !== null && in_array('last_modified_datetime', $columns, true)) {
            $recency = ' AND t.`last_modified_datetime` >= NOW() - INTERVAL ' . max(1, $modifiedWithinDays) . ' DAY';
        }

        // A recent-only pass touches a handful of rows, so it is one statement on
        // the last_modified_datetime index rather than a walk over every key.
        if ($recency !== '') {
            $this->db->rawQuery(
                "UPDATE {$tableQ} AS t
                    SET t.`" . self::RECEIVED_COLUMN . "` = t.`" . self::COLLECTED_COLUMN . "`
                  WHERE {$condition}{$recency}"
            );
            return max(0, $this->db->count);
        }

        // Windows are PK_WINDOW rows wide, not PK_WINDOW key values wide. Keys are
        // far from dense -- a lab copy with 292k rows has keys past 154 million --
        // so stepping through the key range would spend nearly all its time on
        // empty windows and the pause between them.
        $filled = 0;
        $failedWindows = 0;
        $after = null;
        while (true) {
            $end = $this->db->rawQueryOne(
                "SELECT MAX(k) AS window_end FROM (SELECT {$pkQ} AS k FROM {$tableQ}"
                    . ($after === null ? '' : " WHERE {$pkQ} > ?")
                    . " ORDER BY {$pkQ} LIMIT " . self::PK_WINDOW . ") AS w",
                $after === null ? null : [$after]
            )['window_end'] ?? null;
            if (!is_numeric($end)) {
                break;
            }
            $end = (int) $end;

            $bounds = $after === null ? "t.{$pkQ} <= ?" : "t.{$pkQ} > ? AND t.{$pkQ} <= ?";
            try {
                $this->db->rawQuery(
                    "UPDATE {$tableQ} AS t
                        SET t.`" . self::RECEIVED_COLUMN . "` = t.`" . self::COLLECTED_COLUMN . "`
                      WHERE {$bounds} AND {$condition}",
                    $after === null ? [$end] : [$after, $end]
                );
                $filled += max(0, $this->db->count);
            } catch (\Throwable $e) {
                // One bad window (a deadlock, a lock timeout) must not strand the
                // rest of the table, so the walk carries on and fails at the end.
                $failedWindows++;
                LoggerUtility::logError(
                    "Lab receipt fallback failed on {$table} after {$after} up to {$end}: " . $e->getMessage()
                );
            }

            $after = $end;
            usleep(self::WINDOW_PAUSE_MICROSECONDS);
        }

        // Failing loudly is what gets the skipped rows retried. The full pass is a
        // run-once, and a run-once that returns is recorded as done and never runs
        // again; the nightly sweep only reaches the last few days, so rows skipped
        // here would otherwise stay without a reception date for good.
        if ($failedWindows > 0) {
            throw new \RuntimeException(
                "Lab receipt fallback on {$table}: {$failedWindows} window(s) failed after filling {$filled} row(s)"
            );
        }

        return $filled;
    }
}
