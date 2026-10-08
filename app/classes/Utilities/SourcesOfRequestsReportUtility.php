<?php

declare(strict_types=1);

namespace App\Utilities;

use DateTimeImmutable;
use InvalidArgumentException;
use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;

/**
 * The Sources of Requests report: how the requests from each source moved
 * through the lab. The page, its data endpoints and its export share this, so
 * the summaries and the sample list always count the same rows the same way.
 * Every query here runs over the test table aliased `vl`.
 */
final class SourcesOfRequestsReportUtility
{
    /**
     * A result counts as returned once dispatched or sent back to the source,
     * whichever happened.
     */
    public const RETURNED_ON = 'COALESCE(vl.result_sent_to_source_datetime, vl.result_dispatched_datetime)';

    /** A tested result not returned after this many days is overdue. */
    public const OVERDUE_DAYS = 7;

    /** Stages the sample list can be narrowed to, as the summaries count them. */
    public const STAGES = ['received', 'notReceived', 'tested', 'returned', 'notReturned', 'overdue'];

    /** Sources whose requests the lab had to enter itself. */
    private const NOT_ELECTRONIC = ['vlsm', 'unrecorded'];

    /** The key the medians use for every row together. */
    private const ALL = '*';

    /**
     * The rows the report covers, from the page's filters: every countable
     * request of the test type in the date range, area, clinics and labs chosen,
     * within the user's own lab when they work for one.
     *
     * @return array{table: string, from: string, where: list<string>}
     * @throws InvalidArgumentException for a test type that is not active
     */
    public static function reportScope(array $post, CommonService $general): array
    {
        $testType = (string) ($post['testType'] ?? 'vl');
        if (!in_array($testType, TestsService::getActiveTests(), true)) {
            throw new InvalidArgumentException("Inactive or unknown test type: $testType");
        }
        $table = TestsService::getTestTableName($testType);

        $from = "
                FROM $table as vl
                LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id
                LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                LEFT JOIN batch_details as b ON vl.sample_batch_id=b.batch_id";

        // Cancelled requests are not counted anywhere, so they are not listed either.
        // These five filters appear, verbatim, in a dozen admin endpoints, each with
        // its own alias. AdminFilterClauseBuilder is the one copy of them.
        $where = [
            SampleCountUtility::countableWhere('vl'),
            ...AdminFilterClauseBuilder::buildStandardFilters($post, [
                'dateColumn' => 'vl.request_created_datetime',
                'labColumn' => 'vl.lab_id',
                'stateColumn' => 'f.facility_state_id',
                'districtColumn' => 'f.facility_district_id',
                'facilityColumn' => 'vl.facility_id',
            ]),
        ];
        // A user working for one lab sees that lab's samples only, in the list and
        // in the summaries alike.
        $labScope = $general->labScopeWhere('vl');
        if ($labScope !== '') {
            $where[] = $labScope;
        }

        return ['table' => $table, 'from' => $from, 'where' => array_values($where)];
    }

    /**
     * One value per source, whichever name a row was stored under, so the
     * summaries have one row per source and their links match the source filter.
     */
    public static function sourceExpression(): string
    {
        $when = '';
        foreach (['lis', 'sts'] as $source) {
            $stored = CommonService::storedSourcesOfRequest($source);
            $when .= " WHEN vl.source_of_request IN ('" . implode("', '", $stored) . "') THEN '$stored[0]'";
        }
        return "CASE WHEN IFNULL(vl.source_of_request, '') = '' THEN 'unrecorded'$when"
            . ' ELSE LOWER(vl.source_of_request) END';
    }

    /** The condition for one stage, or null for an unknown one. */
    public static function stageWhere(string $stage): ?string
    {
        $returnedOn = self::RETURNED_ON;
        return match ($stage) {
            'received' => 'vl.sample_received_at_lab_datetime IS NOT NULL',
            'notReceived' => 'vl.sample_received_at_lab_datetime IS NULL',
            'tested' => 'vl.sample_tested_datetime IS NOT NULL',
            'returned' => "$returnedOn IS NOT NULL",
            'notReturned' => "vl.sample_tested_datetime IS NOT NULL AND $returnedOn IS NULL",
            'overdue' => "vl.sample_tested_datetime IS NOT NULL AND $returnedOn IS NULL"
                . ' AND vl.sample_tested_datetime < NOW() - INTERVAL ' . self::OVERDUE_DAYS . ' DAY',
            default => null,
        };
    }

