<?php

namespace App\Services;

use App\Utilities\DateUtility;
use App\Utilities\SampleStatusUtility;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\LOST_OR_MISSING;

/**
 * Records whose own columns contradict each other.
 *
 * Not "incomplete" records, which are ordinary and numerous, but records that
 * assert two things which cannot both be true -- a sample marked lost that
 * carries a result, or one marked accepted with nothing to report. Each is
 * counted by some screens and not others, which is how the same question came
 * to have four different answers across the app.
 *
 * The checks are deliberately narrow. A count nobody can act on is not a
 * problem report, it is furniture: 47% of form_vl rows share a sample code with
 * another row and 1.5% carry no collection date, and neither is a defect anyone
 * will work through. What earns a place is a contradiction that is small,
 * certain and fixable. Also deliberately absent is a result sitting at Pending
 * Approval -- that reads like a contradiction and is not, it is a result
 * waiting for someone to approve it.
 *
 * Only recent samples are counted, for the same reason. A contradiction on a
 * sample collected two years ago is history: the record is closed, the people
 * who worked it have moved on, and nobody is going to open it and rule on it.
 * Reporting it forever teaches readers that the card is not worth reading.
 *
 * Nothing here corrects anything. Every contradiction it looks for has had its
 * cause closed, so a count above zero means a new write path has opened one,
 * and quietly repairing them would erase the evidence saying so.
 */
final class DataIssuesService
{
    public const TABLE = 's_data_issues';
    public const SCAN_TABLE = 's_data_issues_scan';

    /** Primary keys per chunk. Nothing the scan runs is ever wider than this. */
    private const CHUNK_SIZE = 50000;

    /**
     * Chunks per run. A first scan of a very large table is spread over several
     * nights rather than held open for one long pass; the cursor remembers where
     * it stopped.
     */
    private const MAX_CHUNKS_PER_RUN = 400;

    /** How long before the incremental pass is replaced by a full one. */
    private const FULL_SCAN_AFTER_DAYS = 7;

    /**
     * How far back the card looks, in days.
     *
     * The flags themselves are kept for every row, whatever its age -- the scan
     * is what is expensive, and narrowing this later must not need one. Only
     * what is counted and listed is bounded.
     */
    private const RECENT_DAYS = 90;

