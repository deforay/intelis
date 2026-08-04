<?php

namespace App\Utilities;

/**
 * Builds the monthly Laboratory Turnaround Time chart used by every module's
 * Sample Status page (VL, EID, TB, Hepatitis, CD4, Covid-19 and Custom Tests).
 *
 * Every form_* table carries the same six milestone columns, so there is no
 * reason for each module to hand-copy this SQL. They used to, and the copies
 * had drifted apart badly:
 *
 *  - VL, EID, TB and Covid-19 clamped an out-of-order gap to zero days with
 *    GREATEST(diff, 0), which quietly pulled every average down.
 *  - CD4, Hepatitis and Custom Tests passed the milestones to TIMESTAMPDIFF in
 *    reverse and wrapped the result in ABS(AVG(...)). Averaging signed values
 *    and taking the absolute value of the mean lets a handful of bad rows
 *    cancel out good ones, so those three modules reported a number that does
 *    not correspond to any turnaround time at all.
 *  - All of them grouped on the formatted 'Jan-2026' month string and ordered
 *    by a bare sample_tested_datetime that was not in the GROUP BY, so the
 *    chart's x-axis was only in chronological order by luck. Custom Tests had
 *    no ORDER BY on the grouped query at all.
 *
 * How a stage is measured now:
 *  - Calendar days (DATEDIFF), not elapsed 24 hour periods, so a sample
 *    received on Monday evening and tested on Tuesday morning counts as one
 *    day rather than zero. This is how VL/EID programs report turnaround time,
 *    and it keeps every stage on the same footing regardless of whether the
 *    milestone happens to carry a time component.
 *  - A stage is skipped for a sample when either end is missing or the two
 *    dates are out of order. Out-of-order dates are data entry errors, so they
 *    are left out of that stage's average rather than counted as zero days.
 */
final class TurnaroundTimeUtility
{
    /**
     * Chart series key => [start column, end column, label, colour]
     *
     * Keys name the milestones they span, replacing the old sampleReceivedDiff
     * style names that said nothing about what was being measured.
     */
    public const STAGES = [
        'collectedToReceived' => ['sample_collection_date', 'sample_received_at_lab_datetime', 'Collected - Received at Lab', '#edb47c'],
        'receivedToTested' => ['sample_received_at_lab_datetime', 'sample_tested_datetime', 'Received - Tested', '#0f3f6e'],
        'collectedToTested' => ['sample_collection_date', 'sample_tested_datetime', 'Collected - Tested', '#ed7c7d'],
        'testedToPrinted' => ['sample_tested_datetime', 'result_printed_datetime', 'Tested - Printed', '#7f22e8'],
        'collectedToPrinted' => ['sample_collection_date', 'result_printed_datetime', 'Collected - Printed', '#000000'],
        'lisPrintedToStsPrinted' => ['result_printed_on_lis_datetime', 'result_printed_on_sts_datetime', 'Printed on LIS - Printed on STS', '#639e11'],
    ];

    /**
     * The aggregation SQL. Call it through
     * AbstractTestService::getTurnaroundTimeSeries(), which supplies the table
     * and result column for the module and runs the query.
     *
     * @param string   $table        form_* table to read
     * @param string[] $conditions   Caller's WHERE conditions, ANDed together
     * @param string   $resultColumn Column holding the result (form_cd4 calls it cd4_result)
     * @param string   $joins        Extra JOIN clauses the conditions rely on
     * @param string   $alias        Alias given to $table
     * @param string[] $stages       Subset of STAGES keys; defaults to all of them
     */
    public static function buildQuery(
        string $table,
        array $conditions = [],
        string $resultColumn = 'result',
        string $joins = '',
        string $alias = 'sample',
        array $stages = []
    ): string {
        $stages = $stages === [] ? array_keys(self::STAGES) : $stages;

        $columns = [
            "COUNT(*) AS samplesTested",
            "DATE_FORMAT($alias.sample_tested_datetime, '%b-%Y') AS monthLabel",
        ];
        foreach ($stages as $stage) {
            [$from, $to] = self::STAGES[$stage];
            $diff = "DATEDIFF($alias.$to, $alias.$from)";
            // No ELSE, so a missing or out-of-order milestone yields NULL and
            // drops out of AVG() instead of being counted as a zero day stage.
            $columns[] = "ROUND(AVG(CASE WHEN $diff >= 0 THEN $diff END), 2) AS $stage";
        }

        // A sample only has a turnaround time once it has been tested and has
        // a result to show for it.
        //
        // The last two guards drop samples whose test date is impossible: in
        // the future, or before the sample was even collected. An analyzer
        // with a mis-set clock produces thousands of these, and because the
        // chart groups by test month they used to surface as phantom columns
        // years away from the real data, squashing the trend line.
        $where = array_merge($conditions, [
            "$alias.sample_tested_datetime IS NOT NULL",
            "IFNULL($alias.$resultColumn, '') != ''",
            "$alias.sample_tested_datetime <= NOW()",
            "($alias.sample_collection_date IS NULL"
                . " OR $alias.sample_tested_datetime >= $alias.sample_collection_date)",
        ]);

        // Grouping and ordering on the year and month values, rather than on
        // the formatted month string, is what keeps the x-axis chronological.
        $monthKey = "YEAR($alias.sample_tested_datetime), MONTH($alias.sample_tested_datetime)";

        return "SELECT " . implode(",\n               ", $columns) . "
                FROM `$table` AS $alias
                $joins
                WHERE " . implode("\n                  AND ", $where) . "
                GROUP BY $monthKey
                ORDER BY $monthKey";
    }

