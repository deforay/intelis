<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\DatabaseService;

/**
 * The batch / manifest / facility / lab / district / patient filter block is
 * copy-pasted across the request, result-entry, print, failed and approval
 * listings of every test type, each copy with its own columns. Those copies
 * concatenated the request value straight into the WHERE clause.
 *
 * An endpoint now declares its filters as a map of request key => [column, kind]
 * and this class writes the clauses, encoding every value for its kind. The map
 * keeps each listing's own columns and comparison, so a legitimate value matches
 * exactly the rows it matched before; only a value that tried to leave its quotes
 * behaves differently.
 *
 * These listings store the assembled WHERE in the session for their exports to
 * replay, which is why the values are encoded into the string rather than bound.
 */
final class ListingFilterClauseBuilder
{
    /** column = "text" */
    public const EQUALS = 'equals';

    /** column = 123 */
    public const INT = 'int';

    /** column IN (1,2,3), from a CSV string or an array; non-numeric parts dropped */
    public const INT_LIST = 'intList';

    /** column LIKE "%text%", with % and _ in the value matched literally */
    public const CONTAINS = 'contains';

    /** column LIKE "text", the value's own wildcards kept as the listing always treated them */
    public const LIKE = 'like';

    /**
     * @param array<string, mixed> $request the (sanitized) request values
     * @param array<string, array{0: string, 1: string}> $filters request key => [column expression, kind]
     * @return list<string> one WHERE fragment per filter that was supplied
     */
    public static function clauses(DatabaseService $db, array $request, array $filters): array
    {
        $clauses = [];
        foreach ($filters as $key => [$column, $kind]) {
            $value = self::supplied($request[$key] ?? null);
            if ($value === null) {
                continue;
            }
            $clauses[] = match ($kind) {
                self::EQUALS => " $column = \"" . $db->escape(self::text($value)) . '"',
                self::INT => " $column = " . (int) self::text($value),
                self::INT_LIST => " $column IN (" . $db->inIntList($value) . ')',
                self::CONTAINS => " $column LIKE \"%" . $db->escapeLike(self::text($value)) . '%"',
                self::LIKE => " $column LIKE \"" . $db->escape(self::text($value)) . '"',
                default => throw new \InvalidArgumentException("Unknown filter kind '$kind' for '$key'"),
            };
        }
        return $clauses;
    }

    /** Request grid quick filter: samples not yet in a batch. */
    public const NOT_BATCHED = 'notBatched';

    /** Request grid quick filter: samples not yet on a manifest. */
    public const NOT_MANIFESTED = 'notManifested';

    /**
     * Build the request grids' "Not in a batch" or "Not on a manifest" filter.
     *
     * "Not in a batch" uses the same test the batch screens use to offer a
     * sample for batching. Only the two known values produce SQL. Any other
     * value, including an empty one, adds no clause.
     *
     * @param string $alias the form table's alias in the listing query
     * @return list<string> zero or one WHERE fragment
     */
    public static function groupingClauses(mixed $value, string $alias = 'vl'): array
    {
        return match ($value) {
            self::NOT_BATCHED => [" ($alias.sample_batch_id IS NULL OR $alias.sample_batch_id = '')"],
            self::NOT_MANIFESTED => [" ($alias.sample_package_code IS NULL OR $alias.sample_package_code = '')"],
            default => [],
        };
    }

    /**
     * Re-encodes a list a page sends already quoted for SQL -- `'A', 'B'`, as the
     * import screens build it -- into `'A','B'` with every code escaped.
     *
     * The codes are read quote by quote, not split on commas: a sample code may
     * itself contain a comma. A list with no quoted codes is read as plain
     * comma-separated codes.
     */
    public static function quotedTextList(DatabaseService $db, string $list): string
    {
        $codes = preg_match_all("/'([^']*)'/", $list, $matches) > 0
            ? $matches[1]
            : array_map('trim', explode(',', $list));
        return implode(',', array_map(static fn(string $code): string => "'" . $db->escape($code) . "'", $codes));
    }

    /**
     * A filter applies when it has a non-blank value; an array (a multi-select)
     * applies when any element does.
     *
     * @return string|list<mixed>|null
     */
    private static function supplied(mixed $value): string|array|null
    {
        if (is_array($value)) {
            $value = array_values(array_filter($value, static fn($v): bool => is_scalar($v) && trim((string) $v) !== ''));
            return $value === [] ? null : $value;
        }
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }
        return (string) $value;
    }

    /** @param string|list<mixed> $value */
    private static function text(string|array $value): string
    {
        return is_array($value) ? (string) $value[0] : $value;
    }
}