    /**
     * Overlap subtracted from the watermark. A row written in the same second
     * the previous run read its watermark could otherwise fall between the two
     * runs and never be examined by either.
     */
    private const WATERMARK_OVERLAP_MINUTES = 5;

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $general
    ) {
    }

    /**
     * The definitions, in one place, so the card and the listing that shows the
     * offending rows can never drift apart.
     *
     * @return array<string, string> issue key => SQL predicate
     */
    public static function predicates(string $testType, string $alias = 'vl'): array
    {
        // Same definition the write paths refuse on, so what this scan reports
        // and what the app declines to write cannot drift apart.
        $hasResult = SampleStatusUtility::hasResultSql($testType, $alias);
        $noResult = SampleStatusUtility::noResultSql($testType, $alias);

        return [
            // A rejected sample carrying a result is deliberately absent. It was
            // here, and it was the wrong question to put to a reader: rejection
            // is the lab's own decision and it stands, so a leftover result on a
            // rejected row is stale data, not a ruling anyone owes. Both write
            // paths that produced these are closed and the status update now
            // clears the result columns when it rejects, so what remains is
            // history -- 7 rows on one instance, six of them written by a single
            // import in 2024.

            // Someone recorded that the sample never arrived, yet a result for
            // it exists. The status locks the row, so that result cannot be
            // reached, printed or sent back.
            //
            // Expiry is deliberately not here. It is a time-based decision the
            // nightly task makes about a sample nobody worked on, so an expired
            // sample carrying a result is a backlog rather than a contradiction
            // -- 823 of them on one instance, none of which anyone was going to
            // work through.
            'lostWithResult' => "($alias.result_status = " . LOST_OR_MISSING . " AND $hasResult)",

            // Accepted is what the printing, emailing and dispatch queries treat
            // as "has a result", so one without a result drops silently out of
            // every one of them.
            'acceptedWithoutResult' => "($alias.result_status = " . ACCEPTED . " AND $noResult)",
        ];
    }

    /** The window, for the card to say out loud what it is counting. */
    public static function recentDays(): int
    {
        return self::RECENT_DAYS;
    }

    /**
     * The age limit the card and its listing both work to.
     *
     * Collection date is what a reader judges a sample's age by and what the
     * listing's own date filter uses, so the card agrees with the page it sits
     * on. Falling back to when the request was created covers the rows that
     * carry no collection date -- 1.5% of one instance's -- which would
     * otherwise be silently outside every window.
     */
    public static function recencyClause(string $alias = 'vl'): string
    {
        return "COALESCE($alias.sample_collection_date, $alias.request_created_datetime)"
            . " >= DATE_SUB(NOW(), INTERVAL " . self::RECENT_DAYS . " DAY)";
    }

    /**
     * Counts for the current user, read from what the last scan flagged.
     *
     * @return array<string, int> issue key => count, non-zero only
     */
    /**
     * Every restriction the request list applies to what a reader may see.
     *
     * Kept identical to app/vl/requests/get-request-list.php on purpose. A count
     * is data: one that includes records the reader cannot open discloses that
     * those records exist, which is a disclosure whether or not the row itself
     * is ever shown. The two must therefore agree exactly -- if that file gains
     * a restriction, this needs it too.
     *
     * @return string[] SQL clauses, already scoped to the given alias
     */
    private function visibilityClauses(string $alias = 'vl'): array
    {
        $clauses = [];

        if ($this->general->isSTSInstance()) {
            if (!empty($_SESSION['facilityMap'])) {
                // Built by the session from the user's own mapping, never from
                // a request, and already a list of integers.
                $clauses[] = "$alias.facility_id IN (" . $_SESSION['facilityMap'] . ")";
            }
            // Testing-lab users work the lab side only.
            if (($_SESSION['accessType'] ?? '') === 'testing-lab') {
                $clauses[] = "$alias.result_status != " . RECEIVED_AT_CLINIC;
            }
        } else {
            $clauses[] = "$alias.result_status != " . RECEIVED_AT_CLINIC;
        }

        if ($labScope = $this->general->labScopeWhere($alias)) {
            $clauses[] = $labScope;
        }

        $clauses[] = "$alias.result_status != " . CANCELLED;

        return $clauses;
    }

    /**
     * Counts for the current reader, of what the last scan flagged.
     *
     * Joined back to the form table rather than read from the flags alone. The
     * flags carry a denormalised lab and facility, which would answer the scope
     * question more cheaply, but not the status one -- and a cancelled sample
     * carrying a rejection flag would otherwise be counted here while being
     * invisible in the listing below. Joining also means the count reflects the
     * record as it stands now, not as it stood at the last scan.
     *
     * @return array<string, int> issue key => count, non-zero only
     */
    public function getIssueCounts(string $testType): array
    {
        $table = TestsService::getTestTableName($testType);
        $primaryKey = TestsService::getPrimaryColumn($testType);
        if (empty($table) || empty($primaryKey)) {
            return [];
        }

        $clauses = array_merge(
            ['di.test_type = ?', self::recencyClause('vl')],
            $this->visibilityClauses('vl')
        );

        $rows = $this->db->rawQuery(
            "SELECT di.issue_key, COUNT(*) AS total
               FROM " . self::TABLE . " AS di
               JOIN $table AS vl ON vl.$primaryKey = di.record_id
              WHERE " . implode(' AND ', $clauses) . "
              GROUP BY di.issue_key",
            [$testType]
        ) ?: [];

        // A check that has been retired leaves its flags behind until the rows
        // holding them are next examined, and they are not the reader's problem
        // in the meantime. What the definitions no longer list is not counted.
        $known = self::predicates($testType);

        $counts = [];
        foreach ($rows as $row) {
            $issue = (string) $row['issue_key'];
            $total = (int) $row['total'];
            if ($total > 0 && isset($known[$issue])) {
                $counts[$issue] = $total;
            }
        }
        return $counts;
    }

    /**
     * Scan one test type and bring its flags up to date.
     *
     * Incremental by default: only rows whose last_modified_datetime has moved
     * since the last run are re-examined, so the nightly cost follows the day's
     * edits rather than the size of the table. Every re-examined row has its
     * flags dropped first and re-raised from scratch, which is what clears a
     * flag from a row that has since been fixed.
     *
     * Always chunked, whichever mode: work is done in primary key ranges of a
     * fixed size, a bounded number of them per run, and where it stopped is
     * remembered. A first scan of a table with millions of rows therefore
     * spreads over several nights rather than holding the database for one long
     * pass, and no single statement is ever unbounded.
     *
     * Done in SQL rather than by reading rows into PHP: testing three
     * conditions is not worth moving millions of rows through memory.
     *
     * @return array{mode: string, examined: int, flagged: int, complete: bool}
     */
    public function refresh(string $testType, bool $forceFull = false): array
    {
        $table = TestsService::getTestTableName($testType);
        $primaryKey = TestsService::getPrimaryColumn($testType);
        if (empty($table) || empty($primaryKey)) {
            return ['mode' => 'skipped', 'examined' => 0, 'flagged' => 0, 'complete' => true];
        }

        $scan = $this->db->rawQueryOne(
            "SELECT * FROM " . self::SCAN_TABLE . " WHERE test_type = ?",
            [$testType]
        ) ?: [];

        $watermark = (string) ($scan['last_checked_datetime'] ?? '');
        $lastFull = (string) ($scan['last_full_scan_datetime'] ?? '');
        $cursor = (int) ($scan['full_scan_cursor'] ?? 0);

        // A full pass on the first run, periodically after, and always while one
        // is still part way through: the incremental pass only sees rows whose
        // last_modified_datetime moved, so a correction applied straight in SQL
        // would otherwise leave its flag behind for good.
        $full = $forceFull
            || $cursor > 0
            || $watermark === ''
            || $lastFull === ''
            || strtotime($lastFull) < strtotime('-' . self::FULL_SCAN_AFTER_DAYS . ' days');

        // Read before the scan, not after: anything written while it runs must
        // be picked up next time rather than assumed already seen.
        $startedAt = DateUtility::getCurrentDateTime();

        $window = '';
        if (!$full) {
            $since = date('Y-m-d H:i:s', strtotime($watermark) - (self::WATERMARK_OVERLAP_MINUTES * 60));
            $window = " AND vl.last_modified_datetime >= '" . $this->db->escape($since) . "' ";
        }

        // A full pass restarts from wherever the last run stopped; an
        // incremental one always covers the whole key space, since its window
        // already bounds it to the day's edits.
        $from = $full ? $cursor : 0;
        $predicates = self::predicates($testType);
        $examined = 0;
        $chunks = 0;
        $complete = true;

        while (true) {
            if ($chunks >= self::MAX_CHUNKS_PER_RUN) {
                $complete = false;
                break;
            }

            // The chunk boundary is read from the data rather than added to the
            // cursor: primary keys here are sparse -- form_vl reaches 159 million
            // over 1.6 million rows -- so a fixed step through the key space
            // would spend most of its chunks on empty ranges. This walks a fixed
            // number of rows instead.
            // The window is applied here too, not only to the work below, or an
            // incremental run would still step through every chunk of the table
            // to discover that each one holds nothing it needs. With it, a run
            // on a quiet day is a single query that returns nothing.
            $edge = $this->db->rawQueryOne(
                "SELECT MAX(chunk.$primaryKey) AS to_id, COUNT(*) AS in_chunk FROM (
                     SELECT vl.$primaryKey FROM $table AS vl
                      WHERE vl.$primaryKey > $from" . $window . "
                      ORDER BY vl.$primaryKey ASC
                      LIMIT " . self::CHUNK_SIZE . "
                 ) AS chunk"
            ) ?: [];

            $to = (int) ($edge['to_id'] ?? 0);
            if ($to <= $from) {
                break;
            }
            $range = " AND vl.$primaryKey > $from AND vl.$primaryKey <= $to ";

            // 1. Drop what is known about the rows about to be re-examined.
            $this->db->rawQuery(
                "DELETE di FROM " . self::TABLE . " AS di
                   JOIN $table AS vl ON vl.$primaryKey = di.record_id
                  WHERE di.test_type = ?" . $range . $window,
                [$testType]
            );

            // 2. Raise a flag for every row still matching, one statement per
            //    issue, each bounded to this chunk.
            foreach ($predicates as $issue => $predicate) {
                $this->db->rawQuery(
                    "INSERT INTO " . self::TABLE . "
                            (test_type, record_id, issue_key, lab_id, facility_id, sample_code, flagged_on)
                     SELECT ?, vl.$primaryKey, ?, COALESCE(vl.lab_id, 0), COALESCE(vl.facility_id, 0), vl.sample_code, ?
                       FROM $table AS vl
                      WHERE $predicate" . $range . $window . "
                     ON DUPLICATE KEY UPDATE flagged_on = VALUES(flagged_on)",
                    [$testType, $issue, $startedAt]
                );
            }

            $examined += (int) ($edge['in_chunk'] ?? 0);

            $from = $to;
            $chunks++;
        }

        $flaggedRow = $this->db->rawQueryOne(
            "SELECT COUNT(*) AS total FROM " . self::TABLE . " WHERE test_type = ?",
            [$testType]
        ) ?: [];

        // A full pass only counts as done, and only stops being resumed, once it
        // has reached the end of the table.
        $nextCursor = ($full && !$complete) ? $from : 0;
        $fullFinishedAt = ($full && $complete) ? $startedAt : null;

        $this->db->rawQuery(
            "INSERT INTO " . self::SCAN_TABLE . "
                    (test_type, last_checked_datetime, last_full_scan_datetime, full_scan_cursor)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                    last_checked_datetime = VALUES(last_checked_datetime),
                    last_full_scan_datetime = COALESCE(VALUES(last_full_scan_datetime), last_full_scan_datetime),
                    full_scan_cursor = VALUES(full_scan_cursor)",
            [$testType, $startedAt, $fullFinishedAt, $nextCursor]
        );

        return [
            'mode' => $full ? 'full' : 'incremental',
            'examined' => $examined,
            'flagged' => (int) ($flaggedRow['total'] ?? 0),
            'complete' => $complete,
        ];
    }
}
