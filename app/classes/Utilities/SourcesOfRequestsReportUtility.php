<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\CommonService;
use App\Services\DatabaseService;

/**
 * The Sources of Requests report: how the requests from each source moved
 * through the lab. The page, its data endpoint and its export share this, so
 * the summary and the sample list always count the same rows the same way.
 * Every query here runs over the test table aliased `vl`.
 */
final class SourcesOfRequestsReportUtility
{
    /**
     * A result counts as returned once dispatched or sent back to the source,
     * whichever happened.
     */
    public const RETURNED_ON = 'COALESCE(vl.result_sent_to_source_datetime, vl.result_dispatched_datetime)';

    /** Stages the sample list can be narrowed to, as the summary counts them. */
    public const STAGES = ['received', 'notReceived', 'tested', 'returned', 'notReturned'];

    /**
     * One value per source, whichever name a row was stored under, so the
     * summary has one row per source and its links match the source filter.
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
            default => null,
        };
    }

    /**
     * Per source: requests, how many reached each step, and the median days from
     * sample collection to receipt at the lab and to the result being returned.
     *
     * The times run from collection, not from the request: request_created_datetime
     * is when the row was entered, and a lab entering samples after the fact puts
     * it weeks after the receipt and even after the result. A time dated before
     * collection is a data-entry slip and is left out.
     *
     * @param string $fromWhere The FROM and WHERE of the rows to count.
     * @return array{rows: array<int, array<string, mixed>>, total: array<string, mixed>}
     */
    public static function summary(DatabaseService $db, string $fromWhere): array
    {
        $source = self::sourceExpression();
        $returnedOn = self::RETURNED_ON;

        $counts = $db->rawQuery(
            "SELECT $source AS request_source,
                    COUNT(*) AS requested,
                    SUM(vl.sample_received_at_lab_datetime IS NOT NULL) AS received,
                    SUM(vl.sample_tested_datetime IS NOT NULL) AS tested,
                    SUM($returnedOn IS NOT NULL) AS returned
             $fromWhere
             GROUP BY request_source"
        ) ?: [];

        // MySQL has no MEDIAN(): rank each source's times and average the middle
        // one or two. Source '' holds every row, for the total.
        $medians = $db->rawQuery(
            "WITH d AS (
                SELECT $source AS request_source,
                       CASE WHEN vl.sample_received_at_lab_datetime >= vl.sample_collection_date
                            THEN TIMESTAMPDIFF(MINUTE, vl.sample_collection_date,
                                vl.sample_received_at_lab_datetime) END AS receipt_minutes,
                       CASE WHEN $returnedOn >= vl.sample_collection_date
                            THEN TIMESTAMPDIFF(MINUTE, vl.sample_collection_date, $returnedOn) END
                            AS return_minutes
                $fromWhere
            ),
            everything AS (
                SELECT request_source, receipt_minutes, return_minutes FROM d
                UNION ALL
                SELECT '', receipt_minutes, return_minutes FROM d
            ),
            ranked AS (
                SELECT request_source, 'receipt' AS step, receipt_minutes AS minutes,
                       ROW_NUMBER() OVER (PARTITION BY request_source ORDER BY receipt_minutes) AS position,
                       COUNT(*) OVER (PARTITION BY request_source) AS times
                  FROM everything WHERE receipt_minutes IS NOT NULL
                UNION ALL
                SELECT request_source, 'return', return_minutes,
                       ROW_NUMBER() OVER (PARTITION BY request_source ORDER BY return_minutes),
                       COUNT(*) OVER (PARTITION BY request_source)
                  FROM everything WHERE return_minutes IS NOT NULL
            )
            SELECT request_source, step, AVG(minutes) AS median_minutes
              FROM ranked
             WHERE position IN (FLOOR((times + 1) / 2), CEIL((times + 1) / 2))
             GROUP BY request_source, step"
        ) ?: [];

        $median = [];
        foreach ($medians as $row) {
            $median[(string) $row['request_source']][$row['step']] = round((float) $row['median_minutes'] / 1440, 1);
        }

        $total = ['requested' => 0, 'received' => 0, 'tested' => 0, 'returned' => 0];
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

        // Busiest source first; rows without a source last, whatever their size.
        usort($rows, fn(array $a, array $b): int => [$a['source'] === 'unrecorded', $b['requested']]
            <=> [$b['source'] === 'unrecorded', $a['requested']]);

        return [
            'rows' => $rows,
            'total' => ['source' => '', 'label' => _translate('Total')] + $total + [
                'receiptDays' => $median['']['receipt'] ?? null,
                'returnDays' => $median['']['return'] ?? null,
            ],
        ];
    }
}