    /**
     * Per source: requests, how many reached each step, how many results are
     * overdue, and the median days from sample collection to receipt at the lab
     * and to the result being returned.
     *
     * @param string $fromWhere The FROM and WHERE of the rows to count.
     * @return array{rows: array<int, array<string, mixed>>, total: array<string, mixed>}
     */
    public static function summary(DatabaseService $db, string $fromWhere): array
    {
        $source = self::sourceExpression();
        $returnedOn = self::RETURNED_ON;
        $overdue = self::stageWhere('overdue');

        $counts = $db->rawQuery(
            "SELECT $source AS request_source,
                    COUNT(*) AS requested,
                    SUM(vl.sample_received_at_lab_datetime IS NOT NULL) AS received,
                    SUM(vl.sample_tested_datetime IS NOT NULL) AS tested,
                    SUM($returnedOn IS NOT NULL) AS returned,
                    SUM($overdue) AS overdue
             $fromWhere
             GROUP BY request_source"
        ) ?: [];
        $median = self::medians($db, $fromWhere, $source);

        $total = ['requested' => 0, 'received' => 0, 'tested' => 0, 'returned' => 0, 'overdue' => 0];
        $rows = [];
        foreach ($counts as $row) {
            $key = (string) $row['request_source'];
            $entry = ['source' => $key, 'label' => CommonService::sourceOfRequestLabel($key)];
            foreach (array_keys($total) as $step) {
                $entry[$step] = (int) $row[$step];
                $total[$step] += $entry[$step];
            }
            $rows[] = $entry + [
                'receiptDays' => $median[$key]['receipt'] ?? null,
                'returnDays' => $median[$key]['return'] ?? null,
            ];
        }

        return [
            'rows' => self::sortSources($rows),
            'total' => ['source' => '', 'label' => _translate('Total')] + $total + [
                'receiptDays' => $median[self::ALL]['receipt'] ?? null,
                'returnDays' => $median[self::ALL]['return'] ?? null,
            ],
        ];
    }

    /**
     * Per clinic: requests from each source, how many came electronically (not
     * entered at the lab), results returned and overdue, and the median days from
     * collection to the result being returned. Clinic 0 holds requests with none.
     *
     * @return array{sources: list<array{source: string, label: string}>,
     *     rows: list<array<string, mixed>>, total: array<string, mixed>}
     */
    public static function byClinic(DatabaseService $db, string $fromWhere): array
    {
        $source = self::sourceExpression();
        $returnedOn = self::RETURNED_ON;
        $overdue = self::stageWhere('overdue');
        $clinic = 'IFNULL(vl.facility_id, 0)';

        $counts = $db->rawQuery(
            "SELECT $clinic AS report_clinic,
                    MAX(f.facility_name) AS clinic_name,
                    $source AS request_source,
                    COUNT(*) AS requested,
                    SUM($returnedOn IS NOT NULL) AS returned,
                    SUM($overdue) AS overdue
             $fromWhere
             GROUP BY report_clinic, request_source"
        ) ?: [];
        $median = self::medians($db, $fromWhere, $clinic);

        $blank = ['requested' => 0, 'electronic' => 0, 'returned' => 0, 'overdue' => 0, 'bySource' => []];
        $clinics = [];
        $sources = [];
        $total = $blank;
        foreach ($counts as $row) {
            $id = (string) (int) $row['report_clinic'];
            $key = (string) $row['request_source'];
            $clinics[$id] ??= [
                'clinicId' => $id,
                'label' => $id === '0' || trim((string) $row['clinic_name']) === ''
                    ? _translate('Not Recorded') : (string) $row['clinic_name'],
            ] + $blank;
            $sources[$key] = ($sources[$key] ?? 0) + (int) $row['requested'];

            foreach ([&$clinics[$id], &$total] as &$entry) {
                $entry['requested'] += (int) $row['requested'];
                $entry['returned'] += (int) $row['returned'];
                $entry['overdue'] += (int) $row['overdue'];
                $entry['bySource'][$key] = ($entry['bySource'][$key] ?? 0) + (int) $row['requested'];
                if (!in_array($key, self::NOT_ELECTRONIC, true)) {
                    $entry['electronic'] += (int) $row['requested'];
                }
            }
            unset($entry);
        }

        $rows = [];
        foreach ($clinics as $id => $entry) {
            $rows[] = $entry + ['returnDays' => $median[(string) $id]['return'] ?? null];
        }
        usort($rows, fn(array $a, array $b): int => $b['requested'] <=> $a['requested']);

        $sourceRows = [];
        foreach ($sources as $key => $requested) {
            $sourceRows[] = [
                'source' => (string) $key,
                'label' => CommonService::sourceOfRequestLabel((string) $key),
                'requested' => $requested,
            ];
        }

        return [
            'sources' => array_map(
                fn(array $row): array => ['source' => $row['source'], 'label' => $row['label']],
                self::sortSources($sourceRows)
            ),
            'rows' => $rows,
            'total' => ['clinicId' => '', 'label' => _translate('Total')] + $total
                + ['returnDays' => $median[self::ALL]['return'] ?? null],
        ];
    }

