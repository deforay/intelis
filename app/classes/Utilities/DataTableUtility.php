<?php

namespace App\Utilities;

use App\Utilities\MemoUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

final class DataTableUtility
{
    /**
     * Build a search WHERE fragment + bind params.
     * Preferred for new code (prevents SQL injection).
     *
     * @return array{0:string,1:array}|null
     */
    public static function buildSearchWhere(array $request, array $columns, bool $split = true): ?array
    {
        return MemoUtility::remember(function () use ($request, $columns, $split): ?array {
            $columns = self::normalizeColumns($columns);
            if ($columns === []) {
                return null;
            }

            $clauses = [];
            $params = [];

            $searchText = isset($request['sSearch']) ? trim((string) $request['sSearch']) : '';
            if ($searchText !== '' && $searchText !== '0') {
                $terms = $split ? preg_split('/\s+/', $searchText) : [$searchText];

                foreach ($terms as $term) {
                    $term = trim((string) $term);
                    if ($term === '') {
                        continue;
                    }
                    [$expr, $exprParams] = self::buildLikeExpression($columns, $term);
                    $clauses[] = $expr;
                    $params = array_merge($params, $exprParams);
                }
            }

            // Per-column search
            foreach ($columns as $i => $column) {
                $searchKey = 'sSearch_' . $i;
                $searchableKey = 'bSearchable_' . $i;

                if (
                    isset($request[$searchableKey], $request[$searchKey]) &&
                    $request[$searchableKey] == 'true' &&
                    $request[$searchKey] !== ''
                ) {
                    $clauses[] = "$column LIKE ?";
                    $params[] = '%' . $request[$searchKey] . '%';
                }
            }

            return $clauses === [] ? null : [implode(' AND ', $clauses), $params];
        });
    }

    /**
     * Build a search WHERE fragment as a string using $db->escape().
     * Use this for legacy code that still concatenates SQL.
     *
     * @return string|null
     */
    public static function buildSearchWhereEscaped(array $request, array $columns, bool $split = true): ?string
    {
        return MemoUtility::remember(function () use ($request, $columns, $split): ?string {
            /** @var DatabaseService $db */
            $db = ContainerRegistry::get(DatabaseService::class);

            $columns = self::normalizeColumns($columns);
            if ($columns === []) {
                return null;
            }

            $clauses = [];

            $searchText = isset($request['sSearch']) ? trim((string) $request['sSearch']) : '';
            if ($searchText !== '' && $searchText !== '0') {
                $terms = $split ? preg_split('/\s+/', $searchText) : [$searchText];

                foreach ($terms as $term) {
                    $term = trim((string) $term);
                    if ($term === '') {
                        continue;
                    }
                    $clauses[] = self::buildLikeExpressionEscaped($columns, $db->escape($term));
                }
            }

            // Per-column search
            foreach ($columns as $i => $column) {
                $searchKey = 'sSearch_' . $i;
                $searchableKey = 'bSearchable_' . $i;

                if (
                    isset($request[$searchableKey], $request[$searchKey]) &&
                    $request[$searchableKey] == 'true' &&
                    $request[$searchKey] !== ''
                ) {
                    $escaped = $db->escape((string) $request[$searchKey]);
                    $clauses[] = "$column LIKE '%$escaped%'";
                }
            }

            return $clauses === [] ? null : implode(' AND ', $clauses);
        });
    }

    /**
     * Build ORDER BY safely from DataTables params.
     */
    public static function buildOrder(array $request, array $orderColumns): ?string
    {
        return MemoUtility::remember(function () use ($request, $orderColumns): ?string {
            if (!isset($request['iSortCol_0'])) {
                return null;
            }

            $order = [];
            $sortingCols = isset($request['iSortingCols']) ? (int) $request['iSortingCols'] : 0;

            for ($i = 0; $i < $sortingCols; $i++) {
                $sortIndexKey = 'iSortCol_' . $i;
                if (!isset($request[$sortIndexKey])) {
                    continue;
                }

                $columnIndex = (int) $request[$sortIndexKey];
                if (!array_key_exists($columnIndex, $orderColumns)) {
                    continue;
                }

                $sortableKey = 'bSortable_' . $columnIndex;
                if (!isset($request[$sortableKey]) || $request[$sortableKey] != 'true') {
                    continue;
                }

                $dirKey = 'sSortDir_' . $i;
                $dir = isset($request[$dirKey]) ? strtoupper((string) $request[$dirKey]) : 'ASC';
                $dir = $dir === 'DESC' ? 'DESC' : 'ASC';

                $order[] = $orderColumns[$columnIndex] . ' ' . $dir;
            }

            return $order === [] ? null : implode(', ', $order);
        });
    }

    /**
     * Normalize column list to prevent empty or invalid entries.
     */
    private static function normalizeColumns(array $columns): array
    {
        return array_values(array_filter($columns, static fn($c) => is_string($c) && trim($c) !== ''));
    }

    /**
     * Build "(col1 LIKE ? OR col2 LIKE ? ...)" + params.
     */
    private static function buildLikeExpression(array $columns, string $term): array
    {
        $parts = [];
        $params = [];

        foreach ($columns as $column) {
            $parts[] = "$column LIKE ?";
            $params[] = '%' . $term . '%';
        }

        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    /**
     * Build "(col1 LIKE '%x%' OR col2 LIKE '%x%' ...)" using escaped text.
     * Legacy helper for non-parameterized queries.
     */
    private static function buildLikeExpressionEscaped(array $columns, string $escapedTerm): string
    {
        $parts = [];

        foreach ($columns as $column) {
            $parts[] = "$column LIKE '%$escapedTerm%'";
        }

        return '(' . implode(' OR ', $parts) . ')';
    }
}
