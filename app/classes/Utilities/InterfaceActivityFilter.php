<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\CommonService;
use App\Services\DatabaseService;

/**
 * WHERE clauses for the Interface Tool events on the Instrument Activity page, shared
 * by the grid and its export so the file always holds what the grid showed. The lab
 * scope is applied here because both endpoints are reachable directly.
 */
final class InterfaceActivityFilter
{
    /** Columns the grid's text search looks in. */
    public const SEARCH_COLUMNS = [
        'a.instrument_id',
        'a.machine_type',
        'a.event_type',
        'a.outcome',
        'a.failure_code',
        'a.app_version',
        'f.facility_name',
    ];

    /**
     * The grid's text search, for the export: the grid sends it as sSearch, the
     * export as search. Needs facility_details joined as `f`.
     */
    public static function searchClause(string $search, CommonService $general): ?string
    {
        $clause = $general->multipleColumnSearch($search, self::SEARCH_COLUMNS);
        return empty($clause) ? null : $clause;
    }

    /**
     * @param array<string, mixed> $post the page's filters: dateRange, outcome, eventType, instrument
     * @return list<string> clauses on instrument_activity_log aliased `a`, to join with AND
     */
    public static function clauses(array $post, DatabaseService $db, CommonService $general): array
    {
        $where = [];

        // An operator only ever sees their own lab's machines.
        $labScope = $general->labAdminScopeWhere('lab_id', 'a');
        if (!empty($labScope)) {
            $where[] = $labScope;
        }

        if (trim((string) ($post['dateRange'] ?? '')) !== '') {
            [$startDate, $endDate] = DateUtility::convertDateRange((string) $post['dateRange']);
            if (!empty($startDate) && !empty($endDate)) {
                $where[] = " DATE(a.occurred_at) BETWEEN '" . $db->escape($startDate) . "'
                                AND '" . $db->escape($endDate) . "' ";
            }
        }

        if (trim((string) ($post['outcome'] ?? '')) !== '') {
            $where[] = " a.outcome = '" . $db->escape((string) $post['outcome']) . "' ";
        }

        if (trim((string) ($post['eventType'] ?? '')) !== '') {
            $where[] = " a.event_type = '" . $db->escape((string) $post['eventType']) . "' ";
        }

        if (trim((string) ($post['instrument'] ?? '')) !== '') {
            $where[] = " a.instrument_id LIKE '%" . $db->escape((string) $post['instrument']) . "%' ";
        }

        return $where;
    }
}