    /**
     * Turns the aggregated rows into JS literals for the chart.
     *
     * A month with no sample completing a stage emits the literal null so the
     * line breaks there. A stage that genuinely averaged zero days now plots as
     * zero instead of vanishing, which is what the old "> 0 ? value : 'null'"
     * test did to same day turnaround.
     *
     * @param iterable<array<string, mixed>> $rows
     * @return array<string, string[]>
     */
    public static function toChartSeries(iterable $rows, array $stages = []): array
    {
        $stages = $stages === [] ? array_keys(self::STAGES) : $stages;

        $series = ['months' => [], 'samplesTested' => []];
        foreach ($stages as $stage) {
            $series[$stage] = [];
        }

        foreach ($rows as $row) {
            if (empty($row['monthLabel'])) {
                continue;
            }
            $series['months'][] = (string) $row['monthLabel'];
            $series['samplesTested'][] = (string) (int) ($row['samplesTested'] ?? 0);
            foreach ($stages as $stage) {
                $series[$stage][] = ($row[$stage] ?? null) === null
                    ? 'null'
                    : (string) round((float) $row[$stage], 2);
            }
        }

        return $series;
    }

    /**
     * Highcharts series definitions for the stages present in $series, so each
     * module's chart is built from one description instead of a copied block.
     *
     * @return array<int, array{data: string, name: string, color: string}>
     */
    public static function chartSeries(array $series): array
    {
        $definitions = [];
        foreach (self::STAGES as $stage => [, , $label, $color]) {
            if (empty($series[$stage])) {
                continue;
            }
            $definitions[] = [
                'data' => implode(',', $series[$stage]),
                'name' => _translate($label, escapeTextOrContext: true),
                'color' => $color,
            ];
        }

        return $definitions;
    }

    /**
     * Plain language description of each stage, for the "How is TAT
     * calculated?" panel on the Sample Status pages.
     *
     * @return array<int, array{label: string, description: string}>
     */
    public static function methodology(array $stages = []): array
    {
        $descriptions = [
            'collectedToReceived' => 'Days from the sample collection date to the date the sample was received at the testing lab. This covers transport from the health facility.',
            'receivedToTested' => 'Days from the date the sample was received at the testing lab to the date it was tested. This is the time the sample spent in the lab before testing.',
            'collectedToTested' => 'Days from the sample collection date to the sample test date.',
            'testedToPrinted' => 'Days from the sample test date to the date the result was printed.',
            'collectedToPrinted' => 'Days from the sample collection date to the date the result was printed. This is the full end-to-end turnaround time.',
            'lisPrintedToStsPrinted' => 'Days between the result being printed at the testing lab and the result being printed at the health facility. This measures how long a finished result takes to reach the facility.',
        ];

        $stages = $stages === [] ? array_keys(self::STAGES) : $stages;

        $rows = [];
        foreach ($stages as $stage) {
            $rows[] = [
                'label' => _translate(self::STAGES[$stage][2]),
                'description' => _translate($descriptions[$stage]),
            ];
        }
        $rows[] = [
            'label' => _translate('No. of Samples Tested'),
            'description' => _translate('Count of samples tested in the month, plotted against the right-hand axis.'),
        ];

        return $rows;
    }

    /**
     * Caveats that apply to every module's chart, for the same panel.
     *
     * @return string[]
     */
    public static function methodologyNotes(): array
    {
        return [
            _translate('Turnaround time is counted in calendar days. A sample collected and tested on the same day counts as zero days, and one collected on Monday and tested on Tuesday counts as one day.'),
            _translate('Only samples that have a result and a sample test date are counted. Samples still in progress, rejected or without a result are excluded.'),
            _translate('Averages are grouped by the month in which the sample was tested, and follow the filters selected above.'),
            _translate('A sample is left out of a measure when either of its two dates is missing, or when the dates are recorded out of order. This means each measure can be based on a different number of samples, so the individual stages will not always add up exactly to the end-to-end figure.'),
            _translate('A month with no samples for a given measure appears as a break in the line rather than as zero.'),
        ];
    }
}
