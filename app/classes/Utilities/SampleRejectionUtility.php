<?php

namespace App\Utilities;

use const SAMPLE_STATUS\REJECTED;

/**
 * The one answer to "is this sample rejected".
 *
 * There were four answers before this, and they disagreed. Over one nine-month
 * window of VL data they returned 412, 419, 421 and 425 for the same question:
 *
 *   is_sample_rejected = 'yes'                        412   requests grid filter
 *   result_status = REJECTED                          419   dashboard
 *   flag OR reason_for_sample_rejection > 0           421   Excel exports
 *   flag OR result_status = REJECTED                  425   rejection report, indicators
 *
 * The union is the one that is right, because either record on its own misses
 * real rejections:
 *
 *   - result_status is the sample's *current* state. A rejected sample that
 *     was later re-ordered or accepted has moved on from REJECTED, and the
 *     rejection is only still visible in the flag.
 *   - the flag is not always written. Rejections arriving through older paths
 *     leave it NULL while the status says REJECTED — 8 such samples in that
 *     same window.
 *
 * reason_for_sample_rejection is deliberately not part of it. A reason is a
 * detail of a rejection, not evidence that one happened, and it is left behind
 * on samples that were un-rejected — which is what made the exports disagree
 * with the report they were exported from.
 */
final class SampleRejectionUtility
{
    /**
     * SQL predicate, for counting in a query.
     *
     * The flag is trimmed and lowered here so this answers on exactly the rows
     * isRejected() answers on. That method normalises before comparing, and the two
     * have to agree or a grid and the export taken from it disagree again, which is
     * the whole thing this class exists to stop. A plain `= 'yes'` did not agree:
     * MySQL 8 defaults to utf8mb4_0900_ai_ci, which is NO PAD, so a trailing space
     * makes the comparison fail there while an older install on utf8mb4_unicode_ci
     * would match it -- a difference between two labs, not just between two reports.
     * Neither collation matches a leading space.
     *
     * No index is lost to this: nothing indexes is_sample_rejected, and the OR with
     * result_status rules out a single-column index either way.
     *
     * @param string $alias the table alias the columns hang off
     */
    public static function sqlPredicate(string $alias = 't'): string
    {
        $a = self::safeAlias($alias);
        return "(LOWER(TRIM({$a}.is_sample_rejected)) = 'yes' OR {$a}.result_status = " . REJECTED . ")";
    }

    /**
     * Row-level test, for a record already fetched — export row builders and
     * anything else deciding this in PHP rather than in SQL.
     *
     * @param array<string, mixed> $row
     */
    public static function isRejected(array $row): bool
    {
        $flag = strtolower(trim((string) ($row['is_sample_rejected'] ?? '')));
        if ($flag === 'yes') {
            return true;
        }
        return isset($row['result_status']) && (int) $row['result_status'] === REJECTED;
    }

    /**
     * The label to show for a rejection reason.
     *
     * A reason id is an auto-increment that is local to the install that minted
     * it, and reference data only flows STS -> LIS, so a reason a lab creates
     * arrives at the STS as an id with no row behind it. On one country's data
     * 1,553 VL rows -- 91% of the rejections in a recent nine-month window --
     * pointed at a reason id the STS has never had.
     *
     * Reports used to INNER JOIN the reason table, so every one of those rows
     * vanished from the listing while the summary on the same page still counted
     * it. LEFT JOIN and route the name through here instead: an unmatched or
     * absent reason is named, not dropped. A rejection with no reason on record
     * is still a rejection.
     */
    public static function reasonLabel(?string $reasonName): string
    {
        $name = trim((string) $reasonName);
        return $name !== '' ? $name : _translate('Unknown or unreported reason');
    }

    /**
     * Samples whose two records of rejection contradict each other, so they can
     * be found and corrected rather than silently rounded away by whichever
     * definition a report happens to use.
     *
     * Carrying a result is what makes a row a contradiction: a rejected sample
     * is rejected before testing, so it should have no result to show. Six such
     * samples exist in the VL window above, and they are the entire difference
     * between the 425 this utility counts and the 418 that are unambiguous.
     */
    public static function contradictionPredicate(string $alias = 't', string $resultColumn = 'result'): string
    {
        $a = self::safeAlias($alias);
        $r = self::safeAlias($resultColumn);
        return "(" . self::sqlPredicate($alias) . " AND {$a}.{$r} IS NOT NULL AND {$a}.{$r} <> '')";
    }

    /**
     * Aliases and column names reach these methods from callers, never from a
     * request, but they are interpolated into SQL, so anything that is not a
     * plain identifier is refused rather than escaped.
     */
    private static function safeAlias(string $identifier): string
    {
        $identifier = trim($identifier);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Unsafe SQL identifier: {$identifier}");
        }
        return $identifier;
    }
}
