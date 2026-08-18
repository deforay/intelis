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
