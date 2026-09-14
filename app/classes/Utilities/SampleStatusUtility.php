<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\TestsService;

use const SAMPLE_STATUS\ACCEPTED;

/**
 * What a sample status asserts about the row carrying it.
 *
 * Accepted is what the printing, emailing, dispatch and reporting queries all
 * read as "this sample has a result", so a row that holds the status without
 * holding a result drops silently out of every one of them -- it is neither
 * pending work anyone can see nor finished work anyone can send. Several write
 * paths could produce exactly that, each in its own way, which is why the rule
 * is stated once here and applied at each of them rather than restated.
 *
 * DataIssuesService reads the same definition to find the rows already in that
 * state, so what the scan reports and what the write paths refuse cannot drift
 * apart.
 */
final class SampleStatusUtility
{
    /**
     * True for a status that claims the test is finished, and so cannot be set
     * on a sample with nothing to show for it.
     *
     * Awaiting Approval is deliberately not here. It is the status a result
     * page writes on its way to approval, and the paths that set it already
     * require a result, so adding it would only refuse work nobody does.
     */
    public static function assertsAResult(int|string|null $status): bool
    {
        return (int) $status === ACCEPTED;
    }

    /**
     * Every column that can hold this test type's result.
     *
     * Hepatitis is the one type with more than one: the result page writes the
     * interpretation to `result`, while the analyzer import writes only the
     * viral load count for whichever of HBV or HCV was ordered. A row resulted
     * by the instrument therefore carries a count and no `result`, and reading
     * `result` alone would call it empty.
     *
     * @return string[]
     */
    public static function resultColumns(string $testType): array
    {
        if ($testType === 'hepatitis') {
            return ['result', 'hbv_vl_count', 'hcv_vl_count'];
        }

        return [TestsService::getResultColumn($testType)];
    }

    /** True when the row holds a result in any column that can carry one. */
    public static function rowHasResult(string $testType, array $row): bool
    {
        foreach (self::resultColumns($testType) as $column) {
            if (trim((string) ($row[$column] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /** SQL that is true for rows holding a result. */
    public static function hasResultSql(string $testType, string $alias = 'vl'): string
    {
        $clauses = [];
        foreach (self::resultColumns($testType) as $column) {
            $clauses[] = "($alias.$column IS NOT NULL AND TRIM($alias.$column) <> '')";
        }

        return '(' . implode(' OR ', $clauses) . ')';
    }

    /** SQL that is true for rows holding no result at all. */
    public static function noResultSql(string $testType, string $alias = 'vl'): string
    {
        $clauses = [];
        foreach (self::resultColumns($testType) as $column) {
            $clauses[] = "($alias.$column IS NULL OR TRIM($alias.$column) = '')";
        }

        return '(' . implode(' AND ', $clauses) . ')';
    }

    /**
     * The colour a status is drawn in on the sample status pies.
     *
     * Keyed by the status constants: the per-page arrays this replaces were
     * numbered by an older status list, so Awaiting Approval was drawn in the
     * colour meant for "Sent to Lab" and Referred had no colour at all.
     */
    public static function chartColor(int $status): string
    {
        return match ($status) {
            \SAMPLE_STATUS\ON_HOLD => '#dda41b',
            \SAMPLE_STATUS\LOST_OR_MISSING => '#9a1c64',
            \SAMPLE_STATUS\REORDERED_FOR_TESTING => '#8c8c8c',
            \SAMPLE_STATUS\REJECTED => '#d8424d',
            \SAMPLE_STATUS\TEST_FAILED => '#000000',
            \SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB => '#e2d44b',
            ACCEPTED => '#639e11',
            \SAMPLE_STATUS\PENDING_APPROVAL => '#7f22e8',
            \SAMPLE_STATUS\RECEIVED_AT_CLINIC => '#4bc0d9',
            \SAMPLE_STATUS\EXPIRED => '#f0ad4e',
            \SAMPLE_STATUS\NO_RESULT => '#b5651d',
            \SAMPLE_STATUS\CANCELLED => '#20c997',
            \SAMPLE_STATUS\REFERRED => '#1f77b4',
            default => '#999999',
        };
    }
}
