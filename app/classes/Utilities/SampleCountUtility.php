<?php

namespace App\Utilities;

use const SAMPLE_STATUS\CANCELLED;

/**
 * Which samples count as real work.
 *
 * A cancelled sample is one somebody decided should never be tested. It is not
 * a sample that failed, or is waiting, or went missing -- it is a sample that
 * was called off, so it should not appear in a total, a rate, a chart or an
 * export anywhere in the application.
 *
 * This exists as one shared clause rather than as a condition written out at
 * each site because that is how the same question ends up with several answers.
 * Rejection went that way: four places each wrote their own version of "is this
 * sample rejected" and, over one nine-month window, returned 412, 419, 421 and
 * 425 for the same question. The requests grid, the sample status report and
 * the data export already exclude cancelled samples one line at a time; the
 * indicators and eighteen other reports do not, and the difference is invisible
 * until two screens disagree in front of a client.
 *
 * Cancelled is the first member and may not be the last. RECEIVED_AT_CLINIC is
 * the obvious candidate to follow -- the request grids exclude it, conditionally,
 * and the reports do not -- but that one is conditional on the instance and the
 * session, so it belongs here only once it can be stated unconditionally.
 */
final class SampleCountUtility
{
    /**
     * SQL predicate for the samples that count.
     *
     * NULL is not a concern: result_status is written on every insert path, and
     * no row in a production form table carries a NULL one. Were that to change,
     * this comparison would exclude those rows, which is the same thing the
     * hand-written versions it replaces already do.
     *
     * @param string $alias the table alias the column hangs off
     */
    public static function countableWhere(string $alias = 'vl'): string
    {
        $a = self::safeAlias($alias);
        return "($a.result_status != " . CANCELLED . ")";
    }

    /**
     * The date a sample entered the system: its collection date, falling back to
     * when the request was created for rows that never recorded one. Legacy rows
     * carry '0000-00-00 00:00:00' instead of NULL, so those fall back too.
     *
     * @param string $alias the table alias the columns hang off
     */
    public static function registeredOn(string $alias = 'vl'): string
    {
        $a = self::safeAlias($alias);
        return "COALESCE(NULLIF($a.sample_collection_date, '0000-00-00 00:00:00'),"
            . " $a.request_created_datetime)";
    }

    /**
     * Samples registered within a date range, as a WHERE predicate.
     *
     * Written as two branches rather than as a range over registeredOn(), which
     * would read better: wrapping the column in COALESCE hides it from the index
     * on sample_collection_date, and these tables reach seven figures in the
     * larger countries. The branches are equivalent -- a zero or NULL collection
     * date fails the first and is caught by the second, and a real one outside
     * the range fails both -- and measured 24% faster over 1.6M rows.
     *
     * Both bounds are inclusive of whole days, matching DATE(x) BETWEEN a AND b.
     *
     * @param string $alias the table alias the columns hang off
     * @param string $startDate Y-m-d
     * @param string $endDate   Y-m-d, counted in full
     */
    public static function registeredBetween(string $alias, string $startDate, string $endDate): string
    {
        $a = self::safeAlias($alias);
        $from = self::dayBoundary($startDate);
        $until = self::dayBoundary($endDate, '+1 day');
        $collected = "$a.sample_collection_date";

        return "(($collected >= '$from' AND $collected < '$until')"
            . " OR (NULLIF($collected, '0000-00-00 00:00:00') IS NULL"
            . " AND $a.request_created_datetime >= '$from'"
            . " AND $a.request_created_datetime < '$until'))";
    }

    /**
     * Dates are interpolated into SQL, so they are rebuilt from a parsed date
     * rather than trusted as given; anything unparseable is refused.
     */
    private static function dayBoundary(string $date, string $shift = ''): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));
        if ($parsed === false) {
            throw new \InvalidArgumentException("Unusable date: {$date}");
        }
        if ($shift !== '') {
            $parsed = $parsed->modify($shift);
        }
        return $parsed->format('Y-m-d H:i:s');
    }

    /**
     * Aliases reach this from callers, never from a request, but they are
     * interpolated into SQL, so anything that is not a plain identifier is
     * refused rather than escaped.
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
