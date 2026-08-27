<?php

declare(strict_types=1);

namespace App\Utilities;

/**
 * The dateRange / labName / state / district / facilityId filter block is copy-pasted,
 * near-verbatim, across roughly a dozen admin/api-dashboard and admin/monitoring
 * endpoint files (get-api-dashboard-metrics.php, get-duplicate-suspects.php,
 * get-samplewise-report.php, and others), each with a different table alias. This
 * consolidates it into one place so a fix or a test written here covers all of them
 * at once, instead of once per copy.
 *
 * These values are ids and reach an IN() list, which cannot take a placeholder for a
 * variable-length list, so they are concatenated. Everything non-numeric is therefore
 * dropped before it reaches the query -- the approach get-duplicates-detail.php already
 * took, and the reason consolidating was worth doing: it was the only one of the dozen
 * that sanitised, and now they all do.
 */
final class AdminFilterClauseBuilder
{
    /**
     * @return string|null A `column BETWEEN '...' AND '...'` fragment, or null if no
     *   dateRange was supplied.
     */
    public static function dateRangeClause(array $post, string $dateColumn): ?string
    {
        if (!isset($post['dateRange']) || trim((string) $post['dateRange']) === '') {
            return null;
        }
        [$startDate, $endDate] = DateUtility::convertDateRange($post['dateRange'] ?? '', includeTime: true);
        return " $dateColumn BETWEEN '$startDate' AND '$endDate' ";
    }

    /**
     * Builds a `column IN (...)` fragment from a POST value that may be a scalar
     * (labName is passed as a raw comma-separated string) or an array (state, district,
     * facilityId are passed as arrays of ids and imploded).
     *
     * @return string|null null if the key is absent, an empty string, or an empty array.
     */
    public static function inClause(array $post, string $postKey, string $column): ?string
    {
        if (!isset($post[$postKey])) {
            return null;
        }

        $value = $post[$postKey];

        $ids = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_filter($ids, static fn($id): bool => is_numeric($id));

        if ($ids === []) {
            return null;
        }

        return " $column IN (" . implode(',', array_map('intval', $ids)) . ")";
    }

    /**
     * Convenience for the common five-filter shape shared by most admin endpoints.
     * Returns the list of non-null WHERE fragments, ready for `implode(' AND ', ...)`.
     *
     * @param array<string,string> $columns Keys: dateColumn, labColumn, stateColumn,
     *   districtColumn, facilityColumn. Omit any the caller's query doesn't use.
     * @return list<string>
     */
    public static function buildStandardFilters(array $post, array $columns): array
    {
        $clauses = [];

        if (isset($columns['dateColumn'])) {
            $clause = self::dateRangeClause($post, $columns['dateColumn']);
            if ($clause !== null) {
                $clauses[] = $clause;
            }
        }
        if (isset($columns['labColumn'])) {
            $clause = self::inClause($post, 'labName', $columns['labColumn']);
            if ($clause !== null) {
                $clauses[] = $clause;
            }
        }
        if (isset($columns['stateColumn'])) {
            $clause = self::inClause($post, 'state', $columns['stateColumn']);
            if ($clause !== null) {
                $clauses[] = $clause;
            }
        }
        if (isset($columns['districtColumn'])) {
            $clause = self::inClause($post, 'district', $columns['districtColumn']);
            if ($clause !== null) {
                $clauses[] = $clause;
            }
        }
        if (isset($columns['facilityColumn'])) {
            $clause = self::inClause($post, 'facilityId', $columns['facilityColumn']);
            if ($clause !== null) {
                $clauses[] = $clause;
            }
        }

        return $clauses;
    }

    /** True if a facility join is needed (state and/or district filters present). */
    public static function needsFacilityJoin(array $post): bool
    {
        return isset($post['state']) || isset($post['district']);
    }
}