    /**
     * Requests per period from each source: by week (from Monday) when the
     * requests span half a year or less, by month beyond that. Periods with no
     * requests are kept, as zeros, so the chart's spacing is true to time.
     *
     * @return array{unit: string, periods: list<string>,
     *     series: list<array{source: string, label: string, data: list<int>}>}
     */
    public static function trend(DatabaseService $db, string $fromWhere): array
    {
        $source = self::sourceExpression();
        $days = $db->rawQuery(
            "SELECT DATE(vl.request_created_datetime) AS request_day,
                    $source AS request_source,
                    COUNT(*) AS requested
             $fromWhere AND vl.request_created_datetime IS NOT NULL
             GROUP BY request_day, request_source
             ORDER BY request_day"
        ) ?: [];
        if ($days === []) {
            return ['unit' => 'week', 'periods' => [], 'series' => []];
        }

        $first = new DateTimeImmutable((string) $days[0]['request_day']);
        $last = new DateTimeImmutable((string) end($days)['request_day']);
        $unit = $first->diff($last)->days <= 183 ? 'week' : 'month';
        $periodOf = fn(DateTimeImmutable $day): DateTimeImmutable => $unit === 'week'
            ? $day->modify('monday this week')
            : $day->modify('first day of this month');
        $step = $unit === 'week' ? '+1 week' : '+1 month';

        $periods = [];
        for ($period = $periodOf($first); $period <= $last; $period = $period->modify($step)) {
            $periods[$period->format('Y-m-d')] = count($periods);
        }

        $series = [];
        $totals = [];
        foreach ($days as $row) {
            $key = (string) $row['request_source'];
            $series[$key] ??= array_fill(0, count($periods), 0);
            $index = $periods[$periodOf(new DateTimeImmutable((string) $row['request_day']))->format('Y-m-d')];
            $series[$key][$index] += (int) $row['requested'];
            $totals[$key] = ($totals[$key] ?? 0) + (int) $row['requested'];
        }

        $ordered = [];
        foreach ($totals as $key => $requested) {
            $ordered[] = ['source' => (string) $key, 'requested' => $requested];
        }

        return [
            'unit' => $unit,
            // A week by its first day, in the app's date format; a month as
            // 2025-02, which needs no translation and sorts as it reads.
            'periods' => array_map(
                fn(string $start): string => $unit === 'week'
                    ? (string) DateUtility::humanReadableDateFormat($start)
                    : substr($start, 0, 7),
                array_keys($periods)
            ),
            'series' => array_map(fn(array $row): array => [
                'source' => $row['source'],
                'label' => CommonService::sourceOfRequestLabel($row['source']),
                'data' => $series[$row['source']],
            ], self::sortSources($ordered)),
        ];
    }

    /**
     * The median days from sample collection to receipt at the lab and to the
     * result being returned, per group and for every row together (self::ALL).
     *
     * The times run from collection, not from the request: request_created_datetime
     * is when the row was entered, and a lab entering samples after the fact puts
     * it weeks after the receipt and even after the result. A time dated before
     * collection is a data-entry slip and is left out.
     *
     * @return array<string, array{receipt?: float, return?: float}>
     */
    private static function medians(DatabaseService $db, string $fromWhere, string $groupExpression): array
    {
        $returnedOn = self::RETURNED_ON;
        $all = self::ALL;

        // MySQL has no MEDIAN(): rank each group's times and average the middle
        // one or two.
        $medians = $db->rawQuery(
            "WITH d AS (
                SELECT CAST($groupExpression AS CHAR) AS group_key,
                       CASE WHEN vl.sample_received_at_lab_datetime >= vl.sample_collection_date
                            THEN TIMESTAMPDIFF(MINUTE, vl.sample_collection_date,
                                vl.sample_received_at_lab_datetime) END AS receipt_minutes,
                       CASE WHEN $returnedOn >= vl.sample_collection_date
                            THEN TIMESTAMPDIFF(MINUTE, vl.sample_collection_date, $returnedOn) END
                            AS return_minutes
                $fromWhere
            ),
            everything AS (
                SELECT group_key, receipt_minutes, return_minutes FROM d
                UNION ALL
                SELECT '$all', receipt_minutes, return_minutes FROM d
            ),
            ranked AS (
                SELECT group_key, 'receipt' AS step, receipt_minutes AS minutes,
                       ROW_NUMBER() OVER (PARTITION BY group_key ORDER BY receipt_minutes) AS position,
                       COUNT(*) OVER (PARTITION BY group_key) AS times
                  FROM everything WHERE receipt_minutes IS NOT NULL
                UNION ALL
                SELECT group_key, 'return', return_minutes,
                       ROW_NUMBER() OVER (PARTITION BY group_key ORDER BY return_minutes),
                       COUNT(*) OVER (PARTITION BY group_key)
                  FROM everything WHERE return_minutes IS NOT NULL
            )
            SELECT group_key, step, AVG(minutes) AS median_minutes
              FROM ranked
             WHERE position IN (FLOOR((times + 1) / 2), CEIL((times + 1) / 2))
             GROUP BY group_key, step"
        ) ?: [];

        $median = [];
        foreach ($medians as $row) {
            $median[(string) $row['group_key']][$row['step']] = round((float) $row['median_minutes'] / 1440, 1);
        }
        return $median;
    }

    /**
     * Busiest source first; rows without a source last, whatever their size.
     *
     * @param list<array<string, mixed>> $rows each with 'source' and 'requested'
     * @return list<array<string, mixed>>
     */
    private static function sortSources(array $rows): array
    {
        usort($rows, fn(array $a, array $b): int => [$a['source'] === 'unrecorded', $b['requested']]
            <=> [$b['source'] === 'unrecorded', $a['requested']]);
        return $rows;
    }
}
