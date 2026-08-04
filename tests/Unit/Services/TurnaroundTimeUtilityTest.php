<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Utilities\TurnaroundTimeUtility;
use PHPUnit\Framework\TestCase;

/**
 * These lock in the two things every module's turnaround time chart depends on
 * and that the old hand-copied SQL kept getting wrong: how a stage is measured,
 * and how the months are grouped and ordered.
 */
final class TurnaroundTimeUtilityTest extends TestCase
{
    public function testStagesAreMeasuredInCalendarDays(): void
    {
        $sql = TurnaroundTimeUtility::buildQuery('form_vl');

        // DATEDIFF, not TIMESTAMPDIFF: a sample received on Monday evening and
        // tested on Tuesday morning is one day of turnaround, not zero.
        $this->assertStringContainsString(
            'DATEDIFF(sample.sample_tested_datetime, sample.sample_received_at_lab_datetime)',
            $sql
        );
        $this->assertStringNotContainsString('TIMESTAMPDIFF', $sql);
    }

    public function testOutOfOrderDatesAreExcludedRatherThanCountedAsZero(): void
    {
        $sql = TurnaroundTimeUtility::buildQuery('form_vl');

        // A CASE with no ELSE yields NULL, which AVG() skips. GREATEST(diff, 0)
        // and ABS(AVG(...)) both kept bad rows in the denominator instead.
        $this->assertStringContainsString('CASE WHEN', $sql);
        $this->assertStringNotContainsString('GREATEST', $sql);
        $this->assertStringNotContainsString('ABS(AVG', $sql);
    }

    public function testMonthsAreGroupedAndOrderedChronologically(): void
    {
        $sql = TurnaroundTimeUtility::buildQuery('form_vl');

        $group = 'GROUP BY YEAR(sample.sample_tested_datetime), MONTH(sample.sample_tested_datetime)';
        $order = 'ORDER BY YEAR(sample.sample_tested_datetime), MONTH(sample.sample_tested_datetime)';

        $this->assertStringContainsString($group, $sql);
        $this->assertStringContainsString($order, $sql);
    }

    public function testImpossibleTestDatesAreExcluded(): void
    {
        $sql = TurnaroundTimeUtility::buildQuery('form_vl');

        // An analyzer with a mis-set clock used to put phantom months years
        // away from the real data on the chart.
        foreach (TurnaroundTimeUtility::plausibleDateConditions('sample') as $condition) {
            $this->assertStringContainsString($condition, $sql);
        }
    }

    public function testPlausibilityGuardsCompareDatesNotTimestamps(): void
    {
        $conditions = implode(' AND ', TurnaroundTimeUtility::plausibleDateConditions('sample'));

        // Most samples never get a test time recorded, so it defaults to
        // midnight. Comparing full timestamps would drop every same-day sample
        // whose collection time was after 00:00 as "tested before collected".
        $this->assertStringContainsString(
            'DATE(sample.sample_tested_datetime) >= DATE(sample.sample_collection_date)',
            $conditions
        );
        $this->assertStringContainsString('DATE(sample.sample_tested_datetime) <= CURDATE()', $conditions);
        $this->assertStringNotContainsString('<= NOW()', $conditions);
    }

    public function testPlausibilityGuardsAreAliasAware(): void
    {
        // The detail exports still alias the form table as vl.
        $conditions = implode(' AND ', TurnaroundTimeUtility::plausibleDateConditions('vl'));

        $this->assertStringContainsString('DATE(vl.sample_tested_datetime) <= CURDATE()', $conditions);
    }

    public function testResultColumnIsConfigurablePerModule(): void
    {
        // form_cd4 has no `result` column; it stores cd4_result.
        $sql = TurnaroundTimeUtility::buildQuery('form_cd4', [], 'cd4_result');

        $this->assertStringContainsString("IFNULL(sample.cd4_result, '') != ''", $sql);
    }

    public function testCallerConditionsAndJoinsAreApplied(): void
    {
        $sql = TurnaroundTimeUtility::buildQuery(
            'form_eid',
            ['sample.lab_id = ?'],
            'result',
            'LEFT JOIN batch_details AS batch ON batch.batch_id = sample.sample_batch_id'
        );

        $this->assertStringContainsString('sample.lab_id = ?', $sql);
        $this->assertStringContainsString('LEFT JOIN batch_details AS batch', $sql);
    }

    public function testZeroDayAverageIsPlottedRatherThanDroppedAsAGap(): void
    {
        $series = TurnaroundTimeUtility::toChartSeries([
            [
                'monthLabel' => 'Jan-2026',
                'samplesTested' => 10,
                'collectedToReceived' => '0.00',
                'receivedToTested' => null,
            ],
        ], ['collectedToReceived', 'receivedToTested']);

        // The old "> 0 ? value : 'null'" test turned a genuine same-day average
        // into a hole in the line.
        $this->assertSame(['0'], $series['collectedToReceived']);
        // A month where nothing completed the stage still breaks the line.
        $this->assertSame(['null'], $series['receivedToTested']);
        $this->assertSame(['Jan-2026'], $series['months']);
        $this->assertSame(['10'], $series['samplesTested']);
    }

    public function testRowsWithoutAMonthLabelAreSkipped(): void
    {
        $series = TurnaroundTimeUtility::toChartSeries([
            ['monthLabel' => null, 'samplesTested' => 3, 'collectedToTested' => '5'],
            ['monthLabel' => 'Feb-2026', 'samplesTested' => 4, 'collectedToTested' => '6'],
        ], ['collectedToTested']);

        $this->assertSame(['Feb-2026'], $series['months']);
        $this->assertSame(['6'], $series['collectedToTested']);
    }
}
