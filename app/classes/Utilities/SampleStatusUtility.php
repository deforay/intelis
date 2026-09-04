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
}
